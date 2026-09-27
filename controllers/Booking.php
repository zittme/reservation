<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Availability;
use Zittme\Modules\Reservation\Models\Booking as BookingModel;
use Zittme\Modules\Reservation\Models\BranchLink;
use Zittme\Modules\Reservation\Models\Config as ConfigModel;
use Zittme\Modules\Reservation\Models\Coupon;
use Zittme\Modules\Reservation\Models\Credit;
use Zittme\Modules\Reservation\Models\Grade;
use Zittme\Modules\Reservation\Models\Notify;
use Zittme\Modules\Reservation\Models\Slot;
use Zittme\Modules\Reservation\Models\Staff as StaffModel;

/**
 * 예약 신청·조회·취소 (proc).
 *
 * 슬롯 점유는 전부 BookingModel::create → Slot::occupy 의 원자 경로를 탄다.
 */
class Booking extends Base
{
	/**
	 * 잔여 슬롯 조회 (JSON).
	 *
	 * 조회 경로에서 만료 hold 가 lazy 정리된다 (Slot::getRange 내부).
	 */
	public function procReservationGetSlots()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$from = preg_replace('/\D/', '', (string)\Context::get('from'));
		$to = preg_replace('/\D/', '', (string)\Context::get('to'));

		$resource = self::getOpenResource($resource_srl);
		if (!$resource)
		{
			return new \BaseObject(-1, 'msg_reservation_no_resource');
		}

		// 조회 범위 방어: 최대 62일
		if (strlen($from) !== 8 || strlen($to) !== 8 || $to < $from)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$max_to = date('Ymd', strtotime($from . ' +62 day'));
		if ($to > $max_to)
		{
			$to = $max_to;
		}

		// 예약 가능 창(min_lead ~ max_advance) 밖은 잘라낸다
		$min_dt = date('YmdHi', time() + 60 * max(0, (int)$resource->min_lead_minutes));
		$max_date = date('Ymd', strtotime('+' . max(1, (int)$resource->max_advance_days) . ' day'));

		$slots = [];
		foreach (Slot::getRange($resource_srl, $from, $to) as $slot)
		{
			if ($slot->slot_date > $max_date)
			{
				continue;
			}
			$slot_dt = $slot->slot_date . str_replace(':', '', $slot->start_time);
			$available = $slot->status === 'open' && $slot_dt >= $min_dt;
			$remain = max(0, (int)$slot->capacity - (int)$slot->booked_count);
			$slots[] = [
				'slot_srl' => (int)$slot->slot_srl,
				'date' => $slot->slot_date,
				'start' => $slot->start_time,
				'end' => $slot->end_time,
				'remain' => $available ? $remain : 0,
				'available' => $available && $remain > 0,
			];
		}

