<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 담당자 정산.
 *
 * 집계 대상은 이용을 마친 예약(done)뿐이다. 취소와 노쇼는 매출로 잡지 않는다.
 *
 * 금액과 배분율은 예약 행에 박아 둔 예약 시점 값을 쓴다. 담당자 배분율을
 * 나중에 바꿔도 지난 정산이 흔들리지 않아야 하기 때문이다. 확정한 회차는
 * 항목을 다시 계산하지 않는다.
 */
class Settlement
{
	public const STATUS_DRAFT = 'draft';
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_PAID = 'paid';

	public const STATUSES = [self::STATUS_DRAFT, self::STATUS_CONFIRMED, self::STATUS_PAID];

	/**
	 * 확정된 회차는 손대지 않는다.
	 */
	public const LOCKED_STATUSES = [self::STATUS_CONFIRMED, self::STATUS_PAID];

	/**
	 * 정산 회차 하나.
	 *
	 * @param int $settlement_srl
	 * @return ?object
	 */
	public static function get(int $settlement_srl): ?object
	{
		if ($settlement_srl <= 0)
		{
			return null;
		}

		$output = executeQuery('reservation.getSettlement', (object)['settlement_srl' => $settlement_srl]);
		if (!$output->toBool() || !is_object($output->data))
		{
			return null;
		}

		return $output->data;
	}

	/**
	 * 정산 회차 목록.
	 *
	 * @param int $module_srl
	 * @param array $filters staff_srl, status, from_date, to_date, page
	 * @return array list 와 navigation
	 */
	public static function getList(int $module_srl, array $filters = []): array
	{
		$args = new \stdClass;
		$args->module_srl = $module_srl;
		$args->list_count = 20;
		$args->page_count = 10;
		$args->page = max(1, (int)($filters['page'] ?? 1));

		foreach (['staff_srl', 'status', 'from_date', 'to_date'] as $key)
		{
			if (!empty($filters[$key]))
			{
				$args->{$key} = $filters[$key];
			}
		}

		$output = executeQueryArray('reservation.getSettlementList', $args);

		return [
			'list' => ($output->toBool() && is_array($output->data)) ? $output->data : [],
			'navigation' => $output->page_navigation ?? null,
		];
	}

	/**
	 * 아직 정산에 들어가지 않은 예약을 모아 회차를 만든다.
	 *
	 * @param int $module_srl
	 * @param int $staff_srl
	 * @param string $from YYYYMMDD
	 * @param string $to YYYYMMDD
	 * @return int 만들어진 settlement_srl. 대상이 없으면 0
	 */
	public static function build(int $module_srl, int $staff_srl, string $from, string $to): int
	{
		if ($staff_srl <= 0 || !preg_match('/^\d{8}$/', $from) || !preg_match('/^\d{8}$/', $to) || $from > $to)
		{
			return 0;
		}

		$targets = self::getTargets($module_srl, $staff_srl, $from, $to);
		if (!count($targets))
		{
			return 0;
		}

		$now = date('YmdHis');
		$settlement_srl = getNextSequence();

		$gross = 0;
		$share = 0;
		$items = [];
		foreach ($targets as $booking)
		{
			$amount = (int)$booking->amount;
			$rate = (int)$booking->share_rate_snapshot;
			if ($rate < 0)
			{
				$rate = 0;
			}
			$share_amount = Staff::shareAmount($amount, $rate);

			$gross += $amount;
			$share += $share_amount;

			$items[] = (object)[
				'item_srl' => getNextSequence(),
				'settlement_srl' => $settlement_srl,
				'booking_srl' => (int)$booking->booking_srl,
				'resource_srl' => (int)$booking->resource_srl,
				'amount' => $amount,
				'share_rate' => $rate,
				'share_amount' => $share_amount,
				'service_date' => (string)$booking->service_date,
				'regdate' => $now,
			];
		}

		$args = new \stdClass;
		$args->settlement_srl = $settlement_srl;
		$args->module_srl = $module_srl;
		$args->staff_srl = $staff_srl;
		$args->period_from = $from;
		$args->period_to = $to;
		$args->booking_count = count($items);
		$args->gross_amount = $gross;
		$args->share_amount = $share;
		$args->store_amount = $gross - $share;
		$args->status = self::STATUS_DRAFT;
		$args->regdate = $now;

		$output = executeQuery('reservation.insertSettlement', $args);
		if (!$output->toBool())
		{
			return 0;
		}

		foreach ($items as $item)
		{
			$output = executeQuery('reservation.insertSettlementItem', $item);
			if (!$output->toBool())
			{
				self::remove($settlement_srl);
				return 0;
			}

			$output = executeQuery('reservation.updateBookingSettlement', (object)[
				'booking_srl' => $item->booking_srl,
				'settlement_srl' => $settlement_srl,
			]);
			if (!$output->toBool())
			{
				self::remove($settlement_srl);
				return 0;
			}
		}

		return $settlement_srl;
	}

