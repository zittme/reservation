<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 예약 건.
 *
 * 상태 전이는 전부 조건부 UPDATE(멱등) — 결제 트리거가 중복 도착해도 한 번만 처리된다.
 * 슬롯 점유/반환은 Slot 모델의 원자 경로만 쓴다.
 */
class Booking
{
	/**
	 * 예약 생성 — 유일한 생성 경로.
	 *
	 * 순서가 중요하다:
	 *   1) 슬롯 원자 점유 (실패 = 마감)
	 *   2) 예약 행 INSERT (실패 시 즉시 슬롯 반환)
	 * 관리자 수동 예약도 이 함수를 그대로 쓴다.
	 *
	 * @param object $args booking 필드 (slot_srl, resource_srl, person_count, status, ...)
	 * @return object BaseObject; 성공 시 ->get('booking') 에 예약 객체
	 */
	public static function create(object $args): \BaseObject
	{
		$slot_srl = (int)($args->slot_srl ?? 0);
		$person = max(1, (int)($args->person_count ?? 1));

		// 1) 원자 점유
		if (!Slot::occupy($slot_srl, $person))
		{
			return new \BaseObject(-1, 'msg_reservation_slot_full');
		}

		// 2) 예약 행
		$booking_srl = getNextSequence();
		$insert = (object)[
			'booking_srl' => $booking_srl,
			'booking_code' => Base::generateBookingCode(),
			'module_srl' => (int)($args->module_srl ?? 0),
			'resource_srl' => (int)($args->resource_srl ?? 0),
			'slot_srl' => $slot_srl,
			'member_srl' => (int)($args->member_srl ?? 0),
			'booker_name' => (string)($args->booker_name ?? ''),
			'booker_phone' => (string)($args->booker_phone ?? ''),
			'booker_email' => (string)($args->booker_email ?? ''),
			'guest_password' => (string)($args->guest_password ?? ''),
			'person_count' => $person,
			'amount' => (int)($args->amount ?? 0),
			'pay_order_srl' => 0,
			'status' => (string)($args->status ?? Base::STATUS_HOLD),
			'hold_expires' => (string)($args->hold_expires ?? ''),
			'memo' => (string)($args->memo ?? ''),
			'admin_memo' => '',
			'extra_vars' => (string)($args->extra_vars ?? ''),
			'ipaddress' => \RX_CLIENT_IP ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
			'regdate' => Base::now(),
			'confirmed_date' => (string)($args->status ?? '') === Base::STATUS_CONFIRMED ? Base::now() : '',
			'cancelled_date' => '',
		];

		$output = executeQuery('reservation.insertBooking', $insert);
		if (!$output->toBool())
		{
			// INSERT 실패 — 점유를 즉시 되돌린다
			Slot::release($slot_srl, $person);
			return $output;
		}

		self::log($booking_srl, 'create', '', $insert->status, (int)$insert->member_srl);

		$result = new \BaseObject();
		$result->add('booking', $insert);
		return $result;
	}

	/**
	 * 상태 전이 (조건부, 멱등).
	 *
	 * @param int $booking_srl
	 * @param array $from 허용되는 현재 상태 목록
	 * @param string $to
	 * @param array $extra 추가로 갱신할 칼럼 (confirmed_date 등)
	 * @return bool 실제로 바뀌었는가 (false = 이미 다른 요청이 처리)
	 */
	public static function transition(int $booking_srl, array $from, string $to, array $extra = []): bool
	{
		$args = (object)array_merge([
			'booking_srl' => $booking_srl,
			'status' => $to,
			'from_status_list' => implode(',', $from),
		], $extra);

		$output = executeQuery('reservation.updateBookingStatusIf', $args);
		if (!$output->toBool())
		{
			return false;
		}
		return \DB::getInstance()->getAffectedRows() > 0;
	}

	/**
	 * 예약 확정.
	 *
	 * 점유는 이미 hold 시점에 되어 있으므로 슬롯은 건드리지 않는다.
	 *
	 * @param int $booking_srl
	 * @param int $actor_srl
	 * @return bool
	 */
	public static function confirm(int $booking_srl, int $actor_srl = 0): bool
	{
		$won = self::transition(
			$booking_srl,
			[Base::STATUS_HOLD, Base::STATUS_PENDING],
			Base::STATUS_CONFIRMED,
			['confirmed_date' => Base::now(), 'hold_expires' => '']
		);
		if ($won)
		{
			self::log($booking_srl, 'confirm', '', Base::STATUS_CONFIRMED, $actor_srl);
		}
		return $won;
	}