		$this->add('resource_srl', $resource_srl);
		$this->add('slots', $slots);
	}

	/**
	 * 담당자 모드의 예약 가능 시각 조회 (JSON).
	 *
	 * 슬롯을 미리 찍지 않으므로 요청 때마다 계산한다. 담당자를 고르지 않으면
	 * 그 시술이 가능한 담당자 전원의 합집합을 돌려주고, 어느 시각에 누가
	 * 가능한지도 함께 넘긴다. 실제 배정은 신청할 때 정한다.
	 */
	public function procReservationGetTimes()
	{
		BookingModel::expireStaleHolds();

		$resource_srl = (int)\Context::get('resource_srl');
		$staff_srl = (int)\Context::get('staff_srl');
		$date = preg_replace('/\D/', '', (string)\Context::get('date'));

		$resource = self::getOpenResource($resource_srl);
		if (!$resource)
		{
			return new \BaseObject(-1, 'msg_reservation_no_resource');
		}
		if (strlen($date) !== 8)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		if ((string)($resource->booking_mode ?? 'slot') !== 'staff')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$module_srl = self::instanceSrl();

		if ($staff_srl > 0)
		{
			$staff = StaffModel::get($staff_srl);
			if (!$staff || (string)$staff->status !== StaffModel::STATUS_ACTIVE)
			{
				return new \BaseObject(-1, 'msg_reservation_no_staff');
			}

			$map = StaffModel::getServiceMap($staff_srl);
			if (!isset($map[$resource_srl]))
			{
				return new \BaseObject(-1, 'msg_reservation_staff_no_service');
			}

			$resolved = StaffModel::resolveService($resource, $staff, $map[$resource_srl]);
			$times = [];
			foreach (Availability::getDayGrid($resource, $staff, $date, (int)$resolved['duration']) as $row)
			{
				// 가능한 시각만 주면 화면이 "원래 없는 시간" 과 "이미 찬 시간" 을 구분하지 못한다
				$times[] = [
					'time' => $row['time'],
					'open' => (bool)$row['open'],
					'staff' => $row['open'] ? [$staff_srl] : [],
				];
			}

			$this->add('duration', (int)$resolved['duration']);
			$this->add('price', (int)$resolved['price']);
			$this->add('times', $times);
			return;
		}

		$config = self::config();
		if ((string)($config->allow_any_staff ?? 'Y') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_reservation_need_staff');
		}

		$staff_list = StaffModel::getListForService($module_srl, $resource_srl, self::resolveBranchSrl());
		$merged = Availability::getTimesForAnyStaff($resource, $staff_list, $date);

		$times = [];
		foreach ($merged as $time => $row)
		{
			$times[] = ['time' => $time, 'open' => (bool)$row['open'], 'staff' => $row['staff']];
		}

		$this->add('duration', (int)($resource->duration ?? 0));
		$this->add('price', (int)($resource->price ?? 0));
		$this->add('times', $times);
	}

	/**
	 * 그 달에서 자리가 남은 날 조회 (JSON).
	 *
	 * 달력에서 꽉 찬 날을 눌러 들어갔다가 빈손으로 돌아 나오게 두지 않는다.
	 */
	public function procReservationGetOpenDays()
	{
		BookingModel::expireStaleHolds();

		$resource_srl = (int)\Context::get('resource_srl');
		$staff_srl = (int)\Context::get('staff_srl');
		$from = preg_replace('/\D/', '', (string)\Context::get('from'));
		$to = preg_replace('/\D/', '', (string)\Context::get('to'));

		$resource = self::getOpenResource($resource_srl);
		if (!$resource)
		{
			return new \BaseObject(-1, 'msg_reservation_no_resource');
		}
		if (strlen($from) !== 8 || strlen($to) !== 8 || $to < $from)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		if ((string)($resource->booking_mode ?? 'slot') !== 'staff')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 조회 범위를 막아 둔다. 한 번에 몇 달을 계산하면 화면이 멈춘다
		$max_to = date('Ymd', strtotime($from . ' +62 day'));
		if ($to > $max_to)
		{
			$to = $max_to;
		}

		$module_srl = self::instanceSrl();
		$branch_srl = self::resolveBranchSrl();

		if ($staff_srl > 0)
		{
			$staff = StaffModel::get($staff_srl);
			$staff_list = ($staff && (string)$staff->status === StaffModel::STATUS_ACTIVE) ? [$staff] : [];
		}
		else
		{
			$staff_list = array_values(StaffModel::getListForService($module_srl, $resource_srl, $branch_srl));
		}

		$this->add('days', Availability::getOpenDates($resource, $staff_list, $from, $to));
	}

	/**
	 * 예약 신청.
	 *
	 * 무료(또는 결제 불요) → 즉시 confirmed.
	 * 유료 → hold + zittme_pay 주문 생성 → pay_url 로 이동.
	 */
	public function procReservationSubmit()
	{
		BookingModel::expireStaleHolds();

		$config = self::config();
		if (($config->enabled ?? 'Y') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_reservation_disabled');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		if ($member_srl <= 0 && ($config->allow_guest ?? 'Y') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_reservation_login_required');
		}

		// 무엇을, 언제, 누구에게 받는가. 두 점유 방식의 차이는 여기서 끝난다
		$plan = $this->resolvePlan();
		if ($plan instanceof \BaseObject)
		{
			return $plan;
		}

		$resource = $plan['resource'];
		$slot = $plan['slot'];
		$slot_srl = (int)$plan['slot_srl'];

		// 예약자 정보
		$booker_name = trim((string)\Context::get('booker_name'));
		$booker_phone = trim((string)\Context::get('booker_phone'));
		$booker_email = trim((string)\Context::get('booker_email'));
		if ($member_srl > 0 && $booker_name === '')
		{
			$booker_name = (string)$logged_info->nick_name;
		}
		if ($booker_name === '' || mb_strlen($booker_name) > 80)
		{
			return new \BaseObject(-1, 'msg_reservation_need_name');
		}
		if ($member_srl <= 0 && $booker_phone === '')
		{
			return new \BaseObject(-1, 'msg_reservation_need_phone');
		}

		// 비회원 조회 비밀번호
		$guest_password = '';
		if ($member_srl <= 0)
		{
			$raw = (string)\Context::get('guest_password');
			if (strlen($raw) < 4)
			{
				return new \BaseObject(-1, 'msg_reservation_need_password');
			}
			$guest_password = \Rhymix\Framework\Password::hashPassword($raw);
		}

		// 약관 동의 (필수)
		if (\Context::get('agree_privacy') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_reservation_need_agreement');
		}

		// 인원
		$person = max(1, min(100, (int)(\Context::get('person_count') ?: 1)));

		// 중복 예약(같은 슬롯) 검사. 담당자 모드는 점유 칸이 겹침을 막으므로 건너뛴다
		if ($slot_srl > 0 && BookingModel::hasActiveOnSlot($slot_srl, $member_srl))
		{
			return new \BaseObject(-1, 'msg_reservation_duplicate');
		}

		// 1인 동시 활성 예약 상한
		$max_active = (int)($config->max_active_per_member ?? 0);
		if ($member_srl > 0 && $max_active > 0)
		{
			$output = executeQuery('reservation.getActiveCountByMember', (object)[
				'member_srl' => $member_srl,
				'status_list' => implode(',', self::OCCUPYING_STATUSES),
			]);
			if ($output->toBool() && (int)($output->data->count ?? 0) >= $max_active)
			{
				return new \BaseObject(-1, 'msg_reservation_too_many');
			}
		}

		// 추가 문항 수집·검증
		$extra = [];
		foreach (self::getFormFields((int)$resource->resource_srl) as $field)
		{
			$value = trim((string)\Context::get('rf_' . $field->field_name));
			if (($field->required ?? 'N') === 'Y' && $value === '')
			{
				return new \BaseObject(-1, sprintf(lang('reservation.msg_reservation_field_required'), $field->label));
			}
			if ($value !== '')
			{
				$extra[$field->field_name] = mb_substr($value, 0, 1000);
			}
		}

		// 금액은 서버 것만 신뢰한다 — 정해진 단가 × 인원
		$amount = (int)$plan['price'] * $person;

		// 할인·쿠폰·적립금. 화면이 보낸 금액은 쓰지 않고 여기서 다시 계산한다
		$benefit = self::resolveBenefit($member_srl, $amount, (int)$resource->resource_srl);
		if ($benefit instanceof \BaseObject)
		{
			return $benefit;
		}
		$payable = max(0, $amount - $benefit['discount'] - $benefit['credit']);

		// 미리 받을 금액. 예약금이면 총액보다 적고, 전액 선결제면 총액과 같다
		$upfront = min($payable, self::getUpfrontAmount($resource, $payable));
		$paid = $upfront > 0;

		if ($paid && !self::isPayAvailable())
		{
			return new \BaseObject(-1, 'msg_reservation_pay_unavailable');
		}

		// 원자 점유 + 예약 생성
		$hold_minutes = max(3, (int)($config->hold_minutes ?? 10));
		$output = BookingModel::create((object)[
			'slot_srl' => $slot_srl,
			'staff_srl' => (int)$plan['staff_srl'],
			'service_date' => (string)$plan['service_date'],
			'start_datetime' => (string)$plan['start_datetime'],
			'end_datetime' => (string)$plan['end_datetime'],
			'duration_minutes' => (int)$plan['duration'],
			'occupy_minutes' => (int)$plan['occupy_minutes'],
			'share_rate_snapshot' => (int)$plan['share_rate'],
			'resource_srl' => (int)$resource->resource_srl,
			'module_srl' => (int)($this->module_info->module_srl ?? 0),
			'member_srl' => $member_srl,
			'booker_name' => $booker_name,
			'booker_phone' => $booker_phone,
			'booker_email' => $booker_email,
			'guest_password' => $guest_password,
			'person_count' => $person,
			'amount' => $amount,
			'discount_amount' => $benefit['discount'],
			'credit_used' => $benefit['credit'],
			'status' => $paid ? self::STATUS_HOLD : self::STATUS_CONFIRMED,
			'hold_expires' => $paid ? date('YmdHis', time() + 60 * $hold_minutes) : '',
			'extra_vars' => count($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '',
		]);
		if (!$output->toBool())
		{
			return $output;
		}
		$booking = $output->get('booking');
		if ($member_srl <= 0)
		{
			self::grantGuestAccess((string)$booking->booking_code);
		}

		// 쿠폰 점유와 적립금 차감은 예약번호가 나온 다음에야 할 수 있다.
		// 여기서 실패하면 예약을 되돌린다 — 자리만 잡고 혜택은 못 받은 예약을 남기지 않는다
		$claim = self::claimBenefit($booking, $benefit);
		if ($claim instanceof \BaseObject)
		{
			BookingModel::cancelAndRelease((int)$booking->booking_srl, $member_srl, self::STATUS_CANCELLED);
			return $claim;
		}
		$booking->coupon_issue_srl = (int)$claim;

		// 동의 이력
		executeQuery('reservation.insertConsent', (object)[
			'consent_srl' => getNextSequence(),
			'booking_srl' => (int)$booking->booking_srl,
			'agreement_type' => 'privacy',
			'agreement_version' => (string)($config->privacy_version ?? '1.0'),
			'agreed' => 'Y',
			'ipaddress' => \RX_CLIENT_IP ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
			'regdate' => self::now(),
		]);

		// 접수 알림. 결제로 넘어가는 예약은 확정될 때 다시 한 번 나간다
		Notify::send($booking, Notify::TPL_BOOKED);

		// mid 가 없으면 복귀 주소에도 mid 가 빠져 결과 화면에 레이아웃이 붙지 않는다.
		// 스킨이 mid 를 안 보내는 경우까지 여기서 막는다.
		$return_mid = trim((string)\Context::get('mid'));
		if ($return_mid === '')
		{
			$instance = self::getDefaultInstance();
			$return_mid = is_object($instance) ? (string)($instance->mid ?? '') : '';
		}

		$result_url = getNotEncodedFullUrl('', 'mid', $return_mid, 'act', 'dispReservationResult', 'code', $booking->booking_code);

		// 유료: 결제 주문 생성 → 결제 페이지로
		if ($paid)
		{
			$pay = \Zittme\Modules\Zittme_pay\PayService::createOrder([
				'source_module' => 'reservation',
				'source_srl' => (int)$booking->booking_srl,
				'source_code' => (string)$booking->booking_code,
				'member_srl' => $member_srl,
				// 결제로 받는 금액이다. 예약금이면 총액보다 적다
				'amount' => $upfront,
				'title' => trim(sprintf('%s %s', $resource->title, $plan['label'])),
				'payer' => ['name' => $booker_name, 'phone' => $booker_phone, 'email' => $booker_email],
				'return_url' => $result_url,
			]);
			if (empty($pay->success))
			{
				// 결제 주문 실패 — 점유를 되돌린다
				BookingModel::cancelAndRelease((int)$booking->booking_srl, $member_srl, self::STATUS_CANCELLED);
				return new \BaseObject(-1, $pay->message ?: 'msg_reservation_pay_failed');
			}

			// 예약 행의 amount 는 시술 총액이다. 결제 금액으로 덮어쓰지 않는다
			executeQuery('reservation.updateBookingPayOrder', (object)[
				'booking_srl' => (int)$booking->booking_srl,
				'pay_order_srl' => (int)$pay->order_srl,
				'amount' => $amount,
			]);

			// 0원 승인(전액 상계) 등으로 이미 승인됐다면 트리거가 확정을 처리한다
			$this->add('booking_code', $booking->booking_code);
			$this->add('pay_url', (string)$pay->pay_url);
			$this->setRedirectUrl((string)$pay->pay_url ?: $result_url);
			return;
		}

		$this->add('booking_code', $booking->booking_code);
		$this->add('pay_url', '');
		$this->setRedirectUrl($result_url);
	}

	/**
	 * 이 예약에 붙는 혜택을 정한다 — 등급 할인, 쿠폰, 적립금.
	 *
	 * 아직 아무것도 쓰지 않는다. 쿠폰 점유와 적립금 차감은 예약번호가 나온 뒤
	 * claimBenefit 이 한다. 여기서는 금액만 확정한다.
	 *
	 * @param int $member_srl
	 * @param int $amount 시술 총액
	 * @param int $resource_srl
	 * @return array|\BaseObject [discount, credit, coupon_issue_srl, coupon_code]
	 */
	protected static function resolveBenefit(int $member_srl, int $amount, int $resource_srl)
	{
		$empty = ['discount' => 0, 'credit' => 0, 'coupon_issue_srl' => 0, 'coupon_code' => ''];

		// 혜택은 회원의 몫이다. 비회원은 누구인지 알 수 없어 적립도 차감도 할 수 없다
		if ($member_srl <= 0 || $amount <= 0)
		{
			return $empty;
		}

		$config = ConfigModel::getConfig();

		// 1) 등급 할인
		$discount = $amount - Grade::applyDiscount($amount, Grade::discountFor($member_srl));

		// 2) 쿠폰 — 가진 쿠폰을 고르거나 코드를 적는다. 둘 다는 안 된다
		$issue_srl = (int)\Context::get('coupon_issue_srl');
		$code = trim((string)\Context::get('coupon_code'));

		if ((string)($config->coupon_enabled ?? 'N') !== 'Y')
		{
			$issue_srl = 0;
			$code = '';
		}

		$rest = max(0, $amount - $discount);

		if ($issue_srl > 0)
		{
			$issue = Coupon::getIssue($issue_srl);
			if (!$issue || (int)$issue->member_srl !== $member_srl || (int)$issue->booking_srl > 0)
			{
				return new \BaseObject(-1, 'msg_reservation_coupon_invalid');
			}

			$coupon = Coupon::get((int)$issue->coupon_srl);
			if (!$coupon || !Coupon::isUsableNow($coupon))
			{
				return new \BaseObject(-1, 'msg_reservation_coupon_invalid');
			}

			$off = Coupon::discountFor($coupon, $rest, $resource_srl);
			if ($off === null)
			{
				return new \BaseObject(-1, 'msg_reservation_coupon_not_applicable');
			}

			$discount += $off;
			$code = '';
		}
		elseif ($code !== '')
		{
			$coupon = Coupon::getByCode($code);
			if (!$coupon || empty($coupon->code) || !Coupon::isUsableNow($coupon))
			{
				return new \BaseObject(-1, 'msg_reservation_coupon_invalid');
			}

			$off = Coupon::discountFor($coupon, $rest, $resource_srl);
			if ($off === null)
			{
				return new \BaseObject(-1, 'msg_reservation_coupon_not_applicable');
			}

			$discount += $off;
		}

		// 3) 적립금 — 할인 뒤 남은 금액 안에서만
		$credit = 0;
		$want = (int)\Context::get('credit_use');
		if ($want > 0)
		{
			$usable = Credit::usableFor($member_srl, max(0, $amount - $discount));
			if ($want > $usable)
			{
				return new \BaseObject(-1, 'msg_reservation_credit_too_much');
			}
			$credit = $want;
		}

		return [
			'discount' => min($amount, max(0, $discount)),
			'credit' => $credit,
			'coupon_issue_srl' => $issue_srl,
			'coupon_code' => $code,
		];
	}

	/**
	 * 정해 둔 혜택을 실제로 쓴다. 쿠폰은 원자 점유, 적립금은 원자 차감.
	 *
	 * @param object $booking
	 * @param array $benefit resolveBenefit 결과
	 * @return int|\BaseObject 쓴 쿠폰 발급 번호 (실패하면 BaseObject)
	 */
	protected static function claimBenefit(object $booking, array $benefit)
	{
		$booking_srl = (int)$booking->booking_srl;
		$member_srl = (int)$booking->member_srl;
		$issue_srl = (int)$benefit['coupon_issue_srl'];

		if ($issue_srl > 0)
		{
			if (!Coupon::claimIssue($issue_srl, $member_srl, $booking_srl))
			{
				return new \BaseObject(-1, 'msg_reservation_coupon_taken');
			}
		}
		elseif ((string)$benefit['coupon_code'] !== '')
		{
			$result = Coupon::redeemCode(
				(string)$benefit['coupon_code'],
				$member_srl,
				$booking_srl,
				max(0, (int)$booking->amount),
				(int)$booking->resource_srl
			);
			if (empty($result->success))
			{
				return new \BaseObject(-1, (string)($result->message ?: 'msg_reservation_coupon_invalid'));
			}
			$issue_srl = (int)$result->issue_srl;
		}

		if ((int)$benefit['credit'] > 0 && !Credit::spend($member_srl, (int)$benefit['credit'], $booking_srl))
		{
			// 적립금이 모자란다 — 방금 잡은 쿠폰을 풀어 준다
			Coupon::releaseByBooking($booking_srl);
			return new \BaseObject(-1, 'msg_reservation_credit_short');
		}

		executeQuery('reservation.updateBookingBenefit', (object)[
			'booking_srl' => $booking_srl,
			'discount_amount' => (int)$benefit['discount'],
			'coupon_issue_srl' => $issue_srl,
			'credit_used' => (int)$benefit['credit'],
			'credit_earned' => 0,
		]);

		return $issue_srl;
	}

	/**
	 * 무엇을 언제 누구에게 받는지 정한다.
	 *
	 * 두 점유 방식의 차이는 이 함수 안에서 끝난다. 밖에서는 어느 방식인지
	 * 신경 쓰지 않고 같은 값을 쓴다. 값과 소요시간은 화면이 보낸 값을 믿지 않고
	 * 서버에서 다시 정한다.
	 *
	 * @return array|\BaseObject 실패하면 BaseObject
	 */
	protected function resolvePlan()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$slot_srl = (int)\Context::get('slot_srl');

		// 슬롯 번호가 오면 슬롯 모드다. 어느 시술인지는 슬롯이 안다
		if ($slot_srl > 0)
		{
			$slot = Slot::get($slot_srl);
			if (!$slot)
			{
				return new \BaseObject(-1, 'msg_reservation_no_slot');
			}

			$resource = self::getOpenResource((int)$slot->resource_srl);
			if (!$resource)
			{
				return new \BaseObject(-1, 'msg_reservation_no_resource');
			}

			$slot_dt = $slot->slot_date . str_replace(':', '', $slot->start_time);
			if ($slot_dt < date('YmdHi', time() + 60 * max(0, (int)$resource->min_lead_minutes)))
			{
				return new \BaseObject(-1, 'msg_reservation_too_late');
			}

			return [
				'resource' => $resource,
				'slot' => $slot,
				'slot_srl' => $slot_srl,
				'staff_srl' => 0,
				'service_date' => (string)$slot->slot_date,
				'start_datetime' => self::slotDatetime((string)$slot->slot_date, (string)$slot->start_time),
				'end_datetime' => self::slotDatetime((string)$slot->slot_date, (string)$slot->end_time),
				'duration' => (int)($resource->duration ?? 0),
				'occupy_minutes' => 0,
				'price' => (int)($resource->price ?? 0),
				'share_rate' => -1,
				'label' => $slot->slot_date . ' ' . $slot->start_time,
			];
		}

		$resource = self::getOpenResource($resource_srl);
		if (!$resource)
		{
			return new \BaseObject(-1, 'msg_reservation_no_resource');
		}
		if ((string)($resource->booking_mode ?? 'slot') !== 'staff')
		{
			return new \BaseObject(-1, 'msg_reservation_no_slot');
		}

		$date = preg_replace('/\D/', '', (string)\Context::get('date'));
		$time = trim((string)\Context::get('start_time'));
		if (strlen($date) !== 8 || Availability::toMinutes($time) === null)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$module_srl = self::instanceSrl();
		$staff = $this->pickStaff($resource, $module_srl, $date, $time);
		if ($staff instanceof \BaseObject)
		{
			return $staff;
		}

		$map = StaffModel::getServiceMap((int)$staff->staff_srl);
		$resolved = StaffModel::resolveService($resource, $staff, $map[$resource_srl] ?? null);
		$duration = (int)$resolved['duration'];

		// 고른 시각이 지금도 비어 있는지 다시 본다. 화면을 띄워 둔 사이에 찼을 수 있다
		$open = Availability::getTimesForStaff($resource, $staff, $date, $duration);
		if (!in_array($time, $open, true))
		{
			return new \BaseObject(-1, 'msg_reservation_slot_full');
		}

		$start_ts = strtotime(substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' . $time . ':00');
		if ($start_ts === false)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 점유는 앞뒤 준비시간까지 잡는다. 시술 시간만 잡으면 다음 손님과 붙는다
		$before = max(0, (int)($resource->buffer_before ?? 0));
		$after = max(0, (int)($resource->buffer_after ?? 0));

		return [
			'resource' => $resource,
			'slot' => null,
			'slot_srl' => 0,
			'staff_srl' => (int)$staff->staff_srl,
			'service_date' => $date,
			'start_datetime' => date('YmdHis', $start_ts),
			'end_datetime' => date('YmdHis', $start_ts + $duration * 60),
			'duration' => $duration,
			'occupy_minutes' => $before + $duration + $after,
			'price' => (int)$resolved['price'],
			'share_rate' => (int)$resolved['share_rate'],
			'label' => $date . ' ' . $time,
		];
	}

	/**
	 * 담당자를 정한다. 고르지 않았으면 그 시각에 가능한 사람 중 하나를 배정한다.
	 *
	 * @param object $resource
	 * @param int $module_srl
	 * @param string $date
	 * @param string $time
	 * @return object|\BaseObject
	 */
	protected function pickStaff(object $resource, int $module_srl, string $date, string $time)
	{
		$staff_srl = (int)\Context::get('staff_srl');
		$resource_srl = (int)$resource->resource_srl;

		if ($staff_srl > 0)
		{
			$staff = StaffModel::get($staff_srl);
			if (!$staff || (string)$staff->status !== StaffModel::STATUS_ACTIVE)
			{
				return new \BaseObject(-1, 'msg_reservation_no_staff');
			}

			$map = StaffModel::getServiceMap($staff_srl);
			if (!isset($map[$resource_srl]))
			{
				return new \BaseObject(-1, 'msg_reservation_staff_no_service');
			}

			// 고른 지점 소속인지 본다. 안 보면 다른 지점 담당자가 잡힌다
			$branch_srl = self::resolveBranchSrl();
			if ($branch_srl > 0 && (int)$staff->branch_srl > 0 && (int)$staff->branch_srl !== $branch_srl)
			{
				return new \BaseObject(-1, 'msg_reservation_staff_other_branch');
			}

			return $staff;
		}

		$config = self::config();
		if ((string)($config->allow_any_staff ?? 'Y') !== 'Y')
		{
			return new \BaseObject(-1, 'msg_reservation_need_staff');
		}

		// 그 시각에 비어 있는 사람 중 예약이 가장 적은 쪽으로 돌린다
		$candidates = StaffModel::getListForService($module_srl, $resource_srl, self::resolveBranchSrl());
		$available = [];
		foreach ($candidates as $candidate)
		{
			$resolved = StaffModel::resolveService($resource, $candidate);
			$times = Availability::getTimesForStaff($resource, $candidate, $date, (int)$resolved['duration']);
			if (in_array($time, $times, true))
			{
				$available[] = [
					'staff' => $candidate,
					'load' => count(Availability::getBusyRanges((int)$candidate->staff_srl, $date)),
				];
			}
		}

		if (!count($available))
		{
			return new \BaseObject(-1, 'msg_reservation_slot_full');
		}

		usort($available, function ($a, $b) {
			return $a['load'] <=> $b['load'];
		});

		return $available[0]['staff'];
	}

	/**
	 * 손님이 고른 지점. 고르지 않았고 지점이 한 곳뿐이면 그 지점으로 본다.
	 *
	 * 지점 모듈을 쓰지 않는 사이트에서는 늘 0 이며, 담당자를 지점으로 거르지 않는다.
	 *
	 * @return int
	 */
	protected static function resolveBranchSrl(): int
	{
		$branch_srl = (int)\Context::get('branch_srl');
		if ($branch_srl > 0)
		{
			return $branch_srl;
		}

		return BranchLink::getSoleBranchSrl();
	}

	/**
	 * 예약할 때 미리 받을 금액.
	 *
	 * none 이면 받지 않고, deposit 이면 정해 둔 예약금만, full 이면 전액이다.
	 * 예약금이 총액보다 크면 총액까지만 받는다.
	 *
	 * @param object $resource
	 * @param int $amount 시술 총액
	 * @return int
	 */
	protected static function getUpfrontAmount(object $resource, int $amount): int
	{
		if ($amount <= 0)
		{
			return 0;
		}

		$mode = (string)($resource->pay_mode ?? 'none');

		// pay_mode 가 없던 시절의 자원은 결제 필요 표시를 그대로 따른다
		if ($mode === 'none' && ($resource->require_payment ?? 'N') === 'Y')
		{
			$mode = 'full';
		}

		if ($mode === 'full')
		{
			return $amount;
		}
		if ($mode === 'deposit')
		{
			return min($amount, max(0, (int)($resource->deposit_amount ?? 0)));
		}

		return 0;
	}

	/**
	 * 예약 취소 (예약자 본인).
	 *
	 * 회원은 본인 확인, 비회원은 예약번호+비밀번호. 취소 마감·환불 규정을 적용한다.
	 */
	public function procReservationCancel()
	{
		$booking = $this->authorizeBookingAccess();
		if ($booking instanceof \BaseObject)
		{
			return $booking;
		}

		if (!in_array($booking->status, self::OCCUPYING_STATUSES, true))
		{
			return new \BaseObject(-1, 'msg_reservation_not_cancellable');
		}

		$resource = self::getOpenResource((int)$booking->resource_srl, true);
		$slot = Slot::get((int)$booking->slot_srl);

		// 취소 마감 검사. 담당자 모드는 슬롯이 없으므로 예약 행의 시작 시각을 본다
		if ($resource)
		{
			$start_ts = false;
			$start_datetime = (string)($booking->start_datetime ?? '');
			if (strlen($start_datetime) === 14)
			{
				$start_ts = strtotime(sprintf(
					'%s-%s-%s %s:%s:%s',
					substr($start_datetime, 0, 4), substr($start_datetime, 4, 2), substr($start_datetime, 6, 2),
					substr($start_datetime, 8, 2), substr($start_datetime, 10, 2), substr($start_datetime, 12, 2)
				));
			}
			elseif ($slot)
			{
				$start_ts = strtotime(sprintf(
					'%s-%s-%s %s:00',
					substr($slot->slot_date, 0, 4), substr($slot->slot_date, 4, 2), substr($slot->slot_date, 6, 2),
					$slot->start_time
				));
			}

			$deadline_hours = max(0, (int)$resource->cancel_deadline_hours);
			if ($start_ts !== false && time() > $start_ts - 3600 * $deadline_hours)
			{
				return new \BaseObject(-1, 'msg_reservation_cancel_deadline');
			}
		}

		// 결제 환불 (규정 비율)
		if ((int)$booking->pay_order_srl > 0 && self::isPayAvailable())
		{
			$refund_amount = self::calcRefundAmount($booking, $slot);
			if ($refund_amount > 0)
			{
				$refund = \Zittme\Modules\Zittme_pay\PayService::cancel(
					(int)$booking->pay_order_srl,
					lang('reservation.msg_reservation_cancel_reason'),
					$refund_amount
				);
				if (empty($refund->success))
				{
					return new \BaseObject(-1, $refund->message ?: 'msg_reservation_refund_failed');
				}
				// 전액 환불이면 pay 취소 트리거가 예약 취소까지 처리한다.
				// 부분 환불이면 트리거가 오지 않을 수 있으므로 아래에서 직접 취소한다.
			}
		}

		if (!BookingModel::cancelAndRelease((int)$booking->booking_srl, (int)$booking->member_srl, self::STATUS_CANCELLED))
		{
			// 트리거가 이미 취소했다면 그것도 성공이다
			$fresh = BookingModel::get((int)$booking->booking_srl);
			if (!$fresh || $fresh->status !== self::STATUS_CANCELLED)
			{
				return new \BaseObject(-1, 'msg_reservation_cancel_failed');
			}
		}

		$this->setMessage('msg_reservation_cancelled');
	}

	/**
	 * 비회원 예약 조회 (예약번호 + 비밀번호).
	 */
	public function procReservationGuestLookup()
	{
		$booking = $this->authorizeBookingAccess();
		if ($booking instanceof \BaseObject)
		{
			return $booking;
		}

		if ((int)$booking->member_srl <= 0)
		{
			self::grantGuestAccess((string)$booking->booking_code);
		}
		$this->add('booking_code', $booking->booking_code);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', \Context::get('mid'), 'act', 'dispReservationResult', 'code', $booking->booking_code));
	}

	/**
	 * 예약 접근 권한 확인 — 회원 본인 또는 비회원(코드+비밀번호).
	 *
	 * @return object|\BaseObject 예약 객체 또는 오류
	 */
	protected function authorizeBookingAccess()
	{
		$code = trim((string)\Context::get('booking_code'));
		$booking = $code !== '' ? BookingModel::getByCode($code) : null;
		if (!$booking)
		{
			return new \BaseObject(-1, 'msg_reservation_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		// 관리자는 통과
		if ($logged_info && $logged_info->is_admin === 'Y')
		{
			return $booking;
		}

		if ((int)$booking->member_srl > 0)
		{
			if ($member_srl !== (int)$booking->member_srl)
			{
				return new \BaseObject(-1, 'msg_reservation_not_yours');
			}
			return $booking;
		}

		// 비회원 예약: 이 세션에서 이미 확인했으면 통과, 아니면 비밀번호 대조
		if (\Context::get('act') !== 'procReservationGuestLookup' && self::hasGuestAccess((string)$booking->booking_code))
		{
			return $booking;
		}
		$raw = (string)\Context::get('guest_password');
		if ($raw === '' || empty($booking->guest_password)
			|| !\Rhymix\Framework\Password::checkPassword($raw, $booking->guest_password))
		{
			return new \BaseObject(-1, 'msg_reservation_wrong_password');
		}
		return $booking;
	}

	/**
	 * 환불 금액 계산 (설정의 "일수:비율" 규정).
	 *
	 * @param object $booking
	 * @param ?object $slot
	 * @return int
	 */
	protected static function calcRefundAmount(object $booking, ?object $slot): int
	{
		$paid = 0;
		if ((int)$booking->pay_order_srl > 0 && class_exists('\Zittme\Modules\Zittme_pay\Models\Order'))
		{
			$order = \Zittme\Modules\Zittme_pay\Models\Order::get((int)$booking->pay_order_srl);
			// 아직 결제되지 않은 주문(결제 대기·입금 대기)은 돌려줄 돈이 없다
			$paid = ($order && in_array((string)$order->status, ['paid', 'partial_cancelled'], true))
				? (int)($order->remain_amount ?? 0) : 0;
		}
		if ($paid <= 0)
		{
			return 0;
		}

		$start_ts = false;
		$start_datetime = (string)($booking->start_datetime ?? '');
		if (strlen($start_datetime) === 14)
		{
			$start_ts = strtotime(sprintf(
				'%s-%s-%s %s:%s:%s',
				substr($start_datetime, 0, 4), substr($start_datetime, 4, 2), substr($start_datetime, 6, 2),
				substr($start_datetime, 8, 2), substr($start_datetime, 10, 2), substr($start_datetime, 12, 2)
			));
		}
		elseif ($slot)
		{
			$start_ts = strtotime(sprintf(
				'%s-%s-%s %s:00',
				substr($slot->slot_date, 0, 4), substr($slot->slot_date, 4, 2), substr($slot->slot_date, 6, 2),
				$slot->start_time
			));
		}
		if ($start_ts === false)
		{
			return $paid;
		}
		$days_left = max(0, (int)floor(($start_ts - time()) / 86400));

		$percent = 0;
		foreach (ConfigModel::getRefundPolicy() as $days => $p)
		{
			if ($days_left >= $days)
			{
				$percent = $p;
				break;
			}
		}
		return (int)floor($paid * $percent / 100);
	}

	/**
	 * 노출 가능한 리소스.
	 *
	 * @param int $resource_srl
	 * @param bool $any_status 취소 등에서는 닫힌 리소스도 허용
	 * @return ?object
	 */
	protected static function getOpenResource(int $resource_srl, bool $any_status = false): ?object
	{
		if ($resource_srl <= 0)
		{
			return null;
		}
		$output = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
		$resource = ($output->toBool() && is_object($output->data) && !empty($output->data->resource_srl)) ? $output->data : null;
		if (!$resource)
		{
			return null;
		}
		if (!$any_status && ($resource->status ?? '') !== 'open')
		{
			return null;
		}
		return $resource;
	}

	/**
	 * 추가 문항 목록.
	 *
	 * @param int $resource_srl
	 * @return array
	 */
	public static function getFormFields(int $resource_srl): array
	{
		$output = executeQuery('reservation.getFormFieldList', (object)['resource_srl' => $resource_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->field_srl); }));
	}
}
