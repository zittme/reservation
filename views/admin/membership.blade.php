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
		<div style="font-size:13.5px">적립금이 꺼져 있습니다. <b>설정</b> 화면에서 켜야 방문 완료 시 적립됩니다.</div>
	</div>
	@endif

	<div class="rsva-panel">
		<h3>단골 등급</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:#6b7684">방문 완료된 예약의 실제 지불액을 더해 등급을 정합니다. 기준 금액이 가장 높은 구간이 적용됩니다.</p>

		@if (empty($grades))
		<p class="rsva-empty">등급이 없습니다. 첫 등급을 만들어 보세요.</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>등급</th><th>기준 누적액</th><th>적립률</th><th>시술 할인</th><th>달성 쿠폰</th><th></th></tr></thead>
			<tbody>
				@foreach ($grades as $g)
				<tr>
					<td><b>{{ $g->title }}</b></td>
					<td>{{ $rsvm_money($g->min_spend) }}원 이상</td>
					<td>{{ (float)$g->credit_rate > 0 ? $g->credit_rate . '%' : '기본값' }}</td>
					<td>
						@if ($g->discount_type === 'amount')
						{{ $rsvm_money($g->discount_value) }}원
						@elseif ($g->discount_type === 'percent')
						{{ $g->discount_value }}%
						@else
						-
						@endif
					</td>
					<td>{{ isset($rsvm_coupon_map[(int)$g->coupon_srl]) ? $rsvm_coupon_map[(int)$g->coupon_srl]->title : '-' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteGrade" />
							<input type="hidden" name="grade_srl" value="{{ $g->grade_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
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
				<div><label>등급 이름 *</label><input type="text" name="title" placeholder="예: 골드" required /></div>
				<div><label>기준 누적액</label><input type="number" name="min_spend" value="0" min="0" step="1000" /></div>
				<div><label>적립률 %</label><input type="number" name="credit_rate" value="0" min="0" step="0.1" style="width:90px" /></div>
				<div><label>시술 할인</label><select name="discount_type"><option value="">없음</option><option value="percent">정률 %</option><option value="amount">정액 원</option></select></div>
				<div><label>할인값</label><input type="number" name="discount_value" value="0" min="0" style="width:90px" /></div>
				<div style="min-width:150px">
					<label>달성 쿠폰</label>
					<select name="coupon_srl">
						<option value="0">없음</option>
						@foreach ($coupons as $c)
						<option value="{{ $c->coupon_srl }}">{{ $c->title }}</option>
						@endforeach
					</select>
				</div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
			</div>
		</form>
	</div>

	<div class="rsva-panel">
		<h3>쿠폰</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:#6b7684">코드를 적어 둔 쿠폰은 손님이 예약서에서 코드를 입력해 씁니다. 코드가 없으면 관리자 발급이나 등급 달성으로만 지급됩니다.</p>

		@if (empty($coupons))
		<p class="rsva-empty">쿠폰이 없습니다.</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>이름</th><th>코드</th><th>할인</th><th>최소 금액</th><th>기간</th><th>사용</th><th>상태</th><th></th></tr></thead>
			<tbody>
				@foreach ($coupons as $c)
				<tr>
					<td><b>{{ $c->title }}</b>@if((int)$c->resource_srl > 0)<br /><small>{{ $resources_map[(int)$c->resource_srl]->title ?? ('#' . $c->resource_srl) }} 전용</small>@endif</td>
					<td>{{ $c->code ? $c->code : '-' }}</td>
					<td>
						@if ($c->discount_type === 'percent')
						{{ (int)$c->discount_value }}%@if((int)$c->max_discount > 0) <small>(최대 {{ $rsvm_money($c->max_discount) }}원)</small>@endif
						@else
						{{ $rsvm_money($c->discount_value) }}원
						@endif
					</td>
					<td>{{ (int)$c->min_amount > 0 ? $rsvm_money($c->min_amount) . '원' : '-' }}</td>
					<td>
						@if ($c->use_start || $c->use_end)
						<small>{{ $c->use_start ? zdate($c->use_start, 'Y-m-d') : '' }} ~ {{ $c->use_end ? zdate($c->use_end, 'Y-m-d') : '' }}</small>
						@else
						-
						@endif
					</td>
					<td>{{ (int)$c->used_count }}@if((int)$c->total_limit > 0) / {{ (int)$c->total_limit }}@endif</td>
					<td><span class="rsva-st @if($c->status === 'Y') rsva-st-confirmed @endif">{{ $c->status === 'Y' ? '사용' : '중지' }}</span></td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procReservationAdminDeleteCoupon" />
							<input type="hidden" name="coupon_srl" value="{{ $c->coupon_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
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
				<div><label>이름 *</label><input type="text" name="title" placeholder="예: 첫 방문 감사" required /></div>
				<div><label>코드</label><input type="text" name="code" placeholder="WELCOME" style="width:120px" /></div>
				<div><label>할인 방식</label><select name="discount_type"><option value="fixed">정액 원</option><option value="percent">정률 %</option></select></div>
				<div><label>할인값 *</label><input type="number" name="discount_value" value="0" min="0" style="width:100px" required /></div>
				<div><label>최대 할인액</label><input type="number" name="max_discount" value="0" min="0" style="width:100px" /></div>
				<div><label>최소 시술 금액</label><input type="number" name="min_amount" value="0" min="0" style="width:110px" /></div>
			</div>
			<div class="rsva-inline" style="margin-top:12px">
				<div style="min-width:150px">
					<label>적용 시술</label>
					<select name="resource_srl">
						<option value="0">전체</option>
						@foreach ($resources_map as $srl => $r)
						<option value="{{ $srl }}">{{ $r->title }}</option>
						@endforeach
					</select>
				</div>
				<div><label>시작일</label><input type="date" name="use_start" /></div>
				<div><label>종료일</label><input type="date" name="use_end" /></div>
				<div><label>1인당 횟수</label><input type="number" name="per_member" value="1" min="1" style="width:80px" /></div>
				<div><label>전체 한도</label><input type="number" name="total_limit" value="0" min="0" style="width:90px" /></div>
				<div><label>상태</label><select name="status"><option value="Y">사용</option><option value="N">중지</option></select></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
			</div>
		</form>
	</div>

	<div class="rsva-panel">
		<h3>수동 지급</h3>
		<div class="rsva-form-grid">
			<form action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminAdjustCredit" />
				<div class="rsva-field"><label>적립금 조정 — 회원 아이디 또는 회원번호</label><input type="text" name="member_id" required /></div>
				<div class="rsva-inline">
					<div><label>금액 (빼려면 음수)</label><input type="number" name="amount" value="0" step="1000" required /></div>
					<div style="flex:1"><label>메모</label><input type="text" name="memo" placeholder="예: 리뷰 감사 적립" /></div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">적용</button></div>
				</div>
			</form>

			<form action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procReservationAdminIssueCoupon" />
				<div class="rsva-field"><label>쿠폰 발급 — 회원 아이디 또는 회원번호</label><input type="text" name="member_id" required /></div>
				<div class="rsva-inline">
					<div style="flex:1">
						<label>쿠폰</label>
						<select name="coupon_srl" required>
							@foreach ($coupons as $c)
							<option value="{{ $c->coupon_srl }}">{{ $c->title }}</option>
							@endforeach
						</select>
					</div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">발급</button></div>
				</div>
			</form>
		</div>
	</div>
</div>
