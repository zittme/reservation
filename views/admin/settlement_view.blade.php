@include('_tabs')

@php
$sv_srl = (int)$settlement->settlement_srl;
$sv_status = (string)$settlement->status;
$sv_status_names = ['draft' => '집계중', 'confirmed' => '확정', 'paid' => '지급완료'];
$sv_locked = in_array($sv_status, ['confirmed', 'paid'], true);
@endphp

<div class="rsva">
	<div class="rsva-cards">
		<div class="rsva-card"><b>{{ number_format((int)$settlement->gross_amount) }}</b><span>매출</span></div>
		<div class="rsva-card"><b>{{ number_format((int)$settlement->share_amount) }}</b><span>담당자 몫</span></div>
		<div class="rsva-card"><b>{{ number_format((int)$settlement->store_amount) }}</b><span>매장 몫</span></div>
		<div class="rsva-card"><b>{{ (int)$settlement->booking_count }}</b><span>건수</span></div>
	</div>

	<div class="rsva-panel">
		<h3>{{ $staff ? $staff->name : '-' }} / {{ $settlement->period_from }} ~ {{ $settlement->period_to }}</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">
			상태 <span class="rsva-st {{ $sv_status === 'draft' ? '' : 'rsva-st-confirmed' }}">{{ $sv_status_names[$sv_status] ?? $sv_status }}</span>
			@if ($sv_locked)
			<span style="margin-left:8px">확정한 회차는 금액이 바뀌지 않습니다.</span>
			@endif
		</p>

		<div style="display:flex;gap:8px;flex-wrap:wrap">
			@if ($sv_status === 'draft')
			<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('이 회차를 확정합니다. 확정한 뒤에는 금액이 바뀌지 않습니다. 계속할까요?')">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminChangeSettlement" />
				<input type="hidden" name="settlement_srl" value="{{ $sv_srl }}" />
				<input type="hidden" name="status" value="confirmed" />
				<button type="submit" class="rsva-btn rsva-btn-primary">확정하기</button>
			</form>
			<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('집계중인 회차를 지웁니다. 물린 예약은 다시 정산 대상으로 돌아갑니다. 계속할까요?')">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminDeleteSettlement" />
				<input type="hidden" name="settlement_srl" value="{{ $sv_srl }}" />
				<button type="submit" class="rsva-btn rsva-btn-danger">회차 지우기</button>
			</form>
			@elseif ($sv_status === 'confirmed')
			<form action="{{ getUrl('') }}" method="post" style="display:inline">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminChangeSettlement" />
				<input type="hidden" name="settlement_srl" value="{{ $sv_srl }}" />
				<input type="hidden" name="status" value="paid" />
				<button type="submit" class="rsva-btn rsva-btn-primary">지급완료로</button>
			</form>
			@endif
			<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlements') }}" class="rsva-btn">목록</a>
		</div>
	</div>

	@if (empty($items))
	<p class="rsva-empty">이 회차에 담긴 예약이 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>이용일</th><th>시술</th><th>금액</th><th>배분율</th><th>담당자 몫</th></tr></thead>
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
				<td>{{ number_format((int)$it->amount) }}원</td>
				<td>{{ number_format((int)$it->share_rate / 100, 1) }}%</td>
				<td><b style="color:#2677e3">{{ number_format((int)$it->share_amount) }}원</b></td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
