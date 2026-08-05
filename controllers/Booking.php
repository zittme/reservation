<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Booking as BookingModel;
use Zittme\Modules\Reservation\Models\Config as ConfigModel;
use Zittme\Modules\Reservation\Models\Slot;

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
	 * 예약 신청.
	 *
	 * 무료(또는 결제 불요) → 즉시 confirmed.
	 * 유료 → hold + zittme_pay 주문 생성 → pay_url 로 이동.
	 */
	public function procReservationSubmit()
	{
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

		$slot_srl = (int)\Context::get('slot_srl');
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

		// 예약 가능 창 검사 (지난 슬롯·너무 이른 예약 방지)
		$slot_dt = $slot->slot_date . str_replace(':', '', $slot->start_time);
		if ($slot_dt < date('YmdHi', time() + 60 * max(0, (int)$resource->min_lead_minutes)))
		{
			return new \BaseObject(-1, 'msg_reservation_too_late');
		}

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

		// 중복 예약(같은 슬롯) 검사
		if (BookingModel::hasActiveOnSlot($slot_srl, $member_srl))
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

		// 금액은 서버 것만 신뢰한다 — 리소스 단가 × 인원
		$paid = ($resource->require_payment ?? 'N') === 'Y' && (int)$resource->price > 0;
		$amount = $paid ? (int)$resource->price * $person : 0;

		if ($paid && !self::isPayAvailable())
		{
			return new \BaseObject(-1, 'msg_reservation_pay_unavailable');
		}

		// ★ 원자 점유 + 예약 생성
		$hold_minutes = max(3, (int)($config->hold_minutes ?? 10));
		$output = BookingModel::create((object)[
			'slot_srl' => $slot_srl,
			'resource_srl' => (int)$resource->resource_srl,
			'module_srl' => (int)($this->module_info->module_srl ?? 0),
			'member_srl' => $member_srl,
			'booker_name' => $booker_name,
			'booker_phone' => $booker_phone,
			'booker_email' => $booker_email,
			'guest_password' => $guest_password,
			'person_count' => $person,
			'amount' => $amount,
			'status' => $paid ? self::STATUS_HOLD : self::STATUS_CONFIRMED,
			'hold_expires' => $paid ? date('YmdHis', time() + 60 * $hold_minutes) : '',
			'extra_vars' => count($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '',
		]);
		if (!$output->toBool())
		{
			return $output;
		}
		$booking = $output->get('booking');

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

		$result_url = getNotEncodedFullUrl('', 'mid', \Context::get('mid'), 'act', 'dispReservationResult', 'code', $booking->booking_code);

		// 유료: 결제 주문 생성 → 결제 페이지로
		if ($paid)
		{
			$pay = \Zittme\Modules\Zittme_pay\PayService::createOrder([
				'source_module' => 'reservation',
				'source_srl' => (int)$booking->booking_srl,
				'member_srl' => $member_srl,
				'amount' => $amount,
				'title' => sprintf('%s %s %s', $resource->title, $slot->slot_date, $slot->start_time),
				'payer' => ['name' => $booker_name, 'phone' => $booker_phone, 'email' => $booker_email],
				'return_url' => $result_url,
			]);
			if (empty($pay->success))
			{
				// 결제 주문 실패 — 점유를 되돌린다
				BookingModel::cancelAndRelease((int)$booking->booking_srl, $member_srl, self::STATUS_CANCELLED);
				return new \BaseObject(-1, $pay->message ?: 'msg_reservation_pay_failed');
			}

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

		// 취소 마감 검사
		if ($resource && $slot)
		{
			$deadline_hours = max(0, (int)$resource->cancel_deadline_hours);
			$slot_ts = strtotime(sprintf(
				'%s-%s-%s %s:00',
				substr($slot->slot_date, 0, 4), substr($slot->slot_date, 4, 2), substr($slot->slot_date, 6, 2),
				$slot->start_time
			));
			if ($slot_ts !== false && time() > $slot_ts - 3600 * $deadline_hours)
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
					$refund_amount >= (int)$booking->amount ? 0 : $refund_amount
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

		$this->add('booking_code', $booking->booking_code);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', \Context::get('mid'), 'act', 'dispReservationResult', 'code', $booking->booking_code, 'gp', \Context::get('guest_password')));
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

		// 비회원 예약: 비밀번호 대조
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
		$amount = (int)$booking->amount;
		if ($amount <= 0)
		{
			return 0;
		}
		if (!$slot)
		{
			return $amount;
		}

		$slot_ts = strtotime(sprintf(
			'%s-%s-%s %s:00',
			substr($slot->slot_date, 0, 4), substr($slot->slot_date, 4, 2), substr($slot->slot_date, 6, 2),
			$slot->start_time
		));
		$days_left = $slot_ts !== false ? max(0, (int)floor(($slot_ts - time()) / 86400)) : 0;

		$percent = 0;
		foreach (ConfigModel::getRefundPolicy() as $days => $p)
		{
			if ($days_left >= $days)
			{
				$percent = $p;
				break;
			}
		}
		return (int)floor($amount * $percent / 100);
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
