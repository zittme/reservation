@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $resource ? '예약상품 편집' : '새 예약상품' }}</h3>
		<form action="{{ getUrl('') }}" method="post" enctype="multipart/form-data">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminInsertResource" />
			@if ($resource)
			<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
			@endif

			{{-- 대표 이미지 (정사각형으로 노출) --}}
			{{-- 중괄호 보간은 템플릿 컴파일러와 충돌한다 — 반드시 문자열 연결로 --}}
			@php $rsva_thumb_style = !empty($resource->thumb) ? "background-image:url('" . $resource->thumb . "');" : ''; @endphp
			<div class="rsva-field" style="display:flex;gap:14px;align-items:flex-start;margin-bottom:16px">
				<div style="flex:none;width:96px;height:96px;border:1px solid #e5e8ee;border-radius:12px;background:#f7f8fa center/cover no-repeat;{{ $rsva_thumb_style }}"></div>
				<div>
					<label>대표 이미지 (정사각형 권장, 10MB 이하)</label>
					<input type="file" name="thumb_file" accept="image/*" />
					@if (!empty($resource->thumb))
					<label style="display:inline-flex;align-items:center;gap:5px;margin-top:6px;font-weight:500"><input type="checkbox" name="thumb_delete" value="Y" /> 현재 이미지 삭제</label>
					@endif
					<small style="display:block;margin-top:4px;color:#6b7684">정사각형이 아니어도 가운데 기준으로 잘려서 표시됩니다.</small>
				</div>
			</div>

			<div class="rsva-form-grid">
				<div><label>이름 *</label><input type="text" name="title" required value="{{ $resource->title ?? '' }}" /></div>
				<div><label>요약</label><input type="text" name="summary" value="{{ $resource->summary ?? '' }}" /></div>
				<div><label>기본 정원 (1 = 1:1)</label><input type="number" name="capacity_default" min="1" max="1000" value="{{ $resource->capacity_default ?? 1 }}" /></div>
				<div><label>이용시간 (분)</label><input type="number" name="duration" min="5" max="1440" step="5" value="{{ $resource->duration ?? 60 }}" /></div>
				<div><label>가격 (0 = 무료)</label><input type="number" name="price" min="0" value="{{ $resource->price ?? 0 }}" /></div>
				<div><label>결제 필수</label><select name="require_payment"><option value="N" @if(($resource->require_payment ?? 'N') === 'N') selected @endif>아니요</option><option value="Y" @if(($resource->require_payment ?? '') === 'Y') selected @endif>예 (짓미 페이)</option></select></div>
				<div><label>준비 시간 (분, 앞)</label><input type="number" name="buffer_before" min="0" max="240" value="{{ $resource->buffer_before ?? 0 }}" /></div>
				<div><label>정리 시간 (분, 뒤)</label><input type="number" name="buffer_after" min="0" max="240" value="{{ $resource->buffer_after ?? 0 }}" /></div>
				<div><label>며칠 뒤까지 예약</label><input type="number" name="max_advance_days" min="1" max="366" value="{{ $resource->max_advance_days ?? 90 }}" /></div>
				<div><label>몇 분 전까지 예약</label><input type="number" name="min_lead_minutes" min="0" max="10080" value="{{ $resource->min_lead_minutes ?? 60 }}" /></div>
				<div><label>취소 마감 (시간 전)</label><input type="number" name="cancel_deadline_hours" min="0" max="720" value="{{ $resource->cancel_deadline_hours ?? 24 }}" /></div>
				<div><label>상태</label><select name="status"><option value="open" @if(($resource->status ?? 'open') === 'open') selected @endif>공개</option><option value="closed" @if(($resource->status ?? '') === 'closed') selected @endif>비공개</option></select></div>
			</div>
			<div style="margin-top:14px">
				<button type="submit" class="rsva-btn rsva-btn-primary">저장</button>
				<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResources') }}" class="rsva-btn">목록</a>
			</div>
		</form>
	</div>

	@if ($resource)
	{{-- 운영 규칙 — 리소스와 한 화면에서 관리 --}}
	<div class="rsva-panel">
		<h3>운영 시간 (요일별 규칙)</h3>
		@if (empty($rules))
		<p class="rsva-empty">운영 규칙이 없으면 슬롯이 만들어지지 않습니다. 아래에서 추가하세요.</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>요일</th><th>시간</th><th>간격</th><th>정원</th><th>적용 기간</th><th></th></tr></thead>
			<tbody>
				@foreach ($rules as $rule)
				<tr>
					<td>{{ ['일','월','화','수','목','금','토'][(int)$rule->weekday] }}</td>
					<td>{{ $rule->start_time }} ~ {{ $rule->end_time }}</td>
					<td>{{ $rule->interval_minutes }}분</td>
					<td>{{ $rule->capacity > 0 ? $rule->capacity : '기본값' }}</td>
					<td>{{ $rule->valid_from ?: '~' }} - {{ $rule->valid_to ?: '~' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteRule" />
							<input type="hidden" name="rule_srl" value="{{ $rule->rule_srl }}" />
							<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif

		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminInsertRule" />
			<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
			<div class="rsva-field">
				<label>요일 (복수 선택)</label>
				<div class="rsva-weekdays">
					@foreach (['일','월','화','수','목','금','토'] as $i => $d)
					<label><input type="checkbox" name="weekday[]" value="{{ $i }}" @if($i >= 1 && $i <= 5) checked @endif /> {{ $d }}</label>
					@endforeach
				</div>
			</div>
			<div class="rsva-inline">
				<div><label>시작</label><input type="time" name="start_time" value="09:00" required /></div>
				<div><label>종료</label><input type="time" name="end_time" value="18:00" required /></div>
				<div><label>타임 간격(분)</label><input type="number" name="interval_minutes" min="5" max="1440" step="5" placeholder="{{ $resource->duration }}" style="width:90px" /></div>
				<div><label>타임당 정원</label><input type="number" name="capacity" min="0" max="1000" placeholder="{{ $resource->capacity_default }}" style="width:90px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">규칙 추가</button></div>
			</div>
			<p style="margin:10px 0 0;font-size:12px;color:#6b7684;line-height:1.7">
				<strong>타임 간격</strong> = 다음 예약 타임이 시작되는 주기입니다. 예: 09:00~18:00에 간격 60분 → 09:00, 10:00, 11:00 … 타임이 만들어집니다.
				비워두면 이용시간({{ $resource->duration }}분)과 같게 적용됩니다. 정원도 비워두면 기본 정원({{ $resource->capacity_default }}명)을 씁니다.
			</p>
		</form>
	</div>

	{{-- 이 리소스의 휴무 --}}
	<div class="rsva-panel">
		<h3>휴무·임시오픈 (이 예약상품)</h3>
		@if (!empty($holidays))
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>날짜</th><th>구분</th><th>시간</th><th>사유</th><th>대상</th><th></th></tr></thead>
			<tbody>
				@foreach ($holidays as $h)
				<tr>
					<td>{{ substr($h->holiday_date,0,4) }}.{{ substr($h->holiday_date,4,2) }}.{{ substr($h->holiday_date,6,2) }}</td>
					<td>{{ $h->holiday_type === 'extra' ? '임시오픈' : '휴무' }}</td>
					<td>{{ $h->start_time ? $h->start_time . ' ~ ' . $h->end_time : '종일' }}</td>
					<td>{{ $h->reason ?: '-' }}</td>
					<td>{{ (int)$h->resource_srl === 0 ? '전체 공통' : '이 상품' }}</td>
					<td>
						@if ((int)$h->resource_srl !== 0)
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteHoliday" />
							<input type="hidden" name="holiday_srl" value="{{ $h->holiday_srl }}" />
							<input type="hidden" name="success_return_url" value="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource->resource_srl) }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
						</form>
						@endif
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif

		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminInsertHoliday" />
			<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
			<input type="hidden" name="success_return_url" value="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource->resource_srl) }}" />
			<div class="rsva-inline">
				<div><label>날짜</label><input type="date" name="holiday_date" required /></div>
				<div><label>구분</label><select name="holiday_type"><option value="closed">휴무</option><option value="extra">임시오픈</option></select></div>
				<div><label>시작(선택)</label><input type="time" name="start_time" /></div>
				<div><label>종료(선택)</label><input type="time" name="end_time" /></div>
				<div><label>사유</label><input type="text" name="reason" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
			</div>
		</form>
	</div>
	@endif
</div>
