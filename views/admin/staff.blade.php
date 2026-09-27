@include('_tabs')

<div class="rsva">
	<div style="margin-bottom:14px;text-align:right">
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaffEdit') }}" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_new_staff }}</a>
	</div>

	@if (empty($staff_list))
	<p class="rsva-empty">{{ $lang->rsv_adm_no_staff }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->rsv_staff_name }}</th><th>{{ $lang->rsv_staff_position }}</th><th>{{ $lang->rsv_staff_service_count }}</th><th>{{ $lang->rsv_adm_default_share }}</th><th>{{ $lang->rsv_adm_linked_member }}</th><th>{{ $lang->reservation_status }}</th><th>{{ $lang->rsv_adm_manage }}</th></tr></thead>
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
				<td>{{ sprintf($lang->rsv_adm_count_format, (int)($staff_service_counts[$s_srl] ?? 0)) }}</td>
				<td>{{ $s_rate }}%</td>
				<td>{{ (int)$s->member_srl > 0 ? $lang->rsv_adm_linked : '-' }}</td>
				<td><span class="rsva-st {{ $s_open ? 'rsva-st-confirmed' : '' }}">{{ $s_open ? $lang->rsv_staff_status_active : $lang->rsv_staff_status_hidden }}</span></td>
				<td>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaffEdit', 'staff_srl', $s_srl) }}" class="rsva-btn rsva-btn-sm">{{ $lang->rsv_adm_edit }}</a>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->confirm_rsv_staff_delete }}')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procReservationAdminDeleteStaff" />
						<input type="hidden" name="staff_srl" value="{{ $s_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->rsv_adm_delete }}</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
