@include('_tabs')

@php
$st_srl = $staff ? (int)$staff->staff_srl : 0;
$st_rate = $staff ? number_format((int)$staff->share_rate / 100, 1, '.', '') : '';
$st_days = ['일', '월', '화', '수', '목', '금', '토'];
@endphp

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procReservationAdminInsertStaff" />
		<input type="hidden" name="staff_srl" value="{{ $st_srl }}" />

		<div class="rsva-panel">
			<h3>기본 정보</h3>
			<div class="rsva-form-grid">
				<div>
					<label>이름</label>
					<input type="text" name="name" value="{{ $staff->name ?? '' }}" required />
				</div>
				<div>
					<label>직급</label>
					<input type="text" name="position" value="{{ $staff->position ?? '' }}" placeholder="원장, 실장, 디자이너" />
				</div>
				<div>
					<label>기본 배분율 (%)</label>
					<input type="text" name="share_rate" value="{{ $st_rate }}" placeholder="45" />
				</div>
				<div>
					<label>노출 순서</label>
					<input type="number" name="list_order" value="{{ (int)($staff->list_order ?? 0) }}" />
				</div>
				<div>
					<label>상태</label>
					<select name="status">
						<option value="active" @if(($staff->status ?? 'active') === 'active') selected="selected" @endif>노출</option>
						<option value="hidden" @if(($staff->status ?? '') === 'hidden') selected="selected" @endif>숨김</option>
					</select>
				</div>
				<div>
					<label>사진 주소</label>
					<input type="text" name="thumb" value="{{ $staff->thumb ?? '' }}" />
				</div>
				@if (count($branches))
				<div>
					<label>소속 지점</label>
					<select name="branch_srl">
						<option value="0">지점 무관</option>
						@foreach ($branches as $b)
						<option value="{{ (int)$b->branch_srl }}" @if((int)($staff->branch_srl ?? 0) === (int)$b->branch_srl) selected="selected" @endif>{{ $b->name }}</option>
						@endforeach
					</select>
					<small style="color:#8b95a1">손님이 지점을 고르면 그 지점 담당자만 보입니다.</small>
				</div>
				@endif
			</div>

			<div class="rsva-field" style="margin-top:14px">
				<label>한 줄 소개</label>
				<input type="text" name="summary" value="{{ $staff->summary ?? '' }}" />
			</div>
			<div class="rsva-field">
				<label>소개</label>
				<textarea name="content" rows="4">{{ $staff->content ?? '' }}</textarea>
			</div>
			<div class="rsva-field">
				<label>연결 회원 아이디</label>
				<input type="text" name="member_id" value="{{ $staff_member_id ?? '' }}" placeholder="비워 두면 연결하지 않습니다" />
				<small style="color:#8b95a1">적어 두면 그 회원이 로그인해 자기 예약과 정산만 볼 수 있습니다.</small>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>맡는 시술</h3>
			<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">체크한 시술만 이 담당자로 예약됩니다. 값과 소요시간과 배분율을 비우면 시술 기본값을 씁니다.</p>

			@if (empty($resources))
			<p class="rsva-empty">먼저 예약상품(시술)을 등록하세요.</p>
			@else
			<table class="rsva-table">
				<thead><tr><th style="width:34%">시술</th><th>값(원)</th><th>소요시간(분)</th><th>배분율(%)</th></tr></thead>
				<tbody>
					@foreach ($resources as $r)
					@php
					$r_srl = (int)$r->resource_srl;
					$r_map = $service_map[$r_srl] ?? null;
					$r_price = ($r_map && (int)$r_map->price >= 0) ? (int)$r_map->price : '';
					$r_dur = ($r_map && (int)$r_map->duration > 0) ? (int)$r_map->duration : '';
					$r_rate = ($r_map && (int)$r_map->share_rate >= 0) ? number_format((int)$r_map->share_rate / 100, 1, '.', '') : '';
					@endphp
					<tr>
						<td>
							<label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:600">
								<input type="checkbox" name="svc_use[]" value="{{ $r_srl }}" @if($r_map) checked="checked" @endif />
								{{ $r->title }}
							</label>
							<small style="color:#9aa1ab">기본 {{ number_format((int)$r->price) }}원 / {{ (int)$r->duration }}분</small>
						</td>
						<td><input type="text" name="svc_price[{{ $r_srl }}]" value="{{ $r_price }}" placeholder="{{ (int)$r->price }}" /></td>
						<td><input type="number" name="svc_duration[{{ $r_srl }}]" value="{{ $r_dur }}" placeholder="{{ (int)$r->duration }}" /></td>
						<td><input type="text" name="svc_rate[{{ $r_srl }}]" value="{{ $r_rate }}" placeholder="{{ $st_rate }}" /></td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@endif
		</div>

		<div class="rsva-panel">
			<h3>근무 요일</h3>
			<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">체크한 요일의 시간대에만 예약을 받습니다. 개인 휴무는 운영 일정에서 넣습니다.</p>

			<table class="rsva-table">
				<thead><tr><th style="width:120px">요일</th><th>시작</th><th>종료</th></tr></thead>
				<tbody>
					@foreach ($st_days as $wd => $wd_label)
					@php
					$wd_rule = isset($schedules[$wd]) ? $schedules[$wd][0] : null;
					@endphp
					<tr>
						<td>
							<label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:600">
								<input type="checkbox" name="wd_use[]" value="{{ $wd }}" @if($wd_rule) checked="checked" @endif />
								{{ $wd_label }}
							</label>
						</td>
						<td><input type="time" name="wd_start[{{ $wd }}]" value="{{ $wd_rule->start_time ?? '10:00' }}" /></td>
						<td><input type="time" name="wd_end[{{ $wd }}]" value="{{ $wd_rule->end_time ?? '20:00' }}" /></td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div style="text-align:right">
			<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaff') }}" class="rsva-btn">목록</a>
			<button type="submit" class="rsva-btn rsva-btn-primary">저장</button>
		</div>
	</form>
</div>