	/**
	 * 예약 취소 + 슬롯 반환.
	 *
	 * 조건부 전이에서 이긴 요청만 슬롯을 반환하므로 이중 반환이 없다.
	 *
	 * @param int $booking_srl
	 * @param int $actor_srl
	 * @param string $to cancelled | expired | noshow
	 * @return bool
	 */
	public static function cancelAndRelease(int $booking_srl, int $actor_srl = 0, string $to = Base::STATUS_CANCELLED): bool
	{
		$booking = self::get($booking_srl);
		if (!$booking)
		{
			return false;
		}

		$won = self::transition(
			$booking_srl,
			Base::OCCUPYING_STATUSES,
			$to,
			['cancelled_date' => Base::now(), 'hold_expires' => '']
		);
		if (!$won)
		{
			return false;
		}

		// 노쇼는 자리를 쓴 것이므로 슬롯을 반환하지 않는다
		if ($to !== Base::STATUS_NOSHOW)
		{
			Slot::release((int)$booking->slot_srl, (int)$booking->person_count);
		}
		self::log($booking_srl, $to === Base::STATUS_EXPIRED ? 'expire' : ($to === Base::STATUS_NOSHOW ? 'noshow' : 'cancel'), (string)$booking->status, $to, $actor_srl);
		return true;
	}

	/**
	 * 만료된 hold 정리 (lazy).
	 *
	 * 슬롯 조회 경로에서 호출된다 — cron 없이도 잔여 수량이 맞는 이유.
	 * 한 번에 소량만 처리해 조회 지연을 막는다.
	 *
	 * @return int 정리한 건수
	 */
	public static function expireStaleHolds(): int
	{
		$output = executeQuery('reservation.getExpiredHolds', (object)[
			'status' => Base::STATUS_HOLD,
			'now' => Base::now(),
			'list_count' => 20,
		]);
		if (!$output->toBool() || empty($output->data))
		{
			return 0;
		}

		$count = 0;
		foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
		{
			if (!empty($row->booking_srl) && self::cancelAndRelease((int)$row->booking_srl, 0, Base::STATUS_EXPIRED))
			{
				$count++;
			}
		}
		return $count;
	}

	/**
	 * 예약 1건.
	 *
	 * @param int $booking_srl
	 * @return ?object
	 */
	public static function get(int $booking_srl): ?object
	{
		$output = executeQuery('reservation.getBooking', (object)['booking_srl' => $booking_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->booking_srl)) ? $output->data : null;
	}

	/**
	 * 예약번호로 1건.
	 *
	 * @param string $code
	 * @return ?object
	 */
	public static function getByCode(string $code): ?object
	{
		$output = executeQuery('reservation.getBookingByCode', (object)['booking_code' => $code]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->booking_srl)) ? $output->data : null;
	}

	/**
	 * 회원의 같은 슬롯 활성 예약 존재 여부 (중복 예약 방지).
	 *
	 * unique 제약 대신 신청 시점 검사를 쓴다 — 취소 후 재예약이 가능해야 하기 때문.
	 * 검사와 점유 사이의 미세한 틈은 정원(capacity)이 막아 준다.
	 *
	 * @param int $slot_srl
	 * @param int $member_srl
	 * @return bool
	 */
	public static function hasActiveOnSlot(int $slot_srl, int $member_srl): bool
	{
		if ($member_srl <= 0)
		{
			return false;
		}
		$output = executeQuery('reservation.getActiveBookingOnSlot', (object)[
			'slot_srl' => $slot_srl,
			'member_srl' => $member_srl,
			'status_list' => implode(',', Base::OCCUPYING_STATUSES),
		]);
		return $output->toBool() && is_object($output->data) && !empty($output->data->booking_srl);
	}

	/**
	 * 상태 이력 기록.
	 *
	 * @param int $booking_srl
	 * @param string $action
	 * @param string $before
	 * @param string $after
	 * @param int $actor_srl
	 * @param string $memo
	 * @return void
	 */
	public static function log(int $booking_srl, string $action, string $before, string $after, int $actor_srl = 0, string $memo = ''): void
	{
		executeQuery('reservation.insertLog', (object)[
			'log_srl' => getNextSequence(),
			'booking_srl' => $booking_srl,
			'action' => $action,
			'before_status' => $before,
			'after_status' => $after,
			'actor_srl' => $actor_srl,
			'memo' => mb_substr($memo, 0, 250),
			'regdate' => Base::now(),
		]);
	}
}
