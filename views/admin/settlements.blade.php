@include('_tabs')

@php
$se_status_names = ['draft' => $lang->rsv_settlement_status_draft, 'confirmed' => $lang->rsv_settlement_status_confirmed, 'paid' => $lang->rsv_settlement_status_paid];
$se_today = date('Ymd');
$se_month_start = date('Ym') . '01';
@endphp

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_settlement_build }}</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">{{ $lang->about_rsv_settlement }}</p>

		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminBuildSettlement" />
			<div>
				<label>{{ $lang->rsv_staff }}</label>
				<select name="staff_srl" required>
					<option value="">{{ $lang->rsv_adm_select }}</option>
					@foreach ($staff_map as $s)
					<option value="{{ (int)$s->staff_srl }}">{{ $s->name }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label>{{ $lang->rsv_adm_start_date }}</label>
				<input type="text" name="period_from" value="{{ $se_month_start }}" placeholder="YYYYMMDD" />
			</div>
			<div>
				<label>{{ $lang->rsv_adm_end_date }}</label>
				<input type="text" name="period_to" value="{{ $se_today }}" placeholder="YYYYMMDD" />
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
