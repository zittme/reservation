<?php

$lang->reservation = 'Reservation';

// General
$lang->cmd_reservation_book = 'Book now';
$lang->cmd_reservation_cancel = 'Cancel booking';
$lang->cmd_reservation_lookup = 'Find booking';
$lang->cmd_reservation_pay = 'Pay';
$lang->cmd_reservation_back_list = 'Back to list';
$lang->reservation_my = 'My bookings';
$lang->reservation_select_date = 'Select a date';
$lang->reservation_select_time = 'Select a time';
$lang->reservation_remain = '%d left';
$lang->reservation_full = 'Full';
$lang->reservation_free = 'Free';
$lang->reservation_person = 'People';
$lang->reservation_booker_info = 'Booker information';
$lang->reservation_booker_name = 'Name';
$lang->reservation_booker_phone = 'Phone';
$lang->reservation_booker_email = 'Email';
$lang->reservation_guest_password = 'Lookup password';
$lang->reservation_guest_password_help = 'Password for finding your booking as a guest (4+ characters).';
$lang->reservation_agree_privacy = 'I agree to the collection and use of personal information.';
$lang->reservation_booking_code = 'Booking code';
$lang->reservation_status = 'Status';
$lang->reservation_amount = 'Amount';
$lang->reservation_date = 'Date & time';
$lang->reservation_no_resources = 'Nothing is available for booking right now.';
$lang->reservation_no_bookings = 'No bookings yet.';
$lang->reservation_guest_lookup_help = 'Enter your booking code and the password you set.';

// Status labels
$lang->reservation_status_hold = 'Awaiting payment';
$lang->reservation_status_pending = 'Awaiting deposit';
$lang->reservation_status_confirmed = 'Confirmed';
$lang->reservation_status_cancelled = 'Cancelled';
$lang->reservation_status_noshow = 'No-show';
$lang->reservation_status_done = 'Completed';
$lang->reservation_status_expired = 'Expired';

// Messages
$lang->msg_reservation_disabled = 'Reservations are disabled.';
$lang->msg_reservation_no_resource = 'Resource not found.';
$lang->msg_reservation_no_slot = 'Time slot not found.';
$lang->msg_reservation_slot_full = 'Sorry, this slot has just been filled. Please pick another time.';
$lang->msg_reservation_too_late = 'This slot is no longer available for booking.';
$lang->msg_reservation_login_required = 'Please log in to book.';
$lang->msg_reservation_need_name = 'Please enter your name.';
$lang->msg_reservation_need_phone = 'Please enter your phone number.';
$lang->msg_reservation_need_password = 'Please enter a lookup password (4+ characters).';
$lang->msg_reservation_need_agreement = 'Please agree to the privacy terms.';
$lang->msg_reservation_duplicate = 'You already have a booking for this slot.';
$lang->msg_reservation_too_many = 'You have reached the maximum number of active bookings.';
$lang->msg_reservation_field_required = 'Please fill in: %s';
$lang->msg_reservation_pay_unavailable = 'Payment is currently unavailable. Please contact the administrator.';
$lang->msg_reservation_pay_failed = 'Failed to prepare the payment.';
$lang->msg_reservation_not_found = 'Booking not found.';
$lang->msg_reservation_not_yours = 'You can only view your own bookings.';
$lang->msg_reservation_wrong_password = 'The password does not match.';
$lang->msg_reservation_not_cancellable = 'This booking cannot be cancelled.';
$lang->msg_reservation_cancel_deadline = 'The cancellation deadline has passed.';
$lang->msg_reservation_refund_failed = 'An error occurred while processing the refund.';
$lang->msg_reservation_cancel_failed = 'An error occurred while cancelling.';
$lang->msg_reservation_cancelled = 'Your booking has been cancelled.';
$lang->msg_reservation_cancel_reason = 'Booking cancelled';
$lang->msg_reservation_resource_closed = 'This resource has booking history, so it was closed instead of deleted.';

// Admin
$lang->rsv_tab_dashboard = 'Dashboard';
$lang->rsv_tab_bookings = 'Bookings';
$lang->rsv_tab_resources = 'Resources';
$lang->rsv_tab_schedule = 'Schedule';
$lang->rsv_tab_forms = 'Form fields';
$lang->rsv_tab_stats = 'Stats';
$lang->rsv_tab_config = 'Settings';
$lang->reservation_price_format = '%s KRW';
$lang->reservation_minutes = '%d мин';
$lang->reservation_prev_month = 'Предыдущий месяц';
$lang->reservation_next_month = 'Следующий месяц';
$lang->reservation_dow = 'Вс,Пн,Вт,Ср,Чт,Пт,Сб';

