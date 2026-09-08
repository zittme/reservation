<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 쿠폰.
 *
 * 정의(reservation_coupon)와 발급 이력(reservation_coupon_issue)을 나눈다.
 * code 가 있는 쿠폰은 예약서에서 코드를 적어 바로 쓰고, code 가 없는 쿠폰은
 * 관리자 발급이나 등급 달성으로만 손에 들어온다.
 *
 * 사용은 발급 건을 조건부 UPDATE 로 원자 점유해서 한다. 한 발급 건이 두 예약에
 * 쓰이는 일을 막는 자리가 여기다.
 */
class Coupon
{
	/**
	 * 쿠폰 하나.
	 *
	 * @param int $coupon_srl
	 * @return ?object
	 */
	public static function get(int $coupon_srl): ?object
	{
		if ($coupon_srl <= 0)
		{
			return null;
		}

		$output = executeQuery('reservation.getCoupon', (object)['coupon_srl' => $coupon_srl]);
		return ($output->toBool() && !empty($output->data->coupon_srl)) ? $output->data : null;
	}

	/**
	 * 코드로 찾기.
	 *
	 * @param string $code
	 * @return ?object
	 */
	public static function getByCode(string $code): ?object
	{
		$code = trim($code);
		if ($code === '')
		{
			return null;
		}

		$output = executeQuery('reservation.getCouponByCode', (object)['code' => $code]);
		return ($output->toBool() && !empty($output->data->coupon_srl)) ? $output->data : null;
	}

	/**
	 * 쿠폰 목록.
	 *
	 * @param int $module_srl
	 * @return array
	 */
	public static function getList(int $module_srl = 0): array
	{
		$output = executeQueryArray('reservation.getCouponList', (object)['module_srl' => $module_srl]);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		return array_values($output->data);
	}

	/**
	 * 지금 쓸 수 있는 기간인가.
	 *
	 * @param object $coupon
	 * @return bool
	 */
	public static function isUsableNow(object $coupon): bool
	{
		if ((string)($coupon->status ?? 'Y') !== 'Y')
		{
			return false;
		}

		$now = Base::now();
		if (!empty($coupon->use_start) && $now < $coupon->use_start)
		{
			return false;
		}
		if (!empty($coupon->use_end) && $now > $coupon->use_end)
		{
			return false;
		}

		return true;
	}

	/**
	 * 할인액. 조건에 안 맞으면 null.
	 *
	 * @param object $coupon
	 * @param int $amount 시술 금액
	 * @param int $resource_srl 어떤 시술에 쓰는가
	 * @return ?int
	 */
	public static function discountFor(object $coupon, int $amount, int $resource_srl = 0): ?int
	{
		if ($amount <= 0 || $amount < (int)($coupon->min_amount ?? 0))
		{
			return null;
		}

		// 시술을 지정한 쿠폰은 그 시술에서만 쓴다
		$only = (int)($coupon->resource_srl ?? 0);
		if ($only > 0 && $resource_srl > 0 && $only !== $resource_srl)
		{
			return null;
		}

		if ((string)($coupon->discount_type ?? 'fixed') === 'percent')
		{
			$discount = (int)floor($amount * (int)$coupon->discount_value / 100);
			$cap = (int)($coupon->max_discount ?? 0);
			if ($cap > 0)
			{
				$discount = min($discount, $cap);
			}
		}
		else
		{
			$discount = (int)$coupon->discount_value;
		}

		$discount = min($discount, $amount);
		return $discount > 0 ? $discount : null;
	}

	/**
	 * 회원에게 발급.
	 *
	 * @param int $coupon_srl
	 * @param int $member_srl
	 * @param int $booking_srl 즉시 사용이면 예약번호
	 * @return int issue_srl (실패 시 0)
	 */
	public static function issueTo(int $coupon_srl, int $member_srl, int $booking_srl = 0): int
	{
		if ($coupon_srl <= 0 || $member_srl <= 0)
		{
			return 0;
		}

		$issue_srl = getNextSequence();
		$output = executeQuery('reservation.insertCouponIssue', (object)[
			'issue_srl' => $issue_srl,
			'coupon_srl' => $coupon_srl,
			'member_srl' => $member_srl,
			'booking_srl' => $booking_srl,
			'regdate' => Base::now(),
			'used_date' => $booking_srl > 0 ? Base::now() : '',
		]);

		return $output->toBool() ? $issue_srl : 0;
	}

