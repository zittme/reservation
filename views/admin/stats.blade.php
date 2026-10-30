@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispReservationAdminStats" />
		<input type="date" name="f_from" value="{{ substr($stats->from,0,4) }}-{{ substr($stats->from,4,2) }}-{{ substr($stats->from,6,2) }}" />
		<input type="date" name="f_to" value="{{ substr($stats->to,0,4) }}-{{ substr($stats->to,4,2) }}-{{ substr($stats->to,6,2) }}" />
		<button type="submit" class="rsva-btn">{{ $lang->rsv_adm_search_btn }}</button>
	</form>

	<div class="rsva-cards">
		<div class="rsva-card"><b>{{ number_format($stats->total) }}</b><span>{{ $lang->rsv_adm_stat_total }}</span></div>
		<div class="rsva-card"><b>{{ number_format($stats->confirmed) }}</b><span>{{ $lang->rsv_adm_stat_confirmed }}</span></div>
		<div class="rsva-card"><b>{{ number_format($stats->cancelled) }}</b><span>{{ $lang->rsv_adm_stat_cancelled }} ({{ $stats->cancel_rate }}%)</span></div>
		<div class="rsva-card"><b>{{ number_format($stats->noshow) }}</b><span>{{ $lang->reservation_status_noshow }} ({{ $stats->noshow_rate }}%)</span></div>
	</div>

	@if (!empty($stats_breakdown['branches']) && (count($stats_breakdown['branches']) > 1 || $stats_breakdown['branches'][0]->srl > 0))
	<h3 style="margin:24px 0 10px;font-size:15px;font-weight:700">{{ $lang->rsv_adm_stat_by_branch }}</h3>
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->rsv_branch }}</th><th>{{ $lang->rsv_adm_stat_count }}</th><th>{{ $lang->rsv_adm_stat_sales }}</th></tr></thead>
		<tbody>
			@foreach ($stats_breakdown['branches'] as $row)
			<tr><td>{{ $row->name !== '' ? $row->name : $lang->rsv_adm_stat_no_branch }}</td><td>{{ number_format($row->count) }}</td><td>{{ sprintf($lang->reservation_price_format, number_format($row->total)) }}</td></tr>
			@endforeach
		</tbody>
	</table>
	@endif

	@if (!empty($stats_breakdown['staff']))
	<h3 style="margin:24px 0 10px;font-size:15px;font-weight:700">{{ $lang->rsv_adm_stat_by_staff }}</h3>
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->rsv_staff }}</th><th>{{ $lang->rsv_adm_stat_count }}</th><th>{{ $lang->rsv_adm_stat_sales }}</th></tr></thead>
		<tbody>
			@foreach ($stats_breakdown['staff'] as $row)
			<tr><td>{{ $row->name !== '' ? $row->name : $lang->rsv_adm_stat_no_staff }}</td><td>{{ number_format($row->count) }}</td><td>{{ sprintf($lang->reservation_price_format, number_format($row->total)) }}</td></tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
