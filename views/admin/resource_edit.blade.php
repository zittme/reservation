@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ $resource ? $lang->rsv_adm_edit_resource : $lang->rsv_adm_new_resource }}</h3>
		<form action="{{ getUrl('') }}" method="post" enctype="multipart/form-data">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminInsertResource" />
			@if ($resource)
			<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
			@endif

			{{-- 대표 이미지 (정사각형으로 노출) --}}
			{{-- 중괄호 보간은 템플릿 컴파일러와 충돌한다 — 반드시 문자열 연결로 --}}
			@php $rsva_thumb_style = !empty($resource->thumb) ? "background-image:url('" . $resource->thumb . "');" : ''; @endphp
			<div class="rsva-field" style="display:flex;gap:14px;align-items:flex-start;margin-bottom:16px">
				<div style="flex:none;width:96px;height:96px;border:1px solid #e5e8ee;border-radius:12px;background:#f7f8fa center/cover no-repeat;{{ $rsva_thumb_style }}"></div>
				<div>
					<label>{{ $lang->rsv_adm_thumb }}</label>
					<input type="file" name="thumb_file" accept="image/*" />
					@if (!empty($resource->thumb))
					<label style="display:inline-flex;align-items:center;gap:5px;margin-top:6px;font-weight:500"><input type="checkbox" name="thumb_delete" value="Y" /> {{ $lang->rsv_adm_thumb_delete }}</label>
					@endif
					<small style="display:block;margin-top:4px;color:#6b7684">{{ $lang->rsv_adm_thumb_help }}</small>
				</div>
			</div>

			<div class="rsva-form-grid">
				<div><label>{{ $lang->rsv_adm_name }} *</label><div class="zlf-row-wrap"><input type="text" name="title" required value="{{ $resource->title ?? '' }}" />@include('_langfield', ['lf_name' => 'title', 'lf_value' => $resource->title_raw ?? ''])</div></div>
				<div><label>{{ $lang->rsv_adm_summary }}</label><div class="zlf-row-wrap"><input type="text" name="summary" value="{{ $resource->summary ?? '' }}" />@include('_langfield', ['lf_name' => 'summary', 'lf_value' => $resource->summary_raw ?? ''])</div></div>
				<div><label>{{ $lang->rsv_adm_capacity_default }}</label><input type="number" name="capacity_default" min="1" max="1000" value="{{ $resource->capacity_default ?? 1 }}" /></div>
				<div><label>{{ $lang->rsv_adm_duration_min }}</label><input type="number" name="duration" min="5" max="1440" step="5" value="{{ $resource->duration ?? 60 }}" /></div>
				<div><label>{{ $lang->rsv_adm_price_free }}</label><input type="number" name="price" min="0" value="{{ $resource->price ?? 0 }}" /></div>
				<div><label>{{ $lang->rsv_booking_mode }}</label><select name="booking_mode"><option value="slot" @if(($resource->booking_mode ?? 'slot') !== 'staff') selected @endif>{{ $lang->rsv_booking_mode_slot }}</option><option value="staff" @if(($resource->booking_mode ?? '') === 'staff') selected @endif>{{ $lang->rsv_booking_mode_staff }}</option></select></div>
				<div><label>{{ $lang->rsv_category }}</label><div class="zlf-row-wrap"><input type="text" name="category" maxlength="100" value="{{ $resource->category ?? '' }}" />@include('_langfield', ['lf_name' => 'category', 'lf_value' => $resource->category_raw ?? ''])</div></div>
				<div><label>{{ $lang->rsv_pay_mode }}</label><select name="pay_mode">@foreach(['none', 'deposit', 'full'] as $rsv_pm)<option value="{{ $rsv_pm }}" @if(($resource->pay_mode ?? 'none') === $rsv_pm) selected @endif>{{ $lang->{'rsv_pay_mode_' . $rsv_pm} }}</option>@endforeach</select></div>
				<div><label>{{ $lang->rsv_deposit_amount }}</label><input type="number" name="deposit_amount" min="0" value="{{ $resource->deposit_amount ?? 0 }}" /></div>
				<div><label>{{ $lang->rsv_adm_buffer_before }}</label><input type="number" name="buffer_before" min="0" max="240" value="{{ $resource->buffer_before ?? 0 }}" /></div>
				<div><label>{{ $lang->rsv_adm_buffer_after }}</label><input type="number" name="buffer_after" min="0" max="240" value="{{ $resource->buffer_after ?? 0 }}" /></div>
				<div><label>{{ $lang->rsv_adm_max_advance }}</label><input type="number" name="max_advance_days" min="1" max="366" value="{{ $resource->max_advance_days ?? 90 }}" /></div>
				<div><label>{{ $lang->rsv_adm_min_lead }}</label><input type="number" name="min_lead_minutes" min="0" max="10080" value="{{ $resource->min_lead_minutes ?? 60 }}" /></div>
				<div><label>{{ $lang->rsv_adm_cancel_deadline }}</label><input type="number" name="cancel_deadline_hours" min="0" max="720" value="{{ $resource->cancel_deadline_hours ?? 24 }}" /></div>
				<div><label>{{ $lang->reservation_status }}</label><select name="status"><option value="open" @if(($resource->status ?? 'open') === 'open') selected @endif>{{ $lang->rsv_adm_public }}</option><option value="closed" @if(($resource->status ?? '') === 'closed') selected @endif>{{ $lang->rsv_adm_private }}</option></select></div>
			</div>
			<div style="margin-top:14px">
				<button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_save }}</button>
				<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResources') }}" class="rsva-btn">{{ $lang->rsv_adm_list }}</a>
			</div>
		</form>
	</div>

	@if ($resource)
	{{-- 운영 규칙 — 리소스와 한 화면에서 관리 --}}
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_hours_rules }}</h3>
		@if (empty($rules))
		<p class="rsva-empty">{{ $lang->rsv_adm_no_rules }}</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ $lang->rsv_adm_weekday }}</th><th>{{ $lang->rsv_adm_time }}</th><th>{{ $lang->rsv_adm_interval }}</th><th>{{ $lang->rsv_adm_capacity }}</th><th>{{ $lang->rsv_adm_valid_period }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($rules as $rule)
				<tr>
					<td>{{ explode(',', $lang->reservation_dow)[(int)$rule->weekday] ?? '' }}</td>
					<td>{{ $rule->start_time }} ~ {{ $rule->end_time }}</td>
					<td>{{ sprintf($lang->reservation_minutes, $rule->interval_minutes) }}</td>
					<td>{{ $rule->capacity > 0 ? $rule->capacity : $lang->rsv_adm_default }}</td>
					<td>{{ $rule->valid_from ?: '~' }} - {{ $rule->valid_to ?: '~' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteRule" />
							<input type="hidden" name="rule_srl" value="{{ $rule->rule_srl }}" />
							<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
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
			<input type="hidden" name="act" value="procReservationAdminInsertRule" />
			<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
			<div class="rsva-field">
				<label>{{ $lang->rsv_adm_weekdays_multi }}</label>
				<div class="rsva-weekdays">
					@foreach (explode(',', $lang->reservation_dow) as $i => $d)
					<label><input type="checkbox" name="weekday[]" value="{{ $i }}" @if($i >= 1 && $i <= 5) checked @endif /> {{ $d }}</label>
					@endforeach
				</div>
			</div>
			<div class="rsva-inline">
				<div><label>{{ $lang->rsv_adm_start }}</label><input type="time" name="start_time" value="09:00" required /></div>
				<div><label>{{ $lang->rsv_adm_end }}</label><input type="time" name="end_time" value="18:00" required /></div>
				<div><label>{{ $lang->rsv_adm_slot_interval }}</label><input type="number" name="interval_minutes" min="5" max="1440" step="5" placeholder="{{ $resource->duration }}" style="width:90px" /></div>
				<div><label>{{ $lang->rsv_adm_slot_capacity }}</label><input type="number" name="capacity" min="0" max="1000" placeholder="{{ $resource->capacity_default }}" style="width:90px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_add_rule }}</button></div>
			</div>
			<p style="margin:10px 0 0;font-size:12px;color:#6b7684;line-height:1.7">
				{!! sprintf($lang->rsv_adm_rules_help, (int)$resource->duration, (int)$resource->capacity_default) !!}
			</p>
		</form>
	</div>

	{{-- 이 리소스의 휴무 --}}
	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_holidays_this }}</h3>
		@if (!empty($holidays))
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ $lang->rsv_adm_date }}</th><th>{{ $lang->rsv_adm_type }}</th><th>{{ $lang->rsv_adm_time }}</th><th>{{ $lang->rsv_adm_reason }}</th><th>{{ $lang->rsv_adm_target }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($holidays as $h)
				<tr>
					<td>{{ substr($h->holiday_date,0,4) }}.{{ substr($h->holiday_date,4,2) }}.{{ substr($h->holiday_date,6,2) }}</td>
					<td>{{ $h->holiday_type === 'extra' ? $lang->rsv_adm_extra_open : $lang->rsv_adm_closed }}</td>
					<td>{{ $h->start_time ? $h->start_time . ' ~ ' . $h->end_time : $lang->rsv_adm_all_day }}</td>
					<td>{{ $h->reason ?: '-' }}</td>
					<td>{{ (int)$h->resource_srl === 0 ? $lang->rsv_adm_all_common : $lang->rsv_adm_this_resource }}</td>
					<td>
						@if ((int)$h->resource_srl !== 0)
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteHoliday" />
							<input type="hidden" name="holiday_srl" value="{{ $h->holiday_srl }}" />
							<input type="hidden" name="success_return_url" value="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource->resource_srl) }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ $lang->rsv_adm_delete }}</button>
						</form>
						@endif
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif

		<form action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procReservationAdminInsertHoliday" />
			<input type="hidden" name="resource_srl" value="{{ $resource->resource_srl }}" />
			<input type="hidden" name="success_return_url" value="{{ getUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource->resource_srl) }}" />
			<div class="rsva-inline">
				<div><label>{{ $lang->rsv_adm_date }}</label><input type="date" name="holiday_date" required /></div>
				<div><label>{{ $lang->rsv_adm_type }}</label><select name="holiday_type"><option value="closed">{{ $lang->rsv_adm_closed }}</option><option value="extra">{{ $lang->rsv_adm_extra_open }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_start_opt }}</label><input type="time" name="start_time" /></div>
				<div><label>{{ $lang->rsv_adm_end_opt }}</label><input type="time" name="end_time" /></div>
				<div><label>{{ $lang->rsv_adm_reason }}</label><input type="text" name="reason" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_add }}</button></div>
			</div>
		</form>
	</div>
	@endif
</div>
