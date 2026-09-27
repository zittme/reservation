@include('_tabs')

<div class="rsva">
	{{-- 필터 --}}
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispReservationAdminBookings" />
		<select name="f_status">
			<option value="">{{ $lang->rsv_adm_all_status }}</option>
			@foreach (['hold', 'pending', 'confirmed', 'cancelled', 'noshow', 'done', 'expired'] as $st)
			<option value="{{ $st }}" @if($filters->status === $st) selected @endif>{{ $lang->{'reservation_status_' . $st} }}</option>
			@endforeach
		</select>
		<select name="f_resource">
			<option value="">{{ $lang->rsv_adm_all_resources }}</option>
			@foreach ($resources_map as $srl => $r)
			<option value="{{ $srl }}" @if($filters->resource === $srl) selected @endif>{{ $r->title }}</option>
			@endforeach
		</select>
		<input type="date" name="f_from" value="{{ $filters->from ? substr($filters->from,0,4).'-'.substr($filters->from,4,2).'-'.substr($filters->from,6,2) : '' }}" />
		<input type="date" name="f_to" value="{{ $filters->to ? substr($filters->to,0,4).'-'.substr($filters->to,4,2).'-'.substr($filters->to,6,2) : '' }}" />
		<input type="text" name="f_keyword" placeholder="{{ $lang->rsv_adm_keyword_ph }}" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">{{ $lang->rsv_adm_search }}</button>
		<button type="button" class="rsva-btn rsva-btn-primary" onclick="document.getElementById('rsva_manual').style.display='block';rsvaLoadSlots();">{{ $lang->rsv_adm_manual_booking }}</button>
	</form>

	{{-- 수동 예약 (전화 예약 대행) --}}
	<div class="rsva-panel" id="rsva_manual" style="display:none">
		<h3>{{ $lang->rsv_adm_manual_booking }}</h3>
		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminManualBooking" />
			<div class="rsva-inline">
				<div style="min-width:180px">
					<label>{{ $lang->rsv_adm_resource }}</label>
					<select id="rsva_m_resource" onchange="rsvaLoadSlots()">
						@foreach ($resources_map as $srl => $r)
						<option value="{{ $srl }}">{{ $r->title }}</option>
						@endforeach
					</select>
				</div>
				<div style="min-width:220px">
					<label>{{ $lang->rsv_adm_slot_remain }}</label>
					<select name="slot_srl" id="rsva_m_slot"><option value="">{{ $lang->rsv_adm_loading }}</option></select>
				</div>
				<div><label>{{ $lang->reservation_booker_name }}</label><input type="text" name="booker_name" required /></div>
				<div><label>{{ $lang->reservation_booker_phone }}</label><input type="text" name="booker_phone" /></div>
				<div><label>{{ $lang->reservation_person }}</label><input type="number" name="person_count" value="1" min="1" max="100" style="width:70px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_register }}</button></div>
			</div>
		</form>
	</div>

	@if (empty($bookings))
	<p class="rsva-empty">{{ $lang->rsv_adm_no_bookings_match }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->reservation_date }}</th><th>{{ $lang->rsv_adm_resource }}</th><th>{{ $lang->rsv_adm_booker }}</th><th>{{ $lang->reservation_booker_phone }}</th><th>{{ $lang->reservation_person }}</th><th>{{ $lang->rsv_settlement_item_amount }}</th><th>{{ $lang->reservation_status }}</th><th>{{ $lang->rsv_adm_process }}</th></tr></thead>
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
					<form action="{{ getUrl('') }}" method="post" style="display:flex;gap:4px;flex-wrap:wrap" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_process }}')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procReservationAdminUpdateBooking" />
						<input type="hidden" name="booking_srl" value="{{ $b->booking_srl }}" />
						@if (in_array($b->status, ['hold', 'pending']))
						<button type="submit" name="booking_action" value="confirm" class="rsva-btn rsva-btn-sm">{{ $lang->rsv_adm_btn_confirm }}</button>
						@endif
						@if (in_array($b->status, ['hold', 'pending', 'confirmed']))
						<button type="submit" name="booking_action" value="cancel" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->rsv_adm_btn_cancel }}</button>
						@endif
						@if ($b->status === 'confirmed')
						<button type="submit" name="booking_action" value="noshow" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->reservation_status_noshow }}</button>
						<button type="submit" name="booking_action" value="done" class="rsva-btn rsva-btn-sm">{{ $lang->rsv_adm_btn_done }}</button>
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
	slotSel.innerHTML = '<option value="">{{ $lang->rsv_adm_loading }}</option>';
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
			if (!slots.length) { slotSel.innerHTML = '<option value="">{{ $lang->rsv_adm_no_slots_available }}</option>'; return; }
			slots.forEach(function (s) {
				var opt = document.createElement('option');
				opt.value = s.slot_srl;
				opt.textContent = s.date.slice(4,6) + '.' + s.date.slice(6,8) + ' ' + s.start + ' (' + '{{ $lang->reservation_remain }}'.replace('%d', s.remain) + ')';
				slotSel.appendChild(opt);
			});
		})
		.catch(function () { slotSel.innerHTML = '<option value="">{{ $lang->rsv_adm_load_failed }}</option>'; });
}
</script>
