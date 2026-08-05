@include('_tabs')

<div class="rsva">
	{{-- 필터 --}}
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispReservationAdminBookings" />
		<select name="f_status">
			<option value="">전체 상태</option>
			@foreach (['hold', 'pending', 'confirmed', 'cancelled', 'noshow', 'done', 'expired'] as $st)
			<option value="{{ $st }}" @if($filters->status === $st) selected @endif>{{ $lang->{'reservation_status_' . $st} }}</option>
			@endforeach
		</select>
		<select name="f_resource">
			<option value="">전체 예약상품</option>
			@foreach ($resources_map as $srl => $r)
			<option value="{{ $srl }}" @if($filters->resource === $srl) selected @endif>{{ $r->title }}</option>
			@endforeach
		</select>
		<input type="date" name="f_from" value="{{ $filters->from ? substr($filters->from,0,4).'-'.substr($filters->from,4,2).'-'.substr($filters->from,6,2) : '' }}" />
		<input type="date" name="f_to" value="{{ $filters->to ? substr($filters->to,0,4).'-'.substr($filters->to,4,2).'-'.substr($filters->to,6,2) : '' }}" />
		<input type="text" name="f_keyword" placeholder="이름·연락처·예약번호" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">검색</button>
		<button type="button" class="rsva-btn rsva-btn-primary" onclick="document.getElementById('rsva_manual').style.display='block';rsvaLoadSlots();">수동 예약 등록</button>
	</form>

	{{-- 수동 예약 (전화 예약 대행) --}}
	<div class="rsva-panel" id="rsva_manual" style="display:none">
		<h3>수동 예약 등록</h3>
		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminManualBooking" />
			<div class="rsva-inline">
				<div style="min-width:180px">
					<label>예약상품</label>
					<select id="rsva_m_resource" onchange="rsvaLoadSlots()">
						@foreach ($resources_map as $srl => $r)
						<option value="{{ $srl }}">{{ $r->title }}</option>
						@endforeach
					</select>
				</div>
				<div style="min-width:220px">
					<label>시간대 (잔여)</label>
					<select name="slot_srl" id="rsva_m_slot"><option value="">불러오는 중...</option></select>
				</div>
				<div><label>이름</label><input type="text" name="booker_name" required /></div>
				<div><label>연락처</label><input type="text" name="booker_phone" /></div>
				<div><label>인원</label><input type="number" name="person_count" value="1" min="1" max="100" style="width:70px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">등록</button></div>
			</div>
		</form>
	</div>

	@if (empty($bookings))
	<p class="rsva-empty">조건에 맞는 예약이 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>예약일시</th><th>예약상품</th><th>예약자</th><th>연락처</th><th>인원</th><th>금액</th><th>상태</th><th>처리</th></tr></thead>
		<tbody>
			@foreach ($bookings as $b)
			<tr>
				<td>{{ $b->slot_date ? substr($b->slot_date,0,4).'.'.substr($b->slot_date,4,2).'.'.substr($b->slot_date,6,2) : '-' }} {{ $b->start_time }}<br /><small style="color:#9aa1ab">{{ $b->booking_code }}</small></td>
				<td>{{ $resources_map[(int)$b->resource_srl]->title ?? '-' }}</td>
				<td>{{ $b->booker_name }}</td>
				<td>{{ $b->booker_phone ?: '-' }}</td>
				<td>{{ $b->person_count }}</td>
				<td>{{ $b->amount > 0 ? number_format($b->amount) : '-' }}</td>
				<td><span class="rsva-st rsva-st-{{ $b->status }}">{{ $lang->{'reservation_status_' . $b->status} ?? $b->status }}</span></td>
				<td>
					<form action="{{ getUrl('') }}" method="post" style="display:flex;gap:4px;flex-wrap:wrap" onsubmit="return confirm('처리하시겠습니까?')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procReservationAdminUpdateBooking" />
						<input type="hidden" name="booking_srl" value="{{ $b->booking_srl }}" />
						@if (in_array($b->status, ['hold', 'pending']))
						<button type="submit" name="booking_action" value="confirm" class="rsva-btn rsva-btn-sm">확정</button>
						@endif
						@if (in_array($b->status, ['hold', 'pending', 'confirmed']))
						<button type="submit" name="booking_action" value="cancel" class="rsva-btn rsva-btn-sm rsva-btn-danger">취소</button>
						@endif
						@if ($b->status === 'confirmed')
						<button type="submit" name="booking_action" value="noshow" class="rsva-btn rsva-btn-sm rsva-btn-danger">노쇼</button>
						<button type="submit" name="booking_action" value="done" class="rsva-btn rsva-btn-sm">완료</button>
						@endif
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif

	@if ($page_navigation ?? false)
	<div style="margin-top:14px;text-align:center">
		{!! $page_navigation->printNavigation ?? '' !!}
	</div>
	@endif
</div>

<script>
function rsvaLoadSlots() {
	var resource = document.getElementById('rsva_m_resource');
	var slotSel = document.getElementById('rsva_m_slot');
	if (!resource || !slotSel) return;
	slotSel.innerHTML = '<option value="">불러오는 중...</option>';
	var headers = { 'Content-Type': 'application/json' };
	var meta = document.querySelector('meta[name="csrf-token"]');
	if (meta) headers['X-CSRF-Token'] = meta.getAttribute('content');
	fetch('./', {
		method: 'POST', headers: headers, credentials: 'same-origin',
		body: JSON.stringify({ module: 'admin', act: 'procReservationAdminGetBookings', resource_srl: resource.value })
	})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			slotSel.innerHTML = '';
			var slots = (data.slots || []).filter(function (s) { return s.remain > 0 && s.status === 'open'; });
			if (!slots.length) { slotSel.innerHTML = '<option value="">예약 가능한 시간이 없습니다</option>'; return; }
			slots.forEach(function (s) {
				var opt = document.createElement('option');
				opt.value = s.slot_srl;
				opt.textContent = s.date.slice(4,6) + '.' + s.date.slice(6,8) + ' ' + s.start + ' (잔여 ' + s.remain + ')';
				slotSel.appendChild(opt);
			});
		})
		.catch(function () { slotSel.innerHTML = '<option value="">불러오기 실패</option>'; });
}
</script>
