@include('_tabs')
@include('_langfield_assets')

@php
$st_srl = $staff ? (int)$staff->staff_srl : 0;
$st_rate = $staff ? number_format((int)$staff->share_rate / 100, 1, '.', '') : '';
$st_days = explode(',', $lang->reservation_dow);
@endphp

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procReservationAdminInsertStaff" />
		<input type="hidden" name="staff_srl" value="{{ $st_srl }}" />

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_adm_basic_info }}</h3>
			<div class="rsva-form-grid">
				<div>
					<label>{{ $lang->rsv_staff_name }}</label>
					<div class="zlf-row-wrap"><input type="text" name="name" value="{{ $staff->name ?? '' }}" required />@include('_langfield', ['lf_name' => 'name', 'lf_value' => $staff->name_raw ?? ''])</div>
				</div>
				<div>
					<label>{{ $lang->rsv_staff_position }}</label>
					<div class="zlf-row-wrap"><input type="text" name="position" value="{{ $staff->position ?? '' }}" placeholder="{{ $lang->rsv_adm_ph_position }}" />@include('_langfield', ['lf_name' => 'position', 'lf_value' => $staff->position_raw ?? ''])</div>
				</div>
				<div>
					<label>{{ $lang->rsv_staff_share_rate }}</label>
					<input type="text" name="share_rate" value="{{ $st_rate }}" placeholder="45" />
				</div>
				<div>
					<label>{{ $lang->rsv_adm_display_order }}</label>
					<input type="number" name="list_order" value="{{ (int)($staff->list_order ?? 0) }}" />
				</div>
				<div>
					<label>{{ $lang->reservation_status }}</label>
					<select name="status">
						<option value="active" @if(($staff->status ?? 'active') === 'active') selected="selected" @endif>{{ $lang->rsv_staff_status_active }}</option>
						<option value="hidden" @if(($staff->status ?? '') === 'hidden') selected="selected" @endif>{{ $lang->rsv_staff_status_hidden }}</option>
					</select>
				</div>
				<div>
					<label>{{ $lang->rsv_staff_thumb }}</label>
					<input type="text" name="thumb" value="{{ $staff->thumb ?? '' }}" />
				</div>
				@if (count($branches))
				<div>
					<label>{{ $lang->rsv_adm_branch }}</label>
					<select name="branch_srl">
						<option value="0">{{ $lang->rsv_branch_any }}</option>
						@foreach ($branches as $b)
						<option value="{{ (int)$b->branch_srl }}" @if((int)($staff->branch_srl ?? 0) === (int)$b->branch_srl) selected="selected" @endif>{{ $b->name }}</option>
						@endforeach
					</select>
					<small style="color:#8b95a1">{{ $lang->rsv_adm_branch_help }}</small>
				</div>
				@endif
			</div>

			<div class="rsva-field" style="margin-top:14px">
				<label>{{ $lang->rsv_staff_summary }}</label>
				<div class="zlf-row-wrap"><input type="text" name="summary" value="{{ $staff->summary ?? '' }}" />@include('_langfield', ['lf_name' => 'summary', 'lf_value' => $staff->summary_raw ?? ''])</div>
			</div>
			<div class="rsva-field">
				<label>{{ $lang->rsv_staff_content }}</label>
				<div class="zlf-row-wrap"><textarea name="content" rows="4">{{ $staff->content ?? '' }}</textarea>@include('_langfield', ['lf_name' => 'content', 'lf_value' => $staff->content_raw ?? ''])</div>
			</div>
			<div class="rsva-field">
				<label>{{ $lang->rsv_staff_member }}</label>
				<input type="text" name="member_id" value="{{ $staff_member_id ?? '' }}" placeholder="{{ $lang->rsv_adm_ph_member }}" />
				<small style="color:#8b95a1">{{ $lang->rsv_adm_member_help }}</small>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_staff_services }}</h3>
			<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">{{ $lang->about_rsv_staff_services }}</p>

			@if (empty($resources))
			<p class="rsva-empty">{{ $lang->rsv_adm_add_resource_first }}</p>
			@else
			<table class="rsva-table">
				<thead><tr><th style="width:34%">{{ $lang->rsv_settlement_item_service }}</th><th>{{ $lang->rsv_adm_price_krw }}</th><th>{{ $lang->rsv_adm_time_needed }}</th><th>{{ $lang->rsv_adm_share_pct }}</th></tr></thead>
				<tbody>
					@foreach ($resources as $r)
					@php
					$r_srl = (int)$r->resource_srl;
					$r_map = $service_map[$r_srl] ?? null;
					$r_price = ($r_map && (int)$r_map->price >= 0) ? (int)$r_map->price : '';
					$r_dur = ($r_map && (int)$r_map->duration > 0) ? (int)$r_map->duration : '';
					$r_rate = ($r_map && (int)$r_map->share_rate >= 0) ? number_format((int)$r_map->share_rate / 100, 1, '.', '') : '';
					@endphp
					<tr>
						<td>
							<label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:600">
								<input type="checkbox" name="svc_use[]" value="{{ $r_srl }}" @if($r_map) checked="checked" @endif />
								{{ $r->title }}
							</label>
							<small style="color:#9aa1ab">{{ sprintf($lang->rsv_adm_service_default, number_format((int)$r->price), (int)$r->duration) }}</small>
						</td>
						<td><input type="text" name="svc_price[{{ $r_srl }}]" value="{{ $r_price }}" placeholder="{{ (int)$r->price }}" /></td>
						<td><input type="number" name="svc_duration[{{ $r_srl }}]" value="{{ $r_dur }}" placeholder="{{ (int)$r->duration }}" /></td>
						<td><input type="text" name="svc_rate[{{ $r_srl }}]" value="{{ $r_rate }}" placeholder="{{ $st_rate }}" /></td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@endif
		</div>

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_staff_schedule }}</h3>
			<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">{{ $lang->about_rsv_staff_schedule }}</p>

			<table class="rsva-table">
				<thead><tr><th style="width:120px">{{ $lang->rsv_adm_weekday }}</th><th>{{ $lang->rsv_adm_start }}</th><th>{{ $lang->rsv_adm_end }}</th></tr></thead>
				<tbody>
					@foreach ($st_days as $wd => $wd_label)
					@php
					$wd_rule = isset($schedules[$wd]) ? $schedules[$wd][0] : null;
					@endphp
					<tr>
						<td>
							<label style="display:flex;align-items:center;gap:8px;margin:0;font-weight:600">
								<input type="checkbox" name="wd_use[]" value="{{ $wd }}" @if($wd_rule) checked="checked" @endif />
								{{ $wd_label }}
							</label>
						</td>
						<td><input type="time" name="wd_start[{{ $wd }}]" value="{{ $wd_rule->start_time ?? '10:00' }}" /></td>
						<td><input type="time" name="wd_end[{{ $wd }}]" value="{{ $wd_rule->end_time ?? '20:00' }}" /></td>
					</tr>
					@endforeach
				</tbody>
			</table>
		</div>

		<div style="text-align:right">
			<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaff') }}" class="rsva-btn">{{ $lang->rsv_adm_list }}</a>
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_save }}</button>
		</div>
	</form>
</div>