	/**
	 * 아직 어느 회차에도 들어가지 않은, 이용을 마친 예약.
	 *
	 * @param int $module_srl
	 * @param int $staff_srl
	 * @param string $from
	 * @param string $to
	 * @return array
	 */
	public static function getTargets(int $module_srl, int $staff_srl, string $from, string $to): array
	{
		$oDB = \Rhymix\Framework\DB::getInstance();
		$prefix = \Rhymix\Framework\Config::get('db.master.prefix') ?: '';

		// 예약 행은 실제 인스턴스 번호로 저장되고 담당자는 0 으로 매달린다.
		// 둘을 같이 걸면 아무것도 안 나온다. 담당자만으로도 범위는 충분하다.
		$sql = 'SELECT booking_srl, resource_srl, amount, share_rate_snapshot, service_date' .
			' FROM `' . $prefix . 'reservation_booking`' .
			' WHERE staff_srl = ? AND status = ? AND settlement_srl = 0' .
			' AND service_date >= ? AND service_date <= ?' .
			' ORDER BY service_date ASC, booking_srl ASC';

		try
		{
			$stmt = $oDB->getHandle()->prepare($sql);
			$stmt->execute([$staff_srl, Base::STATUS_DONE, $from, $to]);
			$rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
		}
		catch (\Throwable $e)
		{
			return [];
		}

		return is_array($rows) ? $rows : [];
	}

	/**
	 * 회차에 들어간 예약 항목.
	 *
	 * @param int $settlement_srl
	 * @return array
	 */
	public static function getItems(int $settlement_srl): array
	{
		if ($settlement_srl <= 0)
		{
			return [];
		}

		$output = executeQueryArray('reservation.getSettlementItemList', (object)['settlement_srl' => $settlement_srl]);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		return $output->data;
	}

	/**
	 * 회차 상태를 옮긴다. 되돌리기는 확정에서 집계중으로만 허용한다.
	 *
	 * @param int $settlement_srl
	 * @param string $status
	 * @return bool
	 */
	public static function changeStatus(int $settlement_srl, string $status): bool
	{
		if (!in_array($status, self::STATUSES, true))
		{
			return false;
		}

		$settlement = self::get($settlement_srl);
		if (!$settlement)
		{
			return false;
		}

		// 지급까지 끝난 회차는 더 옮기지 않는다. 장부가 흔들린다
		if ((string)$settlement->status === self::STATUS_PAID && $status !== self::STATUS_PAID)
		{
			return false;
		}

		$args = new \stdClass;
		$args->settlement_srl = $settlement_srl;
		$args->status = $status;
		if ($status === self::STATUS_CONFIRMED)
		{
			$args->confirmed_date = date('YmdHis');
		}
		if ($status === self::STATUS_PAID)
		{
			$args->paid_date = date('YmdHis');
		}

		$output = executeQuery('reservation.updateSettlement', $args);
		return $output->toBool();
	}

	/**
	 * 집계중인 회차를 지운다. 물린 예약은 다시 정산 대상으로 돌아간다.
	 *
	 * @param int $settlement_srl
	 * @return bool
	 */
	public static function remove(int $settlement_srl): bool
	{
		if ($settlement_srl <= 0)
		{
			return false;
		}

		$settlement = self::get($settlement_srl);
		if ($settlement && in_array((string)$settlement->status, self::LOCKED_STATUSES, true))
		{
			return false;
		}

		executeQuery('reservation.clearBookingSettlement', (object)[
			'old_settlement_srl' => $settlement_srl,
			'settlement_srl' => 0,
		]);
		executeQuery('reservation.deleteSettlementItems', (object)['settlement_srl' => $settlement_srl]);

		$output = executeQuery('reservation.deleteSettlement', (object)['settlement_srl' => $settlement_srl]);
		return $output->toBool();
	}
}
