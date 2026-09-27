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
</div>
