@include('_tabs')

<div class="rsva">
	<div style="margin-bottom:14px;text-align:right">
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit') }}" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_new_resource }}</a>
	</div>

	@if (empty($resources))
	<p class="rsva-empty">{{ $lang->rsv_adm_no_resources }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ $lang->rsv_adm_name }}</th><th>{{ $lang->rsv_adm_capacity }}</th><th>{{ $lang->rsv_adm_duration }}</th><th>{{ $lang->rsv_adm_price }}</th><th>{{ $lang->rsv_adm_payment }}</th><th>{{ $lang->reservation_status }}</th><th>{{ $lang->rsv_adm_manage }}</th></tr></thead>
		<tbody>
			@foreach ($resources as $r)
			<tr>
				<td><strong>{{ $r->title }}</strong>@if($r->summary)<br /><small style="color:#9aa1ab">{{ $r->summary }}</small>@endif</td>
				<td>{{ $r->capacity_default }}</td>
				<td>{{ sprintf($lang->reservation_minutes, $r->duration) }}</td>
				<td>{{ $r->price > 0 ? sprintf($lang->reservation_price_format, number_format($r->price)) : $lang->reservation_free }}</td>
				<td>{{ $r->require_payment === 'Y' ? $lang->rsv_adm_required : '-' }}</td>
				<td><span class="rsva-st {{ $r->status === 'open' ? 'rsva-st-confirmed' : '' }}">{{ $r->status === 'open' ? $lang->rsv_adm_public : $lang->rsv_adm_private }}</span></td>
				<td>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $r->resource_srl) }}" class="rsva-btn rsva-btn-sm">{{ $lang->rsv_adm_edit_hours }}</a>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete_resource }}')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procReservationAdminDeleteResource" />
						<input type="hidden" name="resource_srl" value="{{ $r->resource_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->rsv_adm_delete }}</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
