@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_holidays_common }}</h3>
		<p style="margin:0 0 12px;font-size:13px;color:#6b7684">{{ $lang->rsv_adm_holidays_common_help }}</p>

		@if (empty($holidays))
		<p class="rsva-empty">{{ $lang->rsv_adm_no_holidays }}</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ $lang->rsv_adm_date }}</th><th>{{ $lang->rsv_adm_type }}</th><th>{{ $lang->rsv_adm_time }}</th><th>{{ $lang->rsv_adm_reason }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($holidays as $h)
				<tr>
					<td>{{ substr($h->holiday_date,0,4) }}.{{ substr($h->holiday_date,4,2) }}.{{ substr($h->holiday_date,6,2) }}</td>
					<td>{{ $h->holiday_type === 'extra' ? $lang->rsv_adm_extra_open : $lang->rsv_adm_closed }}</td>
					<td>{{ $h->start_time ? $h->start_time . ' ~ ' . $h->end_time : $lang->rsv_adm_all_day }}</td>
					<td>{{ $h->reason ?: '-' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteHoliday" />
							<input type="hidden" name="holiday_srl" value="{{ $h->holiday_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->rsv_adm_delete }}</button>
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
				<div><label>{{ $lang->rsv_adm_date }}</label><input type="date" name="holiday_date" required /></div>
				<div><label>{{ $lang->rsv_adm_type }}</label><select name="holiday_type"><option value="closed">{{ $lang->rsv_adm_closed }}</option><option value="extra">{{ $lang->rsv_adm_extra_open }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_start_opt }}</label><input type="time" name="start_time" /></div>
				<div><label>{{ $lang->rsv_adm_end_opt }}</label><input type="time" name="end_time" /></div>
				<div><label>{{ $lang->rsv_adm_reason }}</label><input type="text" name="reason" placeholder="{{ $lang->rsv_adm_ph_reason }}" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_add }}</button></div>
			</div>
		</form>
	</div>

	{{-- 슬롯 수동 마감 --}}
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_close_slots }}</h3>
		<p style="margin:0 0 12px;font-size:13px;color:#6b7684">{{ $lang->rsv_adm_close_slots_help }}</p>
		<div class="rsva-inline" style="margin-bottom:12px">
			<div style="min-width:180px">
				<label>{{ $lang->rsv_adm_resource }}</label>
				<select id="rsva_s_resource">
					@foreach ($resources_map as $srl => $r)
					<option value="{{ $srl }}">{{ $r->title }}</option>
					@endforeach
				</select>
			</div>
			<div><label>{{ $lang->rsv_adm_start_date }}</label><input type="date" id="rsva_s_from" value="{{ date('Y-m-d') }}" /></div>
			<div><label>{{ $lang->rsv_adm_end_date }}</label><input type="date" id="rsva_s_to" value="{{ date('Y-m-d', strtotime('+7 day')) }}" /></div>
			<div><button type="button" class="rsva-btn" onclick="rsvaLoadScheduleSlots()">{{ $lang->rsv_adm_search_btn }}</button></div>
		</div>
		<div id="rsva_s_result" class="rsva-empty">{{ $lang->rsv_adm_pick_then_view }}</div>
	</div>
</div>

<script>
function rsvaLoadScheduleSlots() {
	var resource = document.getElementById('rsva_s_resource').value;
	var from = document.getElementById('rsva_s_from').value.replace(/-/g, '');
	var to = document.getElementById('rsva_s_to').value.replace(/-/g, '');
	var box = document.getElementById('rsva_s_result');
	box.textContent = '{{ $lang->rsv_adm_loading }}';
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
			if (!slots.length) { box.textContent = '{{ $lang->rsv_adm_no_slots_period }}'; return; }
			box.classList.remove('rsva-empty');
			var html = '<table class="rsva-table"><thead><tr><th>{{ $lang->rsv_adm_date }}</th><th>{{ $lang->rsv_adm_time }}</th><th>{{ $lang->rsv_adm_remain }}</th><th>{{ $lang->reservation_status }}</th><th></th></tr></thead><tbody>';
			slots.forEach(function (s) {
				html += '<tr><td>' + s.date.slice(0,4) + '.' + s.date.slice(4,6) + '.' + s.date.slice(6,8) + '</td>'
					+ '<td>' + s.start + '</td><td>' + s.remain + '</td>'
					+ '<td>' + (s.status === 'closed' ? '<span class="rsva-st rsva-st-cancelled">{{ $lang->rsv_adm_slot_closed }}</span>' : '<span class="rsva-st rsva-st-confirmed">{{ $lang->rsv_adm_slot_open }}</span>') + '</td>'
					+ '<td><form action="{{ getUrl('') }}" method="post" style="display:inline">'
					+ '<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procReservationAdminCloseSlot" />'
					+ '<input type="hidden" name="slot_srl" value="' + s.slot_srl + '" />'
					+ '<button type="submit" class="rsva-btn rsva-btn-sm">' + (s.status === 'closed' ? '{{ $lang->rsv_adm_reopen }}' : '{{ $lang->rsv_adm_close }}') + '</button></form></td></tr>';
			});
			html += '</tbody></table>';
			box.innerHTML = html;
		})
		.catch(function () { box.textContent = '{{ $lang->rsv_adm_load_failed }}'; });
}
</script>