	/**
	 * 같은 쿠폰을 받은 적이 없을 때만 발급. 등급 달성 쿠폰이 매번 쌓이지 않게 한다.
	 *
	 * @param int $coupon_srl
	 * @param int $member_srl
	 * @return int
	 */
	public static function issueOnce(int $coupon_srl, int $member_srl): int
	{
		if (self::countIssues($coupon_srl, $member_srl) > 0)
		{
			return 0;
		}

		return self::issueTo($coupon_srl, $member_srl);
	}

	/**
	 * 회원이 이 쿠폰을 받은 횟수.
	 *
	 * @param int $coupon_srl
	 * @param int $member_srl
	 * @return int
	 */
	public static function countIssues(int $coupon_srl, int $member_srl): int
	{
		$output = executeQuery('reservation.countCouponIssues', (object)[
			'coupon_srl' => $coupon_srl,
			'member_srl' => $member_srl,
		]);

		return $output->toBool() ? (int)($output->data->count ?? 0) : 0;
	}

	/**
	 * 이 예약에 쓸 수 있는 회원의 미사용 쿠폰 — 예상 할인액까지 붙여서.
	 *
	 * @param int $member_srl
	 * @param int $amount
	 * @param int $resource_srl
	 * @return array [{issue_srl, coupon, discount}]
	 */
	public static function listUsableForMember(int $member_srl, int $amount, int $resource_srl = 0): array
	{
		if ($member_srl <= 0)
		{
			return [];
		}

		$output = executeQueryArray('reservation.getMyCouponIssues', (object)['member_srl' => $member_srl, 'booking_srl' => 0]);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$result = [];
		$cache = [];

		foreach ($output->data as $issue)
		{
			// 이미 쓴 발급 건은 뺀다
			if ((int)($issue->booking_srl ?? 0) > 0)
			{
				continue;
			}

			$srl = (int)$issue->coupon_srl;
			if (!array_key_exists($srl, $cache))
			{
				$cache[$srl] = self::get($srl);
			}

			$coupon = $cache[$srl];
			if (!$coupon || !self::isUsableNow($coupon))
			{
				continue;
			}

			$discount = self::discountFor($coupon, $amount, $resource_srl);
			if ($discount === null)
			{
				continue;
			}

			$result[] = (object)[
				'issue_srl' => (int)$issue->issue_srl,
				'coupon' => $coupon,
				'discount' => $discount,
			];
		}

		return $result;
	}

	/**
	 * 회원이 가진 미사용 쿠폰 — 금액과 무관하게 보여 주기만 할 때.
	 *
	 * 내 예약 화면의 쿠폰함이 이걸 쓴다. 예상 할인액은 예약할 시술이 정해져야 나오므로
	 * 여기서는 쿠폰 자체만 돌려준다.
	 *
	 * @param int $member_srl
	 * @return array
	 */
	public static function listMine(int $member_srl): array
	{
		if ($member_srl <= 0)
		{
			return [];
		}

		$output = executeQueryArray('reservation.getMyCouponIssues', (object)['member_srl' => $member_srl, 'booking_srl' => 0]);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$result = [];
		foreach ($output->data as $issue)
		{
			if ((int)($issue->booking_srl ?? 0) > 0)
			{
				continue;
			}

			$coupon = self::get((int)$issue->coupon_srl);
			if (!$coupon || !self::isUsableNow($coupon))
			{
				continue;
			}

			$result[] = (object)['issue_srl' => (int)$issue->issue_srl, 'coupon' => $coupon];
		}

		return $result;
	}

	/**
	 * 발급 쿠폰을 예약에 사용 (원자 점유).
	 *
	 * @param int $issue_srl
	 * @param int $member_srl
	 * @param int $booking_srl
	 * @return bool 점유에 이겼는가
	 */
	public static function claimIssue(int $issue_srl, int $member_srl, int $booking_srl): bool
	{
		$issue = self::getIssue($issue_srl);
		if (!$issue || (int)$issue->member_srl !== $member_srl)
		{
			return false;
		}

		$output = executeQuery('reservation.useCouponIssueIf', (object)[
			'issue_srl' => $issue_srl,
			'member_srl' => $member_srl,
			'from_booking_srl' => 0,
			'booking_srl' => $booking_srl,
			'used_date' => Base::now(),
		]);
		if (!$output->toBool() || \DB::getInstance()->getAffectedRows() < 1)
		{
			return false;
		}

		\Zittme\Framework\DB::getInstance()->query(
			'UPDATE reservation_coupon SET used_count = used_count + 1 WHERE coupon_srl = ?',
			(int)$issue->coupon_srl
		);

		return true;
	}

