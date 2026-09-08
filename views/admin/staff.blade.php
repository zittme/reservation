@include('_tabs')

<div class="rsva">
	<div style="margin-bottom:14px;text-align:right">
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaffEdit') }}" class="rsva-btn rsva-btn-primary">새 담당자</a>
	</div>

	@if (empty($staff_list))
	<p class="rsva-empty">등록된 담당자가 없습니다. 담당자를 등록하고 맡는 시술과 근무 요일을 정하세요.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>이름</th><th>직급</th><th>맡는 시술</th><th>기본 배분율</th><th>연결 회원</th><th>상태</th><th>관리</th></tr></thead>
		<tbody>
			@foreach ($staff_list as $s)
			@php
			$s_srl = (int)$s->staff_srl;
			$s_rate = number_format((int)$s->share_rate / 100, 1);
			$s_open = (string)$s->status === 'active';
			@endphp
			<tr>
				<td><strong>{{ $s->name }}</strong>@if($s->summary)<br /><small style="color:#9aa1ab">{{ $s->summary }}</small>@endif</td>
				<td>{{ $s->position }}</td>
				<td>{{ (int)($staff_service_counts[$s_srl] ?? 0) }}개</td>
				<td>{{ $s_rate }}%</td>
				<td>{{ (int)$s->member_srl > 0 ? '연결됨' : '-' }}</td>
				<td><span class="rsva-st {{ $s_open ? 'rsva-st-confirmed' : '' }}">{{ $s_open ? '노출' : '숨김' }}</span></td>
				<td>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaffEdit', 'staff_srl', $s_srl) }}" class="rsva-btn rsva-btn-sm">편집</a>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까? 예약이 걸려 있으면 지우지 않고 숨김으로 바뀝니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procReservationAdminDeleteStaff" />
						<input type="hidden" name="staff_srl" value="{{ $s_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
