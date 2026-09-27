@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_extra_fields }}</h3>

		@if (empty($fields))
		<p class="rsva-empty">{{ $lang->rsv_adm_no_fields }}</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ $lang->rsv_adm_order }}</th><th>{{ $lang->rsv_adm_label }}</th><th>{{ $lang->rsv_adm_field_name }}</th><th>{{ $lang->rsv_adm_field_type }}</th><th>{{ $lang->rsv_adm_required }}</th><th>{{ $lang->rsv_adm_target }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($fields as $f)
				<tr>
					<td>{{ $f->list_order }}</td>
					<td>{{ $f->label }}</td>
					<td><code>{{ $f->field_name }}</code></td>
					<td>{{ $f->field_type }}</td>
					<td>{{ $f->required === 'Y' ? $lang->rsv_adm_required : '-' }}</td>
					<td>{{ (int)$f->resource_srl === 0 ? $lang->rsv_adm_all : ($resources_map[(int)$f->resource_srl]->title ?? '#' . $f->resource_srl) }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteField" />
							<input type="hidden" name="field_srl" value="{{ $f->field_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->rsv_adm_delete }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif

		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminInsertField" />
			<div class="rsva-inline">
				<div><label>{{ $lang->rsv_adm_label }} *</label><input type="text" name="label" placeholder="{{ $lang->rsv_adm_ph_label }}" required /></div>
				<div><label>{{ $lang->rsv_adm_field_name }} * {{ $lang->rsv_adm_field_name_hint }}</label><input type="text" name="field_name" placeholder="request" pattern="[a-z0-9_]+" required /></div>
				<div><label>{{ $lang->rsv_adm_field_type }}</label><select name="field_type"><option value="text">{{ $lang->rsv_adm_type_text }}</option><option value="textarea">{{ $lang->rsv_adm_type_textarea }}</option><option value="select">{{ $lang->rsv_adm_type_select }}</option><option value="checkbox">{{ $lang->rsv_adm_type_checkbox }}</option><option value="tel">{{ $lang->rsv_adm_type_tel }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_required }}</label><select name="required"><option value="N">{{ $lang->rsv_adm_optional }}</option><option value="Y">{{ $lang->rsv_adm_required }}</option></select></div>
				<div style="min-width:150px">
					<label>{{ $lang->rsv_adm_target }}</label>
					<select name="resource_srl">
						<option value="0">{{ $lang->rsv_adm_all_common }}</option>
						@foreach ($resources_map as $srl => $r)
						<option value="{{ $srl }}">{{ $r->title }}</option>
						@endforeach
					</select>
				</div>
				<div><label>{{ $lang->rsv_adm_order }}</label><input type="number" name="list_order" value="0" style="width:60px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_add }}</button></div>
			</div>
			<div class="rsva-field" style="margin-top:10px;max-width:420px">
				<label>{{ $lang->rsv_adm_select_options }}</label>
				<textarea name="options" rows="3" placeholder="{{ $lang->rsv_adm_option }}1&#10;{{ $lang->rsv_adm_option }}2"></textarea>
			</div>
		</form>
	</div>
</div>