	/**
	 * 코드 쿠폰 즉시 사용 — 한도와 횟수를 보고 사용 상태의 발급 건을 만든다.
	 *
	 * @param string $code
	 * @param int $member_srl
	 * @param int $booking_srl
	 * @param int $amount
	 * @param int $resource_srl
	 * @return object {success, message?, discount?, issue_srl?, coupon?}
	 */
	public static function redeemCode(string $code, int $member_srl, int $booking_srl, int $amount, int $resource_srl = 0): object
	{
		$coupon = self::getByCode($code);
		if (!$coupon || empty($coupon->code) || !self::isUsableNow($coupon))
		{
			return (object)['success' => false, 'message' => 'msg_reservation_coupon_invalid'];
		}

		$discount = self::discountFor($coupon, $amount, $resource_srl);
		if ($discount === null)
		{
			return (object)['success' => false, 'message' => 'msg_reservation_coupon_not_applicable'];
		}

		$per = max(1, (int)($coupon->per_member ?? 1));
		if (self::countIssues((int)$coupon->coupon_srl, $member_srl) >= $per)
		{
			return (object)['success' => false, 'message' => 'msg_reservation_coupon_used'];
		}

		// 전체 한도 — 칼럼끼리 비교라 조건부 UPDATE 로 원자 점유한다
		$stmt = \Zittme\Framework\DB::getInstance()->query(
			'UPDATE reservation_coupon SET used_count = used_count + 1 WHERE coupon_srl = ? AND (total_limit = 0 OR used_count < total_limit)',
			(int)$coupon->coupon_srl
		);
		if (!$stmt || $stmt->rowCount() !== 1)
		{
			return (object)['success' => false, 'message' => 'msg_reservation_coupon_soldout'];
		}

		$issue_srl = self::issueTo((int)$coupon->coupon_srl, $member_srl, $booking_srl);
		if (!$issue_srl)
		{
			\Zittme\Framework\DB::getInstance()->query(
				'UPDATE reservation_coupon SET used_count = used_count - 1 WHERE coupon_srl = ? AND used_count > 0',
				(int)$coupon->coupon_srl
			);
			return (object)['success' => false, 'message' => 'msg_reservation_coupon_invalid'];
		}

		return (object)['success' => true, 'discount' => $discount, 'issue_srl' => $issue_srl, 'coupon' => $coupon];
	}

	/**
	 * 발급 1건.
	 *
	 * @param int $issue_srl
	 * @return ?object
	 */
	public static function getIssue(int $issue_srl): ?object
	{
		if ($issue_srl <= 0)
		{
			return null;
		}

		$output = executeQuery('reservation.getCouponIssue', (object)['issue_srl' => $issue_srl]);
		return ($output->toBool() && !empty($output->data->issue_srl)) ? $output->data : null;
	}

	/**
	 * 예약 취소 시 쿠폰을 되돌린다. 다시 쓸 수 있는 상태가 된다.
	 *
	 * @param int $booking_srl
	 * @return void
	 */
	public static function releaseByBooking(int $booking_srl): void
	{
		if ($booking_srl <= 0)
		{
			return;
		}

		$db = \Zittme\Framework\DB::getInstance();
		$stmt = $db->query('SELECT issue_srl, coupon_srl FROM reservation_coupon_issue WHERE booking_srl = ?', $booking_srl);
		$rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_OBJ) : [];
		if (!count($rows))
		{
			return;
		}

		executeQuery('reservation.releaseCouponIssueByBooking', (object)[
			'booking_srl' => $booking_srl,
			'new_booking_srl' => 0,
			'used_date' => '',
		]);

		foreach ($rows as $row)
		{
			$db->query(
				'UPDATE reservation_coupon SET used_count = used_count - 1 WHERE coupon_srl = ? AND used_count > 0',
				(int)$row->coupon_srl
			);
		}
	}
}
