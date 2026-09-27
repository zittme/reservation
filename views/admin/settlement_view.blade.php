@include('_tabs')

@php
$sv_srl = (int)$settlement->settlement_srl;
$sv_status = (string)$settlement->status;
$sv_status_names = ['draft' => $lang->rsv_settlement_status_draft, 'confirmed' => $lang->rsv_settlement_status_confirmed, 'paid' => $lang->rsv_settlement_status_paid];
$sv_locked = in_array($sv_status, ['confirmed', 'paid'], true);
@endphp

<div class="rsva">
	<div class="rsva-cards">
		<div class="rsva-card"><b>{{ number_format((int)$settlement->gross_amount) }}</b><span>{{ $lang->rsv_settlement_gross }}</span></div>
		<div class="rsva-card"><b>{{ number_format((int)$settlement->share_amount) }}</b><span>{{ $lang->rsv_settlement_share }}</span></div>
		<div class="rsva-card"><b>{{ number_format((int)$settlement->store_amount) }}</b><span>{{ $lang->rsv_settlement_store }}</span></div>
		<div class="rsva-card"><b>{{ (int)$settlement->booking_count }}</b><span>{{ $lang->rsv_settlement_count }}</span></div>
	</div>

	<div class="rsva-panel">
		<h3>{{ $staff ? $staff->name : '-' }} / {{ $settlement->period_from }} ~ {{ $settlement->period_to }}</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">
			{{ $lang->reservation_status }} <span class="rsva-st {{ $sv_status === 'draft' ? '' : 'rsva-st-confirmed' }}">{{ $sv_status_names[$sv_status] ?? $sv_status }}</span>
			@if ($sv_locked)
			<span style="margin-left:8px">{{ $lang->rsv_adm_locked_note }}</span>
			@endif
		</p>

		<div style="display:flex;gap:8px;flex-wrap:wrap">
			@if ($sv_status === 'draft')
			<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->confirm_rsv_settlement_confirm }}')">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminChangeSettlement" />
				<input type="hidden" name="settlement_srl" value="{{ $sv_srl }}" />
				<input type="hidden" name="status" value="confirmed" />
				<button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_settlement_confirm }}</button>
			</form>
			<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->confirm_rsv_settlement_delete }}')">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminDeleteSettlement" />
				<input type="hidden" name="settlement_srl" value="{{ $sv_srl }}" />
				<button type="submit" class="rsva-btn rsva-btn-danger">{{ $lang->rsv_settlement_delete }}</button>
			</form>
			@elseif ($sv_status === 'confirmed')
			<form action="{{ getUrl('') }}" method="post" style="display:inline">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminChangeSettlement" />
				<input type="hidden" name="settlement_srl" value="{{ $sv_srl }}" />
				<input type="hidden" name="status" value="paid" />
				<button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_settlement_pay }}</button>
			</form>
			@endif
			<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlements') }}" class="rsva-btn">{{ $lang->rsv_adm_list }}</a>
		</div>
	</div>

	@if (empty($items))
	<p class="rsva-empty">{{ $lang->rsv_adm_no_items }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->rsv_settlement_item_date }}</th><th>{{ $lang->rsv_settlement_item_service }}</th><th>{{ $lang->rsv_settlement_item_amount }}</th><th>{{ $lang->rsv_settlement_item_rate }}</th><th>{{ $lang->rsv_settlement_item_share }}</th></tr></thead>
		<tbody>
			@foreach ($items as $it)
			@php
			$it_res = $resources[(int)$it->resource_srl] ?? null;
			$it_date = (string)$it->service_date;
			$it_when = strlen($it_date) === 8 ? substr($it_date, 0, 4) . '-' . substr($it_date, 4, 2) . '-' . substr($it_date, 6, 2) : '-';
			@endphp
			<tr>
				<td>{{ $it_when }}</td>
				<td>{{ $it_res ? $it_res->title : '-' }}</td>
				<td>{{ sprintf($lang->reservation_price_format, number_format((int)$it->amount)) }}</td>
				<td>{{ number_format((int)$it->share_rate / 100, 1) }}%</td>
				<td><b style="color:#2677e3">{{ sprintf($lang->reservation_price_format, number_format((int)$it->share_amount)) }}</b></td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
