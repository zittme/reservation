@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>전체 공통 휴무·임시오픈</h3>
		<p style="margin:0 0 12px;font-size:13px;color:#6b7684">여기에 등록하면 모든 예약상품에 적용됩니다. 특정 상품만의 휴무는 예약상품 편집 화면에서 등록하세요.</p>

		@if (empty($holidays))
		<p class="rsva-empty">등록된 공통 휴무가 없습니다.</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>날짜</th><th>구분</th><th>시간</th><th>사유</th><th></th></tr></thead>
			<tbody>
				@foreach ($holidays as $h)
				<tr>
					<td>{{ substr($h->holiday_date,0,4) }}.{{ substr($h->holiday_date,4,2) }}.{{ substr($h->holiday_date,6,2) }}</td>
					<td>{{ $h->holiday_type === 'extra' ? '임시오픈' : '휴무' }}</td>
					<td>{{ $h->start_time ? $h->start_time . ' ~ ' . $h->end_time : '종일' }}</td>
					<td>{{ $h->reason ?: '-' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteHoliday" />
							<input type="hidden" name="holiday_srl" value="{{ $h->holiday_srl }}" />
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
			<input type="hidden" name="act" value="procReservationAdminInsertHoliday" />
			<input type="hidden" name="resource_srl" value="0" />
			<div class="rsva-inline">
				<div><label>날짜</label><input type="date" name="holiday_date" required /></div>
				<div><label>구분</label><select name="holiday_type"><option value="closed">휴무</option><option value="extra">임시오픈</option></select></div>
				<div><label>시작(선택)</label><input type="time" name="start_time" /></div>
				<div><label>종료(선택)</label><input type="time" name="end_time" /></div>
				<div><label>사유</label><input type="text" name="reason" placeholder="예: 추석 연휴" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
			</div>
		</form>
	</div>

	{{-- 슬롯 수동 마감 --}}
	<div class="rsva-panel">
		<h3>특정 시간대 수동 마감</h3>
		<p style="margin:0 0 12px;font-size:13px;color:#6b7684">예약상품과 기간을 고르면 시간대가 나옵니다. 마감한 시간대는 예약 화면에서 제외됩니다.</p>
		<div class="rsva-inline" style="margin-bottom:12px">
			<div style="min-width:180px">
				<label>예약상품</label>
				<select id="rsva_s_resource">
					@foreach ($resources_map as $srl => $r)
					<option value="{{ $srl }}">{{ $r->title }}</option>
					@endforeach
				</select>
			</div>
			<div><label>시작일</label><input type="date" id="rsva_s_from" value="{{ date('Y-m-d') }}" /></div>
			<div><label>종료일</label><input type="date" id="rsva_s_to" value="{{ date('Y-m-d', strtotime('+7 day')) }}" /></div>
			<div><button type="button" class="rsva-btn" onclick="rsvaLoadScheduleSlots()">조회</button></div>
		</div>
		<div id="rsva_s_result" class="rsva-empty">예약상품과 기간을 고른 뒤 조회하세요.</div>
	</div>
</div>

<script>
function rsvaLoadScheduleSlots() {
	var resource = document.getElementById('rsva_s_resource').value;
	var from = document.getElementById('rsva_s_from').value.replace(/-/g, '');
	var to = document.getElementById('rsva_s_to').value.replace(/-/g, '');
	var box = document.getElementById('rsva_s_result');
	box.textContent = '불러오는 중...';
	var headers = { 'Content-Type': 'application/json' };
	var meta = document.querySelector('meta[name="csrf-token"]');
	if (meta) headers['X-CSRF-Token'] = meta.getAttribute('content');
	fetch('./', {
		method: 'POST', headers: headers, credentials: 'same-origin',
		body: JSON.stringify({ module: 'admin', act: 'procReservationAdminGetBookings', resource_srl: resource, from: from, to: to })
	})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			var slots = data.slots || [];
			if (!slots.length) { box.textContent = '해당 기간에 슬롯이 없습니다.'; return; }
			box.classList.remove('rsva-empty');
			var html = '<table class="rsva-table"><thead><tr><th>날짜</th><th>시간</th><th>잔여</th><th>상태</th><th></th></tr></thead><tbody>';
			slots.forEach(function (s) {
				html += '<tr><td>' + s.date.slice(0,4) + '.' + s.date.slice(4,6) + '.' + s.date.slice(6,8) + '</td>'
					+ '<td>' + s.start + '</td><td>' + s.remain + '</td>'
					+ '<td>' + (s.status === 'closed' ? '<span class="rsva-st rsva-st-cancelled">마감</span>' : '<span class="rsva-st rsva-st-confirmed">오픈</span>') + '</td>'
					+ '<td><form action="{{ getUrl('') }}" method="post" style="display:inline">'
					+ '<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procReservationAdminCloseSlot" />'
					+ '<input type="hidden" name="slot_srl" value="' + s.slot_srl + '" />'
					+ '<button type="submit" class="rsva-btn rsva-btn-sm">' + (s.status === 'closed' ? '마감 해제' : '마감') + '</button></form></td></tr>';
			});
			html += '</tbody></table>';
			box.innerHTML = html;
		})
		.catch(function () { box.textContent = '불러오기 실패'; });
}
</script>
