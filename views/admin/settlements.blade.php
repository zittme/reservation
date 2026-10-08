@include('_tabs')

@php
$se_status_names = ['draft' => $lang->rsv_settlement_status_draft, 'confirmed' => $lang->rsv_settlement_status_confirmed, 'paid' => $lang->rsv_settlement_status_paid];
$se_today = \Zittme\Modules\Reservation\Controllers\Base::localDay();
$se_month_start = substr($se_today, 0, 6) . '01';
$se_error = preg_replace('/[^a-z_]/', '', (string)\Context::get('se_error'));
$se_error = in_array($se_error, ['need_staff', 'need_period', 'period_order', 'empty', 'save_failed'], true) ? $se_error : '';
$se_error_text = $se_error !== '' ? (string)lang('reservation.msg_rsv_settlement_err_' . $se_error) : '';
$se_pick_staff = (int)\Context::get('se_staff');
$se_from_value = preg_replace('/\D/', '', (string)\Context::get('se_from')) ?: $se_month_start;
$se_to_value = preg_replace('/\D/', '', (string)\Context::get('se_to')) ?: $se_today;
$se_from_ymd = strlen($se_from_value) === 8 ? substr($se_from_value, 0, 4) . '-' . substr($se_from_value, 4, 2) . '-' . substr($se_from_value, 6, 2) : '';
$se_to_ymd = strlen($se_to_value) === 8 ? substr($se_to_value, 0, 4) . '-' . substr($se_to_value, 4, 2) . '-' . substr($se_to_value, 6, 2) : '';
@endphp

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_settlement_build }}</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">{{ $lang->about_rsv_settlement }}</p>

		@if ($se_error_text !== '')
		<div class="rsva-alert" role="alert">
			<p>{{ $se_error_text }}</p>
			@if ($se_error === 'empty')
			<p class="rsva-alert-sub">{{ $lang->rsv_settlement_err_empty_help }}</p>
			@endif
			<div class="rsva-alert-actions">
				<a class="rsva-btn" href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlements') }}">{{ $lang->rsv_settlement_back }}</a>
				@if ($se_error === 'empty')
				<a class="rsva-btn" href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminBookings') }}">{{ $lang->rsv_settlement_go_bookings }}</a>
				@endif
			</div>
		</div>
		@endif

		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminBuildSettlement" />
			<div>
				<label>{{ $lang->rsv_staff }}</label>
				<select name="staff_srl" required>
					<option value="">{{ $lang->rsv_adm_select }}</option>
					@foreach ($staff_map as $s)
					<option value="{{ (int)$s->staff_srl }}" @if($se_pick_staff === (int)$s->staff_srl) selected @endif>{{ $s->name }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label>{{ $lang->rsv_adm_start_date }}</label>
				<input type="date" name="period_from" value="{{ $se_from_ymd }}" required />
			</div>
			<div>
				<label>{{ $lang->rsv_adm_end_date }}</label>
				<input type="date" name="period_to" value="{{ $se_to_ymd }}" required />
			</div>
			<div>
				<button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_create }}</button>
			</div>
		</form>
	</div>

	@if (empty($settlements))
	<p class="rsva-empty">{{ $lang->rsv_settlement_none }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->rsv_staff }}</th><th>{{ $lang->rsv_settlement_period }}</th><th>{{ $lang->rsv_settlement_count }}</th><th>{{ $lang->rsv_settlement_gross }}</th><th>{{ $lang->rsv_settlement_share }}</th><th>{{ $lang->rsv_settlement_store }}</th><th>{{ $lang->reservation_status }}</th><th></th></tr></thead>
		<tbody>
			@foreach ($settlements as $se)
			@php
			$se_srl = (int)$se->settlement_srl;
			$se_staff = $staff_map[(int)$se->staff_srl] ?? null;
			$se_status = (string)$se->status;
			@endphp
			<tr>
				<td><strong>{{ $se_staff ? $se_staff->name : '-' }}</strong></td>
				<td>{{ $se->period_from }} ~ {{ $se->period_to }}</td>
				<td>{{ (int)$se->booking_count }}</td>
				<td>{{ sprintf($lang->reservation_price_format, number_format((int)$se->gross_amount)) }}</td>
				<td><b style="color:#2677e3">{{ sprintf($lang->reservation_price_format, number_format((int)$se->share_amount)) }}</b></td>
				<td>{{ sprintf($lang->reservation_price_format, number_format((int)$se->store_amount)) }}</td>
				<td><span class="rsva-st {{ $se_status === 'draft' ? '' : 'rsva-st-confirmed' }}">{{ $se_status_names[$se_status] ?? $se_status }}</span></td>
				<td style="text-align:right">
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlementView', 'settlement_srl', $se_srl) }}" class="rsva-btn rsva-btn-sm">{{ $lang->rsv_adm_detail }}</a>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
