<?php

$lang->reservation = '예약';

// 일반
$lang->cmd_reservation_book = '예약하기';
$lang->cmd_reservation_cancel = '예약 취소';
$lang->cmd_reservation_lookup = '예약 조회';
$lang->cmd_reservation_pay = '결제하기';
$lang->cmd_reservation_back_list = '목록으로';
$lang->reservation_my = '내 예약';
$lang->reservation_select_date = '날짜 선택';
$lang->reservation_select_time = '시간 선택';
$lang->reservation_remain = '잔여 %d';
$lang->reservation_full = '마감';
$lang->reservation_free = '무료';
$lang->reservation_person = '인원';
$lang->reservation_booker_info = '예약자 정보';
$lang->reservation_booker_name = '이름';
$lang->reservation_booker_phone = '연락처';
$lang->reservation_booker_email = '이메일';
$lang->reservation_guest_password = '조회 비밀번호';
$lang->reservation_guest_password_help = '비회원 예약 조회 시 사용할 비밀번호입니다. (4자 이상)';
$lang->reservation_agree_privacy = '개인정보 수집·이용에 동의합니다.';
$lang->reservation_booking_code = '예약번호';
$lang->reservation_status = '상태';
$lang->reservation_amount = '결제 금액';
$lang->reservation_date = '예약 일시';
$lang->reservation_no_resources = '현재 예약 가능한 항목이 없습니다.';
$lang->reservation_no_bookings = '예약 내역이 없습니다.';
$lang->reservation_guest_lookup_help = '예약번호와 예약 시 입력한 비밀번호를 입력해주세요.';

// 상태 표기
$lang->reservation_status_hold = '결제 대기';
$lang->reservation_status_pending = '입금 대기';
$lang->reservation_status_confirmed = '예약 확정';
$lang->reservation_status_cancelled = '취소됨';
$lang->reservation_status_noshow = '노쇼';
$lang->reservation_status_done = '이용 완료';
$lang->reservation_status_expired = '기한 만료';

// 메시지
$lang->msg_reservation_disabled = '예약 기능이 비활성화되어 있습니다.';
$lang->msg_reservation_no_resource = '예약 대상을 찾을 수 없습니다.';
$lang->msg_reservation_no_slot = '해당 시간대를 찾을 수 없습니다.';
$lang->msg_reservation_slot_full = '아쉽지만 방금 마감되었습니다. 다른 시간대를 선택해주세요.';
$lang->msg_reservation_too_late = '해당 시간대는 예약 가능 시간이 지났습니다.';
$lang->msg_reservation_login_required = '로그인 후 예약할 수 있습니다.';
$lang->msg_reservation_need_name = '예약자 이름을 입력해주세요.';
$lang->msg_reservation_need_phone = '연락처를 입력해주세요.';
$lang->msg_reservation_need_password = '조회 비밀번호를 4자 이상 입력해주세요.';
$lang->msg_reservation_need_agreement = '개인정보 수집·이용에 동의해주세요.';
$lang->msg_reservation_duplicate = '이미 해당 시간대에 예약하셨습니다.';
$lang->msg_reservation_too_many = '동시에 유지할 수 있는 예약 수를 초과했습니다.';
$lang->msg_reservation_field_required = '%s 항목을 입력해주세요.';
$lang->msg_reservation_pay_unavailable = '결제 기능을 사용할 수 없습니다. 관리자에게 문의해주세요.';
$lang->msg_reservation_pay_failed = '결제 준비 중 오류가 발생했습니다.';
$lang->msg_reservation_not_found = '예약을 찾을 수 없습니다.';
$lang->msg_reservation_not_yours = '본인의 예약만 볼 수 있습니다.';
$lang->msg_reservation_wrong_password = '비밀번호가 일치하지 않습니다.';
$lang->msg_reservation_not_cancellable = '취소할 수 없는 상태입니다.';
$lang->msg_reservation_cancel_deadline = '취소 가능 시간이 지났습니다.';
$lang->msg_reservation_refund_failed = '환불 처리 중 오류가 발생했습니다.';
$lang->msg_reservation_cancel_failed = '취소 처리 중 오류가 발생했습니다.';
$lang->msg_reservation_cancelled = '예약이 취소되었습니다.';
$lang->msg_reservation_cancel_reason = '예약 취소';
$lang->msg_reservation_resource_closed = '예약 이력이 있어 삭제 대신 비공개로 전환했습니다.';

// 관리자
$lang->rsv_tab_dashboard = '대시보드';
$lang->rsv_tab_bookings = '예약 관리';
$lang->rsv_tab_resources = '예약상품 관리';
$lang->rsv_tab_schedule = '운영 일정';
$lang->rsv_tab_forms = '추가 문항';
$lang->rsv_tab_stats = '통계';
$lang->rsv_tab_config = '설정';
$lang->reservation_price_format = '%s원';
$lang->reservation_minutes = '%d분';
$lang->reservation_prev_month = '이전 달';
$lang->reservation_next_month = '다음 달';
$lang->reservation_dow = '일,월,화,수,목,금,토';

