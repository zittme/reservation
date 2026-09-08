<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 적립금 — 예약 자체 원장.
 *
 * 코어 point 모듈은 커뮤니티 포인트라 연동하지 않는다 (사용자 지시).
 * 잔액은 reservation_credit_balance, 이력은 reservation_credit_log.
 * 사용 차감은 `balance >= n` 조건부 UPDATE 원자 경로로만 일어난다 — 음수 잔액 불가.
 */
class Credit
{
	/**
	 * 잔액.
	 *
	 * @param int $member_srl
	 * @return int
	 */
	public static function balanceOf(int $member_srl): int
	{
		if ($member_srl <= 0)
		{
			return 0;
		}

		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT balance FROM reservation_credit_balance WHERE member_srl = ?', $member_srl
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		return $row ? (int)$row->balance : 0;
	}

	/**
	 * 적립·환불·관리자 조정 (+/- 모두).
	 *
	 * 잔액이 음수가 되는 차감은 여기로 오지 않는다. 사용은 spend 로만 한다.
	 *
	 * @param int $member_srl
	 * @param int $amount +적립 / -회수
	 * @param string $type earn | refund | earn_cancel | admin
	 * @param int $booking_srl
	 * @param string $memo
	 * @return bool
	 */
	public static function add(int $member_srl, int $amount, string $type, int $booking_srl = 0, string $memo = ''): bool
	{
		if ($member_srl <= 0 || $amount === 0)
		{
			return false;
		}

		$db = \Zittme\Framework\DB::getInstance();

		// 잔액 행 보장 (동시 생성은 PK 충돌로 한쪽만 성공 — 이후 UPDATE 는 공통)
		try
		{
			$db->query('INSERT INTO reservation_credit_balance (member_srl, balance, upddate) VALUES (?, 0, ?)', $member_srl, Base::now());
		}
		catch (\Exception $e)
		{
			// 이미 있음
		}

		if ($amount < 0)
		{
			// 회수는 잔액 한도 내에서만 (0 미만 방지)
			$stmt = $db->query(
				'UPDATE reservation_credit_balance SET balance = balance + ?, upddate = ? WHERE member_srl = ? AND balance >= ?',
				$amount, Base::now(), $member_srl, -$amount
			);
			if (!$stmt || $stmt->rowCount() !== 1)
			{
				// 잔액 부족 — 있는 만큼만 회수
				$current = self::balanceOf($member_srl);
				if ($current <= 0)
				{
					return false;
				}
				$amount = -$current;
				$db->query(
					'UPDATE reservation_credit_balance SET balance = 0, upddate = ? WHERE member_srl = ?',
					Base::now(), $member_srl
				);
			}
		}
		else
		{
			$db->query(
				'UPDATE reservation_credit_balance SET balance = balance + ?, upddate = ? WHERE member_srl = ?',
				$amount, Base::now(), $member_srl
			);
		}

		self::log($member_srl, $amount, $type, $booking_srl, $memo);
		return true;
	}

	/**
	 * 사용 (원자 차감 — 잔액 부족이면 실패).
	 *
	 * @param int $member_srl
	 * @param int $amount 양수
	 * @param int $booking_srl
	 * @return bool
	 */
	public static function spend(int $member_srl, int $amount, int $booking_srl): bool
	{
		if ($member_srl <= 0 || $amount <= 0)
		{
			return false;
		}

		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'UPDATE reservation_credit_balance SET balance = balance - ?, upddate = ? WHERE member_srl = ? AND balance >= ?',
			$amount, Base::now(), $member_srl, $amount
		);
		if (!$stmt || $stmt->rowCount() !== 1)
		{
			return false;
		}

