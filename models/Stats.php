<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 지점별·담당자별 예약 현황과 매출.
 *
 * 확정과 이용 완료 예약만 센다. 취소와 노쇼는 매출이 아니다.
 */
class Stats
{
	/**
	 * 기간 안의 지점별·담당자별 집계.
	 *
	 * 지점 기록이 없는 예약(1.0.3 이전)은 담당자의 소속 지점으로 센다.
	 *
	 * @param int $module_srl
	 * @param string $from YYYYMMDD
	 * @param string $to YYYYMMDD
	 * @return array{branches: array, staff: array}
	 */
	public static function breakdown(int $module_srl, string $from, string $to): array
	{
		$result = ['branches' => [], 'staff' => []];
		$prefix = \Rhymix\Framework\Config::get('db.master.prefix') ?: '';
		$sql = 'SELECT branch_srl, staff_srl, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total' .
			' FROM `' . $prefix . 'reservation_booking`' .
			' WHERE status IN (?, ?) AND service_date >= ? AND service_date <= ?' .
			' GROUP BY branch_srl, staff_srl';

		try
		{
			$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare($sql);
			$stmt->execute([Base::STATUS_CONFIRMED, Base::STATUS_DONE, $from, $to]);
			$rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
		}
		catch (\Throwable $e)
		{
			return $result;
		}

		$staff_names = [];
		$staff_branch = [];
		foreach (Staff::getList($module_srl, null) as $staff)
		{
			$staff_names[(int)$staff->staff_srl] = (string)$staff->name;
			$staff_branch[(int)$staff->staff_srl] = (int)$staff->branch_srl;
		}

		foreach ($rows as $row)
		{
			$staff_srl = (int)$row->staff_srl;
			$branch_srl = (int)$row->branch_srl ?: ($staff_branch[$staff_srl] ?? 0);
			$count = (int)$row->cnt;
			$total = (int)$row->total;

			$result['branches'][$branch_srl] = self::add($result['branches'][$branch_srl] ?? null, $branch_srl, $count, $total);
			$result['staff'][$staff_srl] = self::add($result['staff'][$staff_srl] ?? null, $staff_srl, $count, $total);
		}

		foreach ($result['branches'] as $branch_srl => $item)
		{
			$branch = BranchLink::get((int)$branch_srl);
			$item->name = $branch ? (string)($branch->name ?? '') : '';
		}
		foreach ($result['staff'] as $staff_srl => $item)
		{
			$item->name = $staff_names[(int)$staff_srl] ?? '';
		}

		$by_total = static function (object $a, object $b): int {
			return $b->total <=> $a->total ?: $b->count <=> $a->count;
		};
		$result['branches'] = array_values($result['branches']);
		$result['staff'] = array_values($result['staff']);
		usort($result['branches'], $by_total);
		usort($result['staff'], $by_total);

		return $result;
	}

	/**
	 * 한 줄에 건수와 금액을 더한다.
	 *
	 * @param ?object $item
	 * @param int $srl
	 * @param int $count
	 * @param int $total
	 * @return object
	 */
	protected static function add(?object $item, int $srl, int $count, int $total): object
	{
		if (!$item)
		{
			$item = (object)['srl' => $srl, 'name' => '', 'count' => 0, 'total' => 0];
		}
		$item->count += $count;
		$item->total += $total;
		return $item;
	}
}
