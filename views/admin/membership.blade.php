@include('_tabs')

@php
$rsvm_money = function ($n) { return number_format((int)$n); };
$rsvm_coupon_map = [];
foreach ($coupons as $rsvm_c)
{
    $rsvm_coupon_map[(int)$rsvm_c->coupon_srl] = $rsvm_c;
}
@endphp

<div class="rsva">

	@if ($rsv_config->credit_enabled !== 'Y')
	<div class="rsva-panel" style="border-color:rgba(185,122,23,.35);background:#fdf9f2">
		<div style="font-size:13.5px">{!! $lang->rsv_adm_credit_off !!}</div>
	</div>
	@endif

	<div class="rsva-panel">
		<h3>{{ $lang->reservation_grade }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:#6b7684">{{ $lang->rsv_adm_grade_help }}</p>

		@if (empty($grades))
		<p class="rsva-empty">{{ $lang->rsv_adm_no_grades }}</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ $lang->rsv_adm_grade }}</th><th>{{ $lang->rsv_adm_min_spend }}</th><th>{{ $lang->rsv_adm_earn_rate }}</th><th>{{ $lang->rsv_adm_service_discount }}</th><th>{{ $lang->rsv_adm_grade_coupon }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($grades as $g)
				<tr>
					<td><b>{{ $g->title }}</b></td>
					<td>{{ sprintf($lang->rsv_adm_min_spend_format, $rsvm_money($g->min_spend)) }}</td>
					<td>{{ (float)$g->credit_rate > 0 ? $g->credit_rate . '%' : $lang->rsv_adm_default }}</td>
					<td>
						@if ($g->discount_type === 'amount')
						{{ sprintf($lang->reservation_price_format, $rsvm_money($g->discount_value)) }}
						@elseif ($g->discount_type === 'percent')
						{{ $g->discount_value }}%
						@else
						-
						@endif
					</td>
					<td>{{ isset($rsvm_coupon_map[(int)$g->coupon_srl]) ? $rsvm_coupon_map[(int)$g->coupon_srl]->title : '-' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteGrade" />
							<input type="hidden" name="grade_srl" value="{{ $g->grade_srl }}" />
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
			<input type="hidden" name="act" value="procReservationAdminInsertGrade" />
			<div class="rsva-inline">
				<div><label>{{ $lang->rsv_adm_grade_name }} *</label><input type="text" name="title" placeholder="{{ $lang->rsv_adm_ph_grade }}" required /></div>
				<div><label>{{ $lang->rsv_adm_min_spend }}</label><input type="number" name="min_spend" value="0" min="0" step="1000" /></div>
				<div><label>{{ $lang->rsv_adm_earn_rate }} %</label><input type="number" name="credit_rate" value="0" min="0" step="0.1" style="width:90px" /></div>
				<div><label>{{ $lang->rsv_adm_service_discount }}</label><select name="discount_type"><option value="">{{ $lang->rsv_adm_none }}</option><option value="percent">{{ $lang->rsv_adm_percent }}</option><option value="amount">{{ $lang->rsv_adm_fixed }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_discount_value }}</label><input type="number" name="discount_value" value="0" min="0" style="width:90px" /></div>
				<div style="min-width:150px">
					<label>{{ $lang->rsv_adm_grade_coupon }}</label>
					<select name="coupon_srl">
						<option value="0">{{ $lang->rsv_adm_none }}</option>
						@foreach ($coupons as $c)
						<option value="{{ $c->coupon_srl }}">{{ $c->title }}</option>
						@endforeach
					</select>
				</div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_add }}</button></div>
			</div>
		</form>
	</div>

	<div class="rsva-panel">
		<h3>{{ $lang->reservation_coupon }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:#6b7684">{{ $lang->rsv_adm_coupon_help }}</p>

		@if (empty($coupons))
		<p class="rsva-empty">{{ $lang->rsv_adm_no_coupons }}</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ $lang->rsv_adm_name }}</th><th>{{ $lang->rsv_adm_code }}</th><th>{{ $lang->rsv_adm_discount }}</th><th>{{ $lang->rsv_adm_min_amount }}</th><th>{{ $lang->rsv_settlement_period }}</th><th>{{ $lang->rsv_adm_used }}</th><th>{{ $lang->reservation_status }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($coupons as $c)
				<tr>
					<td><b>{{ $c->title }}</b>@if((int)$c->resource_srl > 0)<br /><small>{{ sprintf($lang->rsv_adm_only_for, $resources_map[(int)$c->resource_srl]->title ?? ('#' . $c->resource_srl)) }}</small>@endif</td>
					<td>{{ $c->code ? $c->code : '-' }}</td>
					<td>
						@if ($c->discount_type === 'percent')
						{{ (int)$c->discount_value }}%@if((int)$c->max_discount > 0) <small>({{ sprintf($lang->rsv_adm_max_format, $rsvm_money($c->max_discount)) }})</small>@endif
						@else
						{{ sprintf($lang->reservation_price_format, $rsvm_money($c->discount_value)) }}
						@endif
					</td>
					<td>{{ (int)$c->min_amount > 0 ? sprintf($lang->reservation_price_format, $rsvm_money($c->min_amount)) : '-' }}</td>
					<td>
						@if ($c->use_start || $c->use_end)
						<small>{{ $c->use_start ? zdate($c->use_start, 'Y-m-d') : '' }} ~ {{ $c->use_end ? zdate($c->use_end, 'Y-m-d') : '' }}</small>
						@else
						-
						@endif
					</td>
					<td>{{ (int)$c->used_count }}@if((int)$c->total_limit > 0) / {{ (int)$c->total_limit }}@endif</td>
					<td><span class="rsva-st @if($c->status === 'Y') rsva-st-confirmed @endif">{{ $c->status === 'Y' ? $lang->rsv_adm_use : $lang->rsv_adm_stop }}</span></td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ $lang->rsv_adm_confirm_delete }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteCoupon" />
							<input type="hidden" name="coupon_srl" value="{{ $c->coupon_srl }}" />
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
			<input type="hidden" name="act" value="procReservationAdminInsertCoupon" />
			<div class="rsva-inline">
				<div><label>{{ $lang->rsv_adm_name }} *</label><input type="text" name="title" placeholder="{{ $lang->rsv_adm_ph_coupon }}" required /></div>
				<div><label>{{ $lang->rsv_adm_code }}</label><input type="text" name="code" placeholder="WELCOME" style="width:120px" /></div>
				<div><label>{{ $lang->rsv_adm_discount_type }}</label><select name="discount_type"><option value="fixed">{{ $lang->rsv_adm_fixed }}</option><option value="percent">{{ $lang->rsv_adm_percent }}</option></select></div>
				<div><label>{{ $lang->rsv_adm_discount_value }} *</label><input type="number" name="discount_value" value="0" min="0" style="width:100px" required /></div>
				<div><label>{{ $lang->rsv_adm_max_discount }}</label><input type="number" name="max_discount" value="0" min="0" style="width:100px" /></div>
				<div><label>{{ $lang->rsv_adm_min_service_amount }}</label><input type="number" name="min_amount" value="0" min="0" style="width:110px" /></div>
			</div>
			<div class="rsva-inline" style="margin-top:12px">
				<div style="min-width:150px">
					<label>{{ $lang->rsv_adm_applies_service }}</label>
					<select name="resource_srl">
						<option value="0">{{ $lang->rsv_adm_all }}</option>
						@foreach ($resources_map as $srl => $r)
						<option value="{{ $srl }}">{{ $r->title }}</option>
						@endforeach
					</select>
				</div>
				<div><label>{{ $lang->rsv_adm_start_date }}</label><input type="date" name="use_start" /></div>
				<div><label>{{ $lang->rsv_adm_end_date }}</label><input type="date" name="use_end" /></div>
				<div><label>{{ $lang->rsv_adm_per_member }}</label><input type="number" name="per_member" value="1" min="1" style="width:80px" /></div>
				<div><label>{{ $lang->rsv_adm_total_limit }}</label><input type="number" name="total_limit" value="0" min="0" style="width:90px" /></div>
				<div><label>{{ $lang->reservation_status }}</label><select name="status"><option value="Y">{{ $lang->rsv_adm_use }}</option><option value="N">{{ $lang->rsv_adm_stop }}</option></select></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_add }}</button></div>
			</div>
		</form>
	</div>

	<div class="rsva-panel">
		<h3>{{ $lang->rsv_adm_manual_grant }}</h3>
		<div class="rsva-form-grid">
			<form action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminAdjustCredit" />
				<div class="rsva-field"><label>{{ $lang->rsv_adm_adjust_credit }}</label><input type="text" name="member_id" required /></div>
				<div class="rsva-inline">
					<div><label>{{ $lang->rsv_adm_amount_signed }}</label><input type="number" name="amount" value="0" step="1000" required /></div>
					<div style="flex:1"><label>{{ $lang->rsv_adm_memo }}</label><input type="text" name="memo" placeholder="{{ $lang->rsv_adm_ph_memo }}" /></div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_apply }}</button></div>
				</div>
			</form>

			<form action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminIssueCoupon" />
				<div class="rsva-field"><label>{{ $lang->rsv_adm_issue_coupon }}</label><input type="text" name="member_id" required /></div>
				<div class="rsva-inline">
					<div style="flex:1">
						<label>{{ $lang->reservation_coupon }}</label>
						<select name="coupon_srl" required>
							@foreach ($coupons as $c)
							<option value="{{ $c->coupon_srl }}">{{ $c->title }}</option>
							@endforeach
						</select>
					</div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ $lang->rsv_adm_issue }}</button></div>
				</div>
			</form>
		</div>
	</div>
</div>
