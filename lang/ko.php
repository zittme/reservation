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