// 담당자
$lang->rsv_tab_staff = '담당자';
$lang->rsv_staff = '담당자';
$lang->rsv_staff_add = '담당자 추가';
$lang->rsv_staff_name = '이름';
$lang->rsv_staff_position = '직급';
$lang->rsv_staff_member = '연결 회원 아이디';
$lang->about_rsv_staff_member = '적어 두면 그 회원이 로그인해 자기 예약과 정산만 볼 수 있습니다. 비워 두면 연결하지 않습니다.';
$lang->rsv_staff_share_rate = '기본 배분율(%)';
$lang->about_rsv_staff_share_rate = '이 담당자의 매출에서 담당자 몫이 되는 비율입니다. 시술마다 다르게 두려면 아래에서 따로 적습니다.';
$lang->rsv_staff_summary = '한 줄 소개';
$lang->rsv_staff_content = '소개';
$lang->rsv_staff_thumb = '사진 주소';
$lang->rsv_staff_services = '맡는 시술';
$lang->about_rsv_staff_services = '체크한 시술만 이 담당자로 예약됩니다. 값과 소요시간과 배분율을 비워 두면 시술 기본값을 씁니다.';
$lang->rsv_staff_schedule = '근무 요일';
$lang->about_rsv_staff_schedule = '체크한 요일의 시간대에만 예약을 받습니다. 개인 휴무는 운영 일정에서 담당자를 골라 넣습니다.';
$lang->rsv_staff_service_count = '맡는 시술';
$lang->rsv_staff_none = '등록한 담당자가 없습니다.';
$lang->rsv_staff_status_active = '노출';
$lang->rsv_staff_status_hidden = '숨김';
$lang->confirm_rsv_staff_delete = '이 담당자를 지웁니다. 예약이 걸려 있으면 지우지 않고 숨김으로 바꿉니다. 계속할까요?';

// 정산
$lang->rsv_tab_settlements = '정산';
$lang->rsv_settlement = '정산';
$lang->rsv_settlement_build = '정산 만들기';
$lang->rsv_settlement_period = '기간';
$lang->rsv_settlement_count = '건수';
$lang->rsv_settlement_gross = '매출';
$lang->rsv_settlement_share = '담당자 몫';
$lang->rsv_settlement_store = '매장 몫';
$lang->rsv_settlement_none = '정산 회차가 없습니다.';
$lang->rsv_settlement_status_draft = '집계중';
$lang->rsv_settlement_status_confirmed = '확정';
$lang->rsv_settlement_status_paid = '지급완료';
$lang->rsv_settlement_confirm = '확정하기';
$lang->rsv_settlement_pay = '지급완료로';
$lang->rsv_settlement_delete = '회차 지우기';
$lang->about_rsv_settlement = '이용을 마친 예약만 집계합니다. 취소와 노쇼는 매출로 잡지 않습니다. 금액과 배분율은 예약 시점 값을 그대로 씁니다.';
$lang->confirm_rsv_settlement_confirm = '이 회차를 확정합니다. 확정한 뒤에는 금액이 바뀌지 않습니다. 계속할까요?';
$lang->confirm_rsv_settlement_delete = '집계중인 회차를 지웁니다. 물린 예약은 다시 정산 대상으로 돌아갑니다. 계속할까요?';
$lang->rsv_settlement_item_date = '이용일';
$lang->rsv_settlement_item_service = '시술';
$lang->rsv_settlement_item_amount = '금액';
$lang->rsv_settlement_item_rate = '배분율';
$lang->rsv_settlement_item_share = '담당자 몫';

// 예약상품 - 점유와 결제 방식
$lang->rsv_booking_mode = '점유 방식';
$lang->rsv_booking_mode_slot = '정원제 슬롯';
$lang->rsv_booking_mode_staff = '담당자 지정';
$lang->about_rsv_booking_mode = '정원제 슬롯은 미리 찍어 둔 시간표를 여럿이 나눠 씁니다. 담당자 지정은 담당자의 빈 시간을 계산해 잡으며, 시술마다 소요시간이 다른 곳에 맞습니다.';
$lang->rsv_category = '분류';
$lang->about_rsv_category = '컷, 펌처럼 묶어 보여 줄 이름입니다.';
$lang->rsv_pay_mode = '결제 방식';
$lang->rsv_pay_mode_none = '결제 없음';
$lang->rsv_pay_mode_deposit = '예약금만';
$lang->rsv_pay_mode_full = '전액 선결제';
$lang->rsv_deposit_amount = '예약금';
$lang->about_rsv_pay_mode = '예약금만 받으면 나머지는 방문했을 때 받습니다. 결제 없음이면 값만 안내하고 받지 않습니다.';