// Staff
$lang->rsv_tab_staff = 'Staff';
$lang->rsv_staff = 'Staff';
$lang->rsv_staff_add = 'Add staff';
$lang->rsv_staff_name = 'Name';
$lang->rsv_staff_position = 'Title';
$lang->rsv_staff_member = 'Linked member ID';
$lang->about_rsv_staff_member = 'Enter an ID to let that member sign in and see only their own bookings and payouts. Leave it empty for no link.';
$lang->rsv_staff_share_rate = 'Default share (%)';
$lang->about_rsv_staff_share_rate = 'The share of this person\'s sales that goes to them. Set a different share per service below if needed.';
$lang->rsv_staff_summary = 'Short introduction';
$lang->rsv_staff_content = 'Introduction';
$lang->rsv_staff_thumb = 'Photo URL';
$lang->rsv_staff_services = 'Services offered';
$lang->about_rsv_staff_services = 'Only checked services can be booked with this person. Leave price, duration or share empty to use the service defaults.';
$lang->rsv_staff_schedule = 'Working days';
$lang->about_rsv_staff_schedule = 'Bookings are taken only during the checked hours. Add personal days off under the schedule screen.';
$lang->rsv_staff_service_count = 'Services';
$lang->rsv_staff_none = 'No staff has been added yet.';
$lang->rsv_staff_status_active = 'Listed';
$lang->rsv_staff_status_hidden = 'Hidden';
$lang->confirm_rsv_staff_delete = 'This person will be deleted. If bookings are attached they will be hidden instead. Continue?';

// Settlement
$lang->rsv_tab_settlements = 'Payouts';
$lang->rsv_settlement = 'Payout';
$lang->rsv_settlement_build = 'Create payout';
$lang->rsv_settlement_period = 'Period';
$lang->rsv_settlement_count = 'Bookings';
$lang->rsv_settlement_gross = 'Sales';
$lang->rsv_settlement_share = 'Staff share';
$lang->rsv_settlement_store = 'Store share';
$lang->rsv_settlement_none = 'No payout has been created yet.';
$lang->rsv_settlement_status_draft = 'Draft';
$lang->rsv_settlement_status_confirmed = 'Confirmed';
$lang->rsv_settlement_status_paid = 'Paid';
$lang->rsv_settlement_confirm = 'Confirm';
$lang->rsv_settlement_pay = 'Mark as paid';
$lang->rsv_settlement_delete = 'Delete payout';
$lang->about_rsv_settlement = 'Only completed bookings are counted. Cancellations and no-shows are not treated as sales. Amounts and shares are taken from the booking as it stood.';
$lang->confirm_rsv_settlement_confirm = 'This payout will be confirmed and its amounts will no longer change. Continue?';
$lang->confirm_rsv_settlement_delete = 'This draft payout will be deleted and its bookings become available again. Continue?';
$lang->rsv_settlement_item_date = 'Date';
$lang->rsv_settlement_item_service = 'Service';
$lang->rsv_settlement_item_amount = 'Amount';
$lang->rsv_settlement_item_rate = 'Share';
$lang->rsv_settlement_item_share = 'Staff share';

// Booking and payment mode
$lang->rsv_booking_mode = 'Booking mode';
$lang->rsv_booking_mode_slot = 'Fixed slots';
$lang->rsv_booking_mode_staff = 'By staff';
$lang->about_rsv_booking_mode = 'Fixed slots share a pre-generated timetable. By staff works out each person\'s free time, which suits services of differing length.';
$lang->rsv_category = 'Category';
$lang->about_rsv_category = 'A grouping name shown to visitors.';
$lang->rsv_pay_mode = 'Payment';
$lang->rsv_pay_mode_none = 'None';
$lang->rsv_pay_mode_deposit = 'Deposit only';
$lang->rsv_pay_mode_full = 'Pay in full';
$lang->rsv_deposit_amount = 'Deposit';
$lang->about_rsv_pay_mode = 'With a deposit the rest is collected on arrival. With none the price is shown but nothing is charged.';

