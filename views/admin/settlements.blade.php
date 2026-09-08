@include('_tabs')

@php
$se_status_names = ['draft' => '집계중', 'confirmed' => '확정', 'paid' => '지급완료'];
$se_today = date('Ymd');
$se_month_start = date('Ym') . '01';
@endphp

<div class="rsva">
	<div class="rsva-panel">
		<h3>정산 만들기</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">이용을 마친 예약만 집계합니다. 취소와 노쇼는 매출로 잡지 않습니다. 금액과 배분율은 예약 시점 값을 그대로 씁니다.</p>

		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminBuildSettlement" />
			<div>
				<label>담당자</label>
				<select name="staff_srl" required>
					<option value="">선택</option>
					@foreach ($staff_map as $s)
					<option value="{{ (int)$s->staff_srl }}">{{ $s->name }}</option>
					@endforeach
				</select>
			</div>
			<div>
				<label>시작일</label>
				<input type="text" name="period_from" value="{{ $se_month_start }}" placeholder="YYYYMMDD" />
			</div>
			<div>
				<label>종료일</label>
				<input type="text" name="period_to" value="{{ $se_today }}" placeholder="YYYYMMDD" />
			</div>
			<div>
				<button type="submit" class="rsva-btn rsva-btn-primary">만들기</button>
			</div>
		</form>
	</div>

	@if (empty($settlements))
	<p class="rsva-empty">정산 회차가 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>담당자</th><th>기간</th><th>건수</th><th>매출</th><th>담당자 몫</th><th>매장 몫</th><th>상태</th><th></th></tr></thead>
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
				<td>{{ number_format((int)$se->gross_amount) }}원</td>
				<td><b style="color:#2677e3">{{ number_format((int)$se->share_amount) }}원</b></td>
				<td>{{ number_format((int)$se->store_amount) }}원</td>
				<td><span class="rsva-st {{ $se_status === 'draft' ? '' : 'rsva-st-confirmed' }}">{{ $se_status_names[$se_status] ?? $se_status }}</span></td>
				<td style="text-align:right">
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlementView', 'settlement_srl', $se_srl) }}" class="rsva-btn rsva-btn-sm">상세</a>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
