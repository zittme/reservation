@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procReservationAdminInsertConfig" />

		<div class="rsva-panel">
			<h3>기본</h3>
			<div class="rsva-form-grid">
				<div><label>예약 기능</label><select name="enabled"><option value="Y" @if($rsv_config->enabled === 'Y') selected @endif>사용</option><option value="N" @if($rsv_config->enabled === 'N') selected @endif>중지</option></select></div>
				<div><label>예약번호 접두사</label><input type="text" name="code_prefix" maxlength="5" value="{{ $rsv_config->code_prefix }}" /></div>
				<div><label>비회원 예약</label><select name="allow_guest"><option value="Y" @if($rsv_config->allow_guest === 'Y') selected @endif>허용</option><option value="N" @if($rsv_config->allow_guest === 'N') selected @endif>회원만</option></select></div>
				<div><label>1인 동시 예약 상한 (0=무제한)</label><input type="number" name="max_active_per_member" min="0" max="100" value="{{ $rsv_config->max_active_per_member }}" /></div>
				<div><label>슬롯 생성 범위 (일)</label><input type="number" name="generate_days" min="7" max="366" value="{{ $rsv_config->generate_days }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>결제 {{ $pay_available ? '' : '— 짓미 페이(zittme_pay)가 설치되어 있지 않아 유료 예약을 받을 수 없습니다' }}</h3>
			<div class="rsva-form-grid">
				<div><label>결제 대기(홀드) 시간 (분)</label><input type="number" name="hold_minutes" min="3" max="120" value="{{ $rsv_config->hold_minutes }}" /></div>
				<div style="grid-column:1/-1">
					<label>환불 규정 — 한 줄에 "일수:비율". 예) 3:100 → 3일 전까지 100% 환불</label>
					<textarea name="refund_policy" rows="3">{{ $rsv_config->refund_policy }}</textarea>
				</div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>개인정보·약관</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1"><label>수집 동의 문구</label><textarea name="privacy_text" rows="3">{{ $rsv_config->privacy_text }}</textarea></div>
				<div><label>문구 버전 (개정 시 올릴 것)</label><input type="text" name="privacy_version" maxlength="20" value="{{ $rsv_config->privacy_version }}" /></div>
				<div><label>예약 정보 보관 기간 (일, 0=무기한)</label><input type="number" name="retention_days" min="0" max="3650" value="{{ $rsv_config->retention_days }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>알림</h3>
			<div class="rsva-form-grid">
				<div><label>새 예약 시 관리자 메일</label><select name="notify_admin"><option value="N" @if($rsv_config->notify_admin === 'N') selected @endif>끔</option><option value="Y" @if($rsv_config->notify_admin === 'Y') selected @endif>켬</option></select></div>
				<div><label>관리자 메일 주소</label><input type="email" name="notify_admin_email" value="{{ $rsv_config->notify_admin_email }}" /></div>
			</div>
		</div>

		<button type="submit" class="rsva-btn rsva-btn-primary">저장</button>
	</form>
</div>
