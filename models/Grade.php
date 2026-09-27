<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 단골 등급.
 *
 * 코어 회원그룹과는 별개다. 누적 이용 금액으로 구간을 나누고, 구간마다
 * 적립률·시술 할인·달성 쿠폰을 다르게 준다.
 *
 * 누적 금액은 방문 완료(done) 예약의 실제 지불액만 센다. 예약만 하고 오지 않은
 * 건까지 세면 등급이 실제 매출과 어긋난다.
 */
class Grade
{
	/**
	 * 등급 목록. 기준 금액 오름차순.
	 *
	 * @param int $module_srl
	 * @return array
	 */
	public static function getList(int $module_srl = 0): array
	{
		$output = executeQueryArray('reservation.getGradeList', (object)['module_srl' => $module_srl]);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		return array_values($output->data);
	}

	/**
	 * 등급 하나.
	 *
	 * @param int $grade_srl
	 * @return ?object
	 */
	public static function get(int $grade_srl): ?object
	{
		if ($grade_srl <= 0)
		{
			return null;
		}

		$output = executeQuery('reservation.getGrade', (object)['grade_srl' => $grade_srl]);
		return ($output->toBool() && !empty($output->data->grade_srl)) ? $output->data : null;
	}

	/**
	 * 회원의 현재 등급 (없으면 null).
	 *
	 * @param int $member_srl
	 * @return ?object
	 */
	public static function getForMember(int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}

		$output = executeQuery('reservation.getMemberGrade', (object)['member_srl' => $member_srl]);
		if (!$output->toBool() || empty($output->data->member_srl))
		{
			return null;
		}

		$row = $output->data;
		$grade = self::get((int)$row->grade_srl);
		if (!$grade)
		{
			return null;
		}

		$grade->total_spend = (int)$row->total_spend;
		return $grade;
	}

	/**
	 * 누적 이용 금액을 다시 세고 등급을 맞춘다.
	 *
	 * 방문 완료 처리와 취소 처리 뒤에 부른다.
	 *
	 * @param int $member_srl
	 * @return void
	 */
	public static function recalc(int $member_srl): void
	{
		if ($member_srl <= 0)
		{
			return;
		}

		$grades = self::getList();
		if (!count($grades))
		{
			return;
		}

		$db = \Zittme\Framework\DB::getInstance();

		// 실제로 낸 금액만 센다. 할인과 적립금 사용은 매출이 아니다
		$stmt = $db->query(
			'SELECT COALESCE(SUM(GREATEST(amount - discount_amount - credit_used, 0)), 0) AS s'
			. ' FROM reservation_booking WHERE member_srl = ? AND status = ?',
			$member_srl, Base::STATUS_DONE
		);
		$row = $stmt ? $stmt->fetchObject() : null;
		if ($stmt)
		{
			$stmt->closeCursor();
		}
		$total = $row ? (int)$row->s : 0;

		// 가장 높은 구간 (목록은 기준 금액 오름차순)
		$new_grade = null;
		foreach ($grades as $grade)
		{
			if ($total >= (int)$grade->min_spend)
			{
				$new_grade = $grade;
			}
		}

		$current = self::getForMember($member_srl);
		$was_srl = $current ? (int)$current->grade_srl : 0;
		$new_srl = $new_grade ? (int)$new_grade->grade_srl : 0;

		try
		{
			$db->query(
				'INSERT INTO reservation_member_grade (member_srl, grade_srl, total_spend, upddate) VALUES (?, ?, ?, ?)',
				$member_srl, $new_srl, $total, Base::now()
			);
		}
		catch (\Exception $e)
		{
			$db->query(
				'UPDATE reservation_member_grade SET grade_srl = ?, total_spend = ?, upddate = ? WHERE member_srl = ?',
				$new_srl, $total, Base::now(), $member_srl
			);
		}

		// 등급이 올라가면 달성 쿠폰을 준다. 같은 쿠폰을 두 번 주지는 않는다
		if ($new_grade && $new_srl !== $was_srl && (int)$new_grade->coupon_srl > 0)
		{
			Coupon::issueOnce((int)$new_grade->coupon_srl, $member_srl);
		}
	}

	/**
	 * 회원에게 적용되는 적립률 % — 등급 적립률이 있으면 그쪽, 없으면 기본 설정.
	 *
	 * @param int $member_srl
	 * @return float
	 */
	public static function creditRateFor(int $member_srl): float
	{
		$grade = self::getForMember($member_srl);
		if ($grade && (float)$grade->credit_rate > 0)
		{
			return round((float)$grade->credit_rate, 2);
		}

		return max(0, round((float)(Config::getConfig()->credit_rate ?? 0), 2));
	}

	/**
	 * 회원의 등급 할인 (없으면 null).
	 *
	 * @param int $member_srl
	 * @return ?object {type, value}
	 */
	public static function discountFor(int $member_srl): ?object
	{
		$grade = self::getForMember($member_srl);
		if (!$grade)
		{
			return null;
		}

		$type = (string)($grade->discount_type ?? '');
		$value = (float)($grade->discount_value ?? 0);
		if (!in_array($type, ['amount', 'percent'], true) || $value <= 0)
		{
			return null;
		}

		return (object)['type' => $type, 'value' => $value];
	}

	/**
	 * 금액에 등급 할인을 적용한 뒤 값. 0 아래로는 내려가지 않는다.
	 *
	 * @param int $amount
	 * @param ?object $discount discountFor() 결과
	 * @return int
	 */
	public static function applyDiscount(int $amount, ?object $discount): int
	{
		if (!$discount || $amount <= 0)
		{
			return max(0, $amount);
		}

		if ($discount->type === 'amount')
		{
			return max(0, $amount - (int)$discount->value);
		}

		return max(0, $amount - (int)floor($amount * $discount->value / 100));
	}
}