// 알림
$lang->rsv_notify_mail = '메일 알림';
$lang->rsv_notify_sms = '문자 알림';
$lang->rsv_notify_alimtalk = '알림톡';
$lang->about_rsv_notify_alimtalk = '발송기를 꽂기 전까지는 문자로 나갑니다.';
$lang->rsv_notify_on_booked = '예약 접수 알림';
$lang->rsv_notify_on_confirmed = '예약 확정 알림';
$lang->rsv_notify_on_cancelled = '예약 취소 알림';
$lang->rsv_notify_remind = '방문 전 알림';
$lang->rsv_remind_hours = '방문 몇 시간 전';
$lang->rsv_sms_from = '문자 발신번호';
$lang->about_rsv_sms_from = '비워 두면 사이트 기본 발신번호를 씁니다.';
$lang->rsv_slot_unit = '시간 단위(분)';
$lang->about_rsv_slot_unit = '담당자 지정 방식에서 예약 시각을 이 간격으로 보여 줍니다.';
$lang->rsv_allow_any_staff = '담당자 지정 없이 예약 허용';
$lang->notify_subject_booked = '[{site}] {service} 예약이 접수되었습니다';
$lang->notify_body_booked = '{name} 님, {when} {service} 예약이 접수되었습니다. 담당 {staff}. 예약번호 {code}';
$lang->notify_subject_confirmed = '[{site}] {service} 예약이 확정되었습니다';
$lang->notify_body_confirmed = '{name} 님, {when} {service} 예약이 확정되었습니다. 담당 {staff}. 예약번호 {code}';
$lang->notify_subject_cancelled = '[{site}] {service} 예약이 취소되었습니다';
$lang->notify_body_cancelled = '{name} 님, {when} {service} 예약이 취소되었습니다. 예약번호 {code}';
$lang->notify_subject_remind = '[{site}] 내일 {service} 예약이 있습니다';
$lang->notify_body_remind = '{name} 님, {when} {service} 예약을 잊지 마세요. 담당 {staff}. 예약번호 {code}';

// 알림 문구 안내
$lang->msg_reservation_no_staff = '담당자를 찾을 수 없습니다.';
$lang->msg_reservation_staff_no_service = '이 담당자는 그 시술을 맡지 않습니다.';
$lang->msg_reservation_need_staff = '담당자를 골라 주세요.';
$lang->msg_reservation_staff_save_failed = '담당자를 저장하지 못했습니다.';
$lang->msg_reservation_no_settlement = '정산 회차를 찾을 수 없습니다.';
$lang->msg_reservation_settlement_empty = '그 기간에 정산할 예약이 없습니다.';
$lang->msg_reservation_settlement_locked = '확정한 회차는 바꿀 수 없습니다.';

// 지점 연동
$lang->rsv_branch = '지점';
$lang->rsv_branch_pick = '어느 지점으로 가시나요';
$lang->rsv_branch_any = '지점 무관';
$lang->about_rsv_staff_branch = '이 담당자가 일하는 지점입니다. 손님이 지점을 고르면 그 지점 담당자만 보입니다.';
$lang->msg_reservation_staff_other_branch = '그 담당자는 고르신 지점 소속이 아닙니다.';
$lang->rsv_tab_membership = '단골 관리';
$lang->msg_reservation_coupon_invalid = '유효하지 않은 쿠폰입니다.';
$lang->msg_reservation_coupon_not_applicable = '이 예약에는 쓸 수 없는 쿠폰입니다.';
$lang->msg_reservation_coupon_used = '이미 사용한 쿠폰입니다.';
$lang->msg_reservation_coupon_soldout = '쿠폰이 모두 소진되었습니다.';
$lang->msg_reservation_coupon_taken = '다른 예약에 먼저 사용된 쿠폰입니다.';
$lang->msg_reservation_credit_too_much = '쓸 수 있는 적립금보다 많습니다.';
$lang->msg_reservation_credit_short = '적립금이 모자랍니다.';
$lang->msg_reservation_no_member = '회원을 찾을 수 없습니다.';
$lang->msg_reservation_need_title = '이름을 입력해 주세요.';
$lang->reservation_benefit = '혜택';
$lang->reservation_grade_discount = '등급 할인';
$lang->reservation_coupon = '쿠폰';
$lang->reservation_coupon_none = '쿠폰 없이 예약';
$lang->reservation_coupon_code = '쿠폰 코드';
$lang->reservation_credit = '적립금 사용';
$lang->reservation_credit_usable = '최대 %s원 사용 가능 (보유 %s원)';
$lang->reservation_credit_balance = '적립금';
$lang->reservation_grade = '단골 등급';
$lang->reservation_credit_earn = '적립';
$lang->reservation_credit_spend = '사용';
$lang->reservation_credit_refund = '환불';
$lang->reservation_credit_earn_cancel = '적립 취소';
$lang->reservation_credit_admin = '관리자 조정';
$lang->reservation_my_coupons = '내 쿠폰';
