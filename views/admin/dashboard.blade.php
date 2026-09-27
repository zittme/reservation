@include('_tabs')

@if (Context::get('zmc_console'))
<div class="rsva">
	<div class="rsva-cards">
		<div class="rsva-card"><b>{{ number_format($stat_today) }}</b><span>{{ $lang->rsv_adm_today_bookings }}</span></div>
		<div class="rsva-card"><b>{{ number_format($stat_week) }}</b><span>{{ $lang->rsv_adm_week_bookings }}</span></div>
		<div class="rsva-card"><b>{{ number_format($stat_wait) }}</b><span>{{ $lang->rsv_adm_awaiting_payment }}</span></div>
		<div class="rsva-card"><b>{{ $pay_available ? 'ON' : 'OFF' }}</b><span>{{ $lang->rsv_adm_pay_link }}</span></div>
	</div>

	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_upcoming }}</h3>
		@if (empty($upcoming))
		<p class="rsva-empty">{{ $lang->rsv_adm_no_upcoming }}</p>
		@else
		<table class="rsva-table">
			<thead><tr><th>{{ $lang->reservation_date }}</th><th>{{ $lang->rsv_adm_resource }}</th><th>{{ $lang->rsv_adm_booker }}</th><th>{{ $lang->reservation_person }}</th><th>{{ $lang->reservation_status }}</th><th>{{ $lang->reservation_booking_code }}</th></tr></thead>
			<tbody>
				@foreach ($upcoming as $b)
				<tr>
					<td>{{ $b->slot_date ? substr($b->slot_date, 4, 2) . '.' . substr($b->slot_date, 6, 2) : '-' }} {{ $b->start_time }}</td>
					<td>{{ $resources_map[(int)$b->resource_srl]->title ?? '-' }}</td>
					<td>{{ $b->booker_name }}</td>
					<td>{{ $b->person_count }}</td>
					<td><span class="rsva-st rsva-st-{{ $b->status }}">{{ $lang->{'reservation_status_' . $b->status} ?? $b->status }}</span></td>
					<td>{{ $b->booking_code }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
	</div>
</div>
@else
{{-- 코어 관리자에서는 콘솔 안내만 보여준다. 운영은 전용 콘솔로 일원화 --}}
<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_guide_title }}</h3>
		<p style="margin:0;font-size:13px;color:#6b7684;line-height:1.8">
			{!! $lang->rsv_adm_guide_body !!}
		</p>
	</div>
</div>
@endif
