@include('_tabs')

<div class="rsva">
	<div style="margin-bottom:14px;text-align:right">
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit') }}" class="rsva-btn rsva-btn-primary">새 예약상품</a>
	</div>

	@if (empty($resources))
	<p class="rsva-empty">등록된 예약상품이 없습니다. 새 예약상품을 등록하고 운영 시간을 설정하세요.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>이름</th><th>정원</th><th>이용시간</th><th>가격</th><th>결제</th><th>상태</th><th>관리</th></tr></thead>
		<tbody>
			@foreach ($resources as $r)
			<tr>
				<td><strong>{{ $r->title }}</strong>@if($r->summary)<br /><small style="color:#9aa1ab">{{ $r->summary }}</small>@endif</td>
				<td>{{ $r->capacity_default }}</td>
				<td>{{ $r->duration }}분</td>
				<td>{{ $r->price > 0 ? number_format($r->price) . '원' : '무료' }}</td>
				<td>{{ $r->require_payment === 'Y' ? '필수' : '-' }}</td>
				<td><span class="rsva-st {{ $r->status === 'open' ? 'rsva-st-confirmed' : '' }}">{{ $r->status === 'open' ? '공개' : '비공개' }}</span></td>
				<td>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $r->resource_srl) }}" class="rsva-btn rsva-btn-sm">편집·운영시간</a>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까? 예약 이력이 있으면 비공개로 전환됩니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procReservationAdminDeleteResource" />
						<input type="hidden" name="resource_srl" value="{{ $r->resource_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