		self::log($member_srl, -$amount, 'spend', $booking_srl);
		return true;
	}

	/**
	 * 이 예약에서 쓸 수 있는 최대 적립금.
	 *
	 * 잔액이 아무리 많아도 시술비를 넘겨 쓸 수는 없고, 설정한 1회 사용 한도도 따른다.
	 *
	 * @param int $member_srl
	 * @param int $amount 할인 뒤 시술 금액
	 * @return int
	 */
	public static function usableFor(int $member_srl, int $amount): int
	{
		$balance = self::balanceOf($member_srl);
		if ($balance <= 0 || $amount <= 0)
		{
			return 0;
		}

		$config = Config::getConfig();
		if ((string)($config->credit_enabled ?? 'N') !== 'Y')
		{
			return 0;
		}

		$max = $amount;

		// 1회 최대 사용률 (%). 0 이면 제한하지 않는다
		$rate = (float)($config->credit_max_use_rate ?? 0);
		if ($rate > 0)
		{
			$max = min($max, (int)floor($amount * $rate / 100));
		}

		$usable = min($balance, $max);

		// 최소 사용 단위에 못 미치면 아예 못 쓴다
		$min = (int)($config->credit_min_use ?? 0);
		if ($min > 0 && $usable < $min)
		{
			return 0;
		}

		return max(0, $usable);
	}

	/**
	 * 방문 완료 적립 — 실제로 낸 금액(할인·적립금 사용을 뺀 값) 기준.
	 *
	 * 예약만 하고 오지 않으면 적립하지 않는다. 그래서 confirmed 가 아니라 done 에서 부른다.
	 *
	 * @param object $booking
	 * @return int 적립한 금액
	 */
	public static function earnForBooking(object $booking): int
	{
		$member_srl = (int)$booking->member_srl;
		if ($member_srl <= 0)
		{
			return 0;
		}

		$config = Config::getConfig();
		if ((string)($config->credit_enabled ?? 'N') !== 'Y')
		{
			return 0;
		}

		// 이미 적립한 예약을 두 번 적립하지 않는다
		if ((int)($booking->credit_earned ?? 0) > 0)
		{
			return 0;
		}

		$rate = Grade::creditRateFor($member_srl);
		if ($rate <= 0)
		{
			return 0;
		}

		$base = max(0, (int)$booking->amount - (int)($booking->discount_amount ?? 0) - (int)($booking->credit_used ?? 0));
		$earn = (int)floor($base * $rate / 100);
		if ($earn <= 0)
		{
			return 0;
		}

		self::add($member_srl, $earn, 'earn', (int)$booking->booking_srl);
		return $earn;
	}

	/**
	 * 예약 취소 정산 — 사용분 환불 + 적립분 회수.
	 *
	 * @param object $booking
	 * @return void
	 */
	public static function settleCancel(object $booking): void
	{
		$member_srl = (int)$booking->member_srl;
		if ($member_srl <= 0)
		{
			return;
		}

		$booking_srl = (int)$booking->booking_srl;

		$used = (int)($booking->credit_used ?? 0);
		if ($used > 0)
		{
			self::add($member_srl, $used, 'refund', $booking_srl);
		}

		// 적립분 회수 (이 예약의 earn 합계)
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'SELECT COALESCE(SUM(amount), 0) AS s FROM reservation_credit_log WHERE booking_srl = ? AND type = ?',
			$booking_srl, 'earn'
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		$earned = $row ? (int)$row->s : 0;
		if ($earned > 0)
		{
			self::add($member_srl, -$earned, 'earn_cancel', $booking_srl);
		}
	}

	/**
	 * 이력 목록.
	 *
	 * @param int $member_srl
	 * @param int $list_count
	 * @return array
	 */
	public static function getLogs(int $member_srl, int $list_count = 50): array
	{
		if ($member_srl <= 0)
		{
			return [];
		}

		$output = executeQueryArray('reservation.getCreditLogs', (object)[
			'member_srl' => $member_srl,
			'list_count' => $list_count,
		]);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		return array_values($output->data);
	}

	/**
	 * 원장 기록.
	 *
	 * @param int $member_srl
	 * @param int $amount
	 * @param string $type
	 * @param int $booking_srl
	 * @param string $memo
	 * @return void
	 */
	protected static function log(int $member_srl, int $amount, string $type, int $booking_srl = 0, string $memo = ''): void
	{
		executeQuery('reservation.insertCreditLog', (object)[
			'log_srl' => getNextSequence(),
			'member_srl' => $member_srl,
			'booking_srl' => $booking_srl,
			'amount' => $amount,
			'balance_after' => self::balanceOf($member_srl),
			'type' => $type,
			'memo' => mb_substr($memo, 0, 250),
			'regdate' => Base::now(),
		]);
	}
}