// Notifications
$lang->rsv_notify_mail = 'Email';
$lang->rsv_notify_sms = 'SMS';
$lang->rsv_notify_alimtalk = 'AlimTalk';
$lang->about_rsv_notify_alimtalk = 'Falls back to SMS until a sender is attached.';
$lang->rsv_notify_on_booked = 'On booking';
$lang->rsv_notify_on_confirmed = 'On confirmation';
$lang->rsv_notify_on_cancelled = 'On cancellation';
$lang->rsv_notify_remind = 'Reminder';
$lang->rsv_remind_hours = 'Hours before visit';
$lang->rsv_sms_from = 'SMS sender number';
$lang->about_rsv_sms_from = 'Leave empty to use the site default.';
$lang->rsv_slot_unit = 'Time step (minutes)';
$lang->about_rsv_slot_unit = 'Start times are offered at this interval in staff mode.';
$lang->rsv_allow_any_staff = 'Allow booking without choosing a person';
$lang->notify_subject_booked = '[{site}] {service} booking received';
$lang->notify_body_booked = '{name}, your {service} booking on {when} has been received. With {staff}. Reference {code}';
$lang->notify_subject_confirmed = '[{site}] {service} booking confirmed';
$lang->notify_body_confirmed = '{name}, your {service} booking on {when} is confirmed. With {staff}. Reference {code}';
$lang->notify_subject_cancelled = '[{site}] {service} booking cancelled';
$lang->notify_body_cancelled = '{name}, your {service} booking on {when} has been cancelled. Reference {code}';
$lang->notify_subject_remind = '[{site}] {service} booking tomorrow';
$lang->notify_body_remind = '{name}, a reminder about your {service} booking on {when}. With {staff}. Reference {code}';

// Messages
$lang->msg_reservation_no_staff = 'That person could not be found.';
$lang->msg_reservation_staff_no_service = 'That person does not offer this service.';
$lang->msg_reservation_need_staff = 'Please choose a person.';
$lang->msg_reservation_staff_save_failed = 'The staff record could not be saved.';
$lang->msg_reservation_no_settlement = 'That payout could not be found.';
$lang->msg_reservation_settlement_empty = 'There is nothing to pay out for that period.';
$lang->msg_reservation_settlement_locked = 'A confirmed payout cannot be changed.';

// Branch link
$lang->rsv_branch = 'Branch';
$lang->rsv_branch_pick = 'Which branch are you visiting';
$lang->rsv_branch_any = 'Any branch';
$lang->about_rsv_staff_branch = 'The branch this person works at. When a visitor picks a branch, only its staff are shown.';
$lang->msg_reservation_staff_other_branch = 'That person does not work at the branch you chose.';
$lang->rsv_tab_membership = 'Membership';
$lang->msg_reservation_coupon_invalid = 'This coupon is not valid.';
$lang->msg_reservation_coupon_not_applicable = 'This coupon cannot be used for this booking.';
$lang->msg_reservation_coupon_used = 'You have already used this coupon.';
$lang->msg_reservation_coupon_soldout = 'This coupon is no longer available.';
$lang->msg_reservation_coupon_taken = 'This coupon was already used for another booking.';
$lang->msg_reservation_credit_too_much = 'That is more than the credit you can use.';
$lang->msg_reservation_credit_short = 'Not enough credit.';
$lang->msg_reservation_no_member = 'Member not found.';
$lang->msg_reservation_need_title = 'Please enter a title.';
$lang->reservation_benefit = 'Benefits';
$lang->reservation_grade_discount = 'grade discount';
$lang->reservation_coupon = 'Coupon';
$lang->reservation_coupon_none = 'No coupon';
$lang->reservation_coupon_code = 'Coupon code';
$lang->reservation_credit = 'Use credit';
$lang->reservation_credit_usable = 'Up to %s won (balance %s won)';
$lang->reservation_credit_balance = 'Credit';
$lang->reservation_grade = 'Membership';
$lang->reservation_credit_earn = 'Earned';
$lang->reservation_credit_spend = 'Used';
$lang->reservation_credit_refund = 'Refunded';
$lang->reservation_credit_earn_cancel = 'Earn cancelled';
$lang->reservation_credit_admin = 'Adjusted';
$lang->reservation_my_coupons = 'My coupons';
