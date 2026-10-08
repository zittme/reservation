@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procReservationAdminInsertConfig" />

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_adm_basic }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ $lang->rsv_adm_booking_feature }}</label><select name="enabled"><option value="Y" @if($rsv_config->enabled === 'Y') selected @endif>{{ $lang->rsv_adm_use }}</option><option value="N" @if($rsv_config->enabled === 'N') selected @endif>{{ $lang->rsv_adm_stop }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_code_prefix }}</label><input type="text" name="code_prefix" maxlength="5" value="{{ $rsv_config->code_prefix }}" /></div>
				<div><label>{{ $lang->rsv_adm_allow_guest }}</label><select name="allow_guest"><option value="Y" @if($rsv_config->allow_guest === 'Y') selected @endif>{{ $lang->rsv_adm_allow }}</option><option value="N" @if($rsv_config->allow_guest === 'N') selected @endif>{{ $lang->rsv_adm_members_only }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_max_active }}</label><input type="number" name="max_active_per_member" min="0" max="100" value="{{ $rsv_config->max_active_per_member }}" /></div>
				<div><label>{{ $lang->rsv_adm_generate_days }}</label><input type="number" name="generate_days" min="7" max="366" value="{{ $rsv_config->generate_days }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_adm_credit_coupon }}</h3>
			<div class="rsva-form-grid">
				<div>
					<label>{{ $lang->reservation_credit_balance }}</label>
					<select name="credit_enabled"><option value="N">{{ $lang->rsv_adm_not_use }}</option><option value="Y" @if($rsv_config->credit_enabled === 'Y') selected @endif>{{ $lang->rsv_adm_use }}</option></select>
					<small>{{ $lang->rsv_adm_credit_help }}</small>
				</div>
				<div><label>{{ $lang->rsv_adm_credit_rate }}</label><input type="number" name="credit_rate" min="0" max="100" step="0.1" value="{{ $rsv_config->credit_rate }}" /><small>{{ $lang->rsv_adm_credit_rate_help }}</small></div>
				<div><label>{{ $lang->rsv_adm_credit_min_use }}</label><input type="number" name="credit_min_use" min="0" step="1" value="{{ $rsv_config->credit_min_use }}" /><small>{{ $lang->rsv_adm_zero_no_limit }}</small></div>
				<div><label>{{ $lang->rsv_adm_credit_max_rate }}</label><input type="number" name="credit_max_use_rate" min="0" max="100" step="1" value="{{ $rsv_config->credit_max_use_rate }}" /><small>{{ $lang->rsv_adm_credit_max_rate_help }}</small></div>
				<div>
					<label>{{ $lang->reservation_coupon }}</label>
					<select name="coupon_enabled"><option value="N">{{ $lang->rsv_adm_not_use }}</option><option value="Y" @if($rsv_config->coupon_enabled === 'Y') selected @endif>{{ $lang->rsv_adm_use }}</option></select>
				</div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_adm_payment }} {{ $pay_available ? '' : $lang->rsv_adm_pay_missing }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ $lang->rsv_adm_hold_minutes }}</label><input type="number" name="hold_minutes" min="3" max="120" value="{{ $rsv_config->hold_minutes }}" /></div>
				<div><label>{{ $lang->rsv_slot_unit }}</label><input type="number" name="slot_unit" min="5" max="120" step="5" value="{{ $rsv_config->slot_unit ?? 10 }}" /><small>{{ $lang->about_rsv_slot_unit }}</small></div>
				<div><label>{{ $lang->rsv_allow_any_staff }}</label><select name="allow_any_staff"><option value="Y" @if(($rsv_config->allow_any_staff ?? 'Y') === 'Y') selected @endif>{{ $lang->rsv_adm_allow }}</option><option value="N" @if(($rsv_config->allow_any_staff ?? 'Y') !== 'Y') selected @endif>{{ $lang->rsv_adm_not_use }}</option></select></div>
				<div style="grid-column:1/-1">
					<label>{{ $lang->rsv_adm_refund_policy }}</label>
					<textarea name="refund_policy" rows="3">{{ $rsv_config->refund_policy }}</textarea>
				</div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_adm_privacy }}</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1"><label>{{ $lang->rsv_adm_privacy_text }}</label><div class="zlf-row-wrap"><textarea name="privacy_text" rows="3" placeholder="{{ $lang->reservation_privacy_default }}">{{ $rsv_privacy_input }}</textarea>@include('_langfield', ['lf_name' => 'privacy_text', 'lf_value' => $rsv_config->privacy_text])</div></div>
				<div><label>{{ $lang->rsv_adm_privacy_version }}</label><input type="text" name="privacy_version" maxlength="20" value="{{ $rsv_config->privacy_version }}" /></div>
				<div><label>{{ $lang->rsv_adm_retention }}</label><input type="number" name="retention_days" min="0" max="3650" value="{{ $rsv_config->retention_days }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ $lang->rsv_adm_notify }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ $lang->rsv_adm_notify_admin }}</label><select name="notify_admin"><option value="N" @if($rsv_config->notify_admin === 'N') selected @endif>{{ $lang->rsv_adm_off }}</option><option value="Y" @if($rsv_config->notify_admin === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_notify_email }}</label><input type="email" name="notify_admin_email" value="{{ $rsv_config->notify_admin_email }}" /></div>
				<div><label>{{ $lang->rsv_notify_mail }}</label><select name="notify_mail"><option value="Y" @if(($rsv_config->notify_mail ?? 'N') === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option><option value="N" @if(($rsv_config->notify_mail ?? 'N') !== 'Y') selected @endif>{{ $lang->rsv_adm_off }}</option></select></div>
				<div><label>{{ $lang->rsv_notify_sms }}</label><select name="notify_sms"><option value="Y" @if(($rsv_config->notify_sms ?? 'N') === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option><option value="N" @if(($rsv_config->notify_sms ?? 'N') !== 'Y') selected @endif>{{ $lang->rsv_adm_off }}</option></select></div>
				<div><label>{{ $lang->rsv_sms_from }}</label><input type="text" name="sms_from" maxlength="20" value="{{ $rsv_config->sms_from ?? '' }}" /></div>
				<div><label>{{ $lang->rsv_notify_on_booked }}</label><select name="notify_on_booked"><option value="Y" @if(($rsv_config->notify_on_booked ?? 'N') === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option><option value="N" @if(($rsv_config->notify_on_booked ?? 'N') !== 'Y') selected @endif>{{ $lang->rsv_adm_off }}</option></select></div>
				<div><label>{{ $lang->rsv_notify_on_confirmed }}</label><select name="notify_on_confirmed"><option value="Y" @if(($rsv_config->notify_on_confirmed ?? 'N') === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option><option value="N" @if(($rsv_config->notify_on_confirmed ?? 'N') !== 'Y') selected @endif>{{ $lang->rsv_adm_off }}</option></select></div>
				<div><label>{{ $lang->rsv_notify_on_cancelled }}</label><select name="notify_on_cancelled"><option value="Y" @if(($rsv_config->notify_on_cancelled ?? 'N') === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option><option value="N" @if(($rsv_config->notify_on_cancelled ?? 'N') !== 'Y') selected @endif>{{ $lang->rsv_adm_off }}</option></select></div>
				<div><label>{{ $lang->rsv_notify_remind }}</label><select name="notify_remind"><option value="Y" @if(($rsv_config->notify_remind ?? 'N') === 'Y') selected @endif>{{ $lang->rsv_adm_on }}</option><option value="N" @if(($rsv_config->notify_remind ?? 'N') !== 'Y') selected @endif>{{ $lang->rsv_adm_off }}</option></select></div>
				<div><label>{{ $lang->rsv_remind_hours }}</label><input type="number" name="remind_hours" min="1" max="168" value="{{ $rsv_config->remind_hours ?? 24 }}" /><small>{{ $lang->about_rsv_remind_hours }}</small></div>
			</div>
		</div>

		<button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_save }}</button>
	</form>

	<div class="rsva-panel" style="margin-top:16px">
		<h3>{{ $lang->rsv_adm_skin_settings }}</h3>
		<form action="./" method="post" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
			<input type="hidden" name="module" value="reservation" />
			<input type="hidden" name="act" value="procReservationAdminUpdateSkin" />
			<div style="min-width:260px">
				<label>{{ $lang->rsv_adm_skin }}</label>
				<select name="skin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($rsv_instance->skin ?? '') === '/USE_DEFAULT/' || ($rsv_instance->skin ?? '') === '') selected @endif>{{ sprintf($lang->rsv_adm_skin_default, $rsv_default_skin) }}</option>
					@foreach ($rsv_skins as $sk_name => $sk)
					<option value="{{ $sk_name }}" @if(($rsv_instance->skin ?? '') === $sk_name) selected @endif>{{ $sk->title ?: $sk_name }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:260px">
				<label>{{ $lang->rsv_adm_layout_pc }}</label>
				<select name="layout_srl" style="width:100%">
					<option value="-1" @if((int)($rsv_instance->layout_srl ?? -1) === -1) selected @endif>{{ $lang->rsv_adm_layout_default }}</option>
					@foreach ($rsv_layouts as $lo)
					<option value="{{ $lo->layout_srl }}" @if((int)($rsv_instance->layout_srl ?? -1) === (int)$lo->layout_srl) selected @endif>{{ $lo->title ?: $lo->layout }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:260px">
				<label>{{ $lang->rsv_adm_layout_mobile }}</label>
				<select name="mlayout_srl" style="width:100%">
					<option value="-1" @if((int)($rsv_instance->mlayout_srl ?? -1) === -1) selected @endif>{{ $lang->rsv_adm_layout_default }}</option>
					<option value="-2" @if((int)($rsv_instance->mlayout_srl ?? -1) === -2) selected @endif>{{ $lang->rsv_adm_layout_same_pc }}</option>
					@foreach ($rsv_mlayouts as $lo)
					<option value="{{ $lo->layout_srl }}" @if((int)($rsv_instance->mlayout_srl ?? -1) === (int)$lo->layout_srl) selected @endif>{{ $lo->title ?: $lo->layout }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_save_display }}</button></div>
		</form>
		<p style="margin:8px 0 0;font-size:12.5px;color:#8a94a4">{{ $lang->rsv_adm_skin_help }}</p>
	</div>
</div>
