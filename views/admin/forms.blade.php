@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>예약 시 추가로 받을 항목</h3>

		@if (empty($fields))
		<p class="rsva-empty">추가 문항이 없습니다. 기본 항목(이름·연락처·이메일) 외에 더 받을 정보를 등록하세요.</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>순서</th><th>이름표</th><th>필드명</th><th>형식</th><th>필수</th><th>대상</th><th></th></tr></thead>
			<tbody>
				@foreach ($fields as $f)
				<tr>
					<td>{{ $f->list_order }}</td>
					<td>{{ $f->label }}</td>
					<td><code>{{ $f->field_name }}</code></td>
					<td>{{ $f->field_type }}</td>
					<td>{{ $f->required === 'Y' ? '필수' : '-' }}</td>
					<td>{{ (int)$f->resource_srl === 0 ? '전체' : ($resources_map[(int)$f->resource_srl]->title ?? '#' . $f->resource_srl) }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteField" />
							<input type="hidden" name="field_srl" value="{{ $f->field_srl }}" />
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
			<input type="hidden" name="act" value="procReservationAdminInsertField" />
			<div class="rsva-inline">
				<div><label>이름표 *</label><input type="text" name="label" placeholder="예: 요청사항" required /></div>
				<div><label>필드명 * (영문)</label><input type="text" name="field_name" placeholder="request" pattern="[a-z0-9_]+" required /></div>
				<div><label>형식</label><select name="field_type"><option value="text">한 줄</option><option value="textarea">여러 줄</option><option value="select">선택형</option><option value="checkbox">체크박스</option><option value="tel">전화번호</option></select></div>
				<div><label>필수</label><select name="required"><option value="N">선택</option><option value="Y">필수</option></select></div>
				<div style="min-width:150px">
					<label>대상</label>
					<select name="resource_srl">
						<option value="0">전체 공통</option>
						@foreach ($resources_map as $srl => $r)
						<option value="{{ $srl }}">{{ $r->title }}</option>
						@endforeach
					</select>
				</div>
				<div><label>순서</label><input type="number" name="list_order" value="0" style="width:60px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
			</div>
			<div class="rsva-field" style="margin-top:10px;max-width:420px">
				<label>선택형 옵션 (줄바꿈 구분, 선택형일 때만)</label>
				<textarea name="options" rows="3" placeholder="옵션1&#10;옵션2"></textarea>
			</div>
		</form>
	</div>
</div>
