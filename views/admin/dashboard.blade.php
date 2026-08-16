@include('_tabs')

@if (Context::get('zmc_console'))
<div class="rsva">
	<div class="rsva-cards">
		<div class="rsva-card"><b>{{ number_format($stat_today) }}</b><span>오늘 예약</span></div>
		<div class="rsva-card"><b>{{ number_format($stat_week) }}</b><span>이번 주 예약</span></div>
		<div class="rsva-card"><b>{{ number_format($stat_wait) }}</b><span>결제·입금 대기</span></div>
		<div class="rsva-card"><b>{{ $pay_available ? 'ON' : 'OFF' }}</b><span>결제 연동 (zittme_pay)</span></div>
	</div>

	<div class="rsva-panel">
		<h3>임박한 예약</h3>
		@if (empty($upcoming))
		<p class="rsva-empty">예정된 예약이 없습니다.</p>
		@else
		<table class="rsva-table">
			<thead><tr><th>예약일시</th><th>예약상품</th><th>예약자</th><th>인원</th><th>상태</th><th>예약번호</th></tr></thead>
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
		<h3>운영 안내</h3>
		<p style="margin:0;font-size:13px;color:#6b7684;line-height:1.8">
			예약 접수·자원·스케줄 등 모든 운영은 위의 <strong>전용 콘솔</strong>에서 합니다.
		</p>
	</div>
</div>
@endif
