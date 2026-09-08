<?php

namespace Zittme\Modules\Reservation\Models;

/**
 * 담당자 모드의 점유 — 예약의 단일 진실 공급원.
 *
 * 슬롯 모드에서 조건부 UPDATE 가 하던 일을 여기서는 유일 인덱스가 한다.
 * 예약이 차지하는 시간을 칸(slot_unit 분)으로 쪼개 (staff_srl, cell) 로 넣고,
 * 한 칸이라도 이미 차 있으면 INSERT 가 중복 키로 실패한다.
 *
 * 절대로 "조회해서 비었으면 넣는다" 로 바꾸지 말 것. 조회와 삽입 사이에
 * 다른 요청이 끼어들어 같은 시간에 두 손님이 잡힌다.
 */
class Occupancy
{
	/**
	 * 구간을 점유한다. 한 칸이라도 겹치면 통째로 실패한다.
	 *
	 * @param int $staff_srl
	 * @param int $booking_srl
	 * @param string $start_datetime YYYYMMDDHHIISS
	 * @param int $minutes 버퍼를 포함한 점유 길이
	 * @return bool
	 */
	public static function reserve(int $staff_srl, int $booking_srl, string $start_datetime, int $minutes): bool
	{
		if ($staff_srl <= 0 || $booking_srl <= 0 || $minutes <= 0)
		{
			return false;
		}

		$cells = self::buildCells($start_datetime, $minutes);
		if (!count($cells))
		{
			return false;
		}

		$oDB = \Rhymix\Framework\DB::getInstance();
		$prefix = \Rhymix\Framework\Config::get('db.master.prefix') ?: '';
		$now = date('YmdHis');

		$values = [];
		$params = [];
		foreach ($cells as $cell)
		{
			$values[] = '(?, ?, ?, ?, ?)';
			$params[] = getNextSequence();
			$params[] = $staff_srl;
			$params[] = $cell;
			$params[] = $booking_srl;
			$params[] = $now;
		}

		$sql = 'INSERT INTO `' . $prefix . 'reservation_occupancy`' .
			' (occupancy_srl, staff_srl, cell, booking_srl, regdate) VALUES ' . implode(', ', $values);

		try
		{
			$stmt = $oDB->getHandle()->prepare($sql);
			$stmt->execute($params);
		}
		catch (\Throwable $e)
		{
			// 중복 키 = 그 사이에 다른 손님이 잡았다. 부분 삽입은 남지 않는다
			self::release($booking_srl);
			return false;
		}

		return true;
	}

	/**
	 * 예약이 잡고 있던 칸을 모두 돌려준다. 취소·만료·거절에서 부른다.
	 *
	 * @param int $booking_srl
	 * @return bool
	 */
	public static function release(int $booking_srl): bool
	{
		if ($booking_srl <= 0)
		{
			return false;
		}

		$output = executeQuery('reservation.deleteOccupancyByBooking', (object)['booking_srl' => $booking_srl]);
		return $output->toBool();
	}

	/**
	 * 담당자의 그날 점유 칸.
	 *
	 * @param int $staff_srl
	 * @param string $date YYYYMMDD
	 * @return array cell 문자열 목록
	 */
	public static function getCellsOfDay(int $staff_srl, string $date): array
	{
		if ($staff_srl <= 0 || !preg_match('/^\d{8}$/', $date))
		{
			return [];
		}

		$oDB = \Rhymix\Framework\DB::getInstance();
		$prefix = \Rhymix\Framework\Config::get('db.master.prefix') ?: '';

		$sql = 'SELECT cell FROM `' . $prefix . 'reservation_occupancy`' .
			' WHERE staff_srl = ? AND cell LIKE ?';

		try
		{
			$stmt = $oDB->getHandle()->prepare($sql);
			$stmt->execute([$staff_srl, $date . '%']);
			$rows = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);
		}
		catch (\Throwable $e)
		{
			return [];
		}

		return is_array($rows) ? $rows : [];
	}

	/**
	 * 시작 시각과 길이로 칸 목록을 만든다.
	 *
	 * 점유는 칸 단위라 시작이 칸 경계에 없으면 그 칸 전체를 차지한다.
	 * 끝나는 시각도 같은 이유로 올림한다. 반 칸만 남기면 그 자리에 아무도
	 * 못 들어가면서 비어 있는 것처럼 보인다.
	 *
	 * @param string $start_datetime
	 * @param int $minutes
	 * @return array
	 */
	public static function buildCells(string $start_datetime, int $minutes): array
	{
		if (!preg_match('/^\d{14}$/', $start_datetime) || $minutes <= 0)
		{
			return [];
		}

		$config = Config::getConfig();
		$unit = max(5, (int)($config->slot_unit ?? 10));

		$start = strtotime(
			substr($start_datetime, 0, 4) . '-' . substr($start_datetime, 4, 2) . '-' . substr($start_datetime, 6, 2) . ' ' .
			substr($start_datetime, 8, 2) . ':' . substr($start_datetime, 10, 2) . ':' . substr($start_datetime, 12, 2)
		);
		if ($start === false)
		{
			return [];
		}

		// 칸 경계로 내림한 시각부터 시작한다
		$day_start = strtotime(date('Y-m-d 00:00:00', $start));
		$offset = (int)floor(($start - $day_start) / 60);
		$first = (int)floor($offset / $unit) * $unit;
		$last = (int)ceil(($offset + $minutes) / $unit) * $unit;

		$cells = [];
		for ($cursor = $first; $cursor < $last; $cursor += $unit)
		{
			$cells[] = date('YmdHi', $day_start + $cursor * 60);
		}

		return $cells;
	}
}
