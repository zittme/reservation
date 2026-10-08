<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 담당자 모드의 예약 가능 시간 계산.
 *
 * 슬롯 모드는 슬롯을 미리 찍어 두지만, 시술마다 소요시간이 다른 곳에서는
 * 그 방식이 맞지 않는다. 여기서는 슬롯을 만들지 않고 요청 때마다 계산한다.
 *
 *   근무시간 - 휴무 - 이미 잡힌 구간 - 앞뒤 버퍼 = 남은 구간
 *   그 구간에 이 시술의 소요시간이 통째로 들어가는 시작 시각만 돌려준다
 *
 * 점유 자체는 여기서 하지 않는다. 계산과 점유 사이에 다른 요청이 끼어들 수
 * 있으므로 실제 확보는 Occupancy::reserve() 의 유일 인덱스가 맡는다.
 */
class Availability
{
	/**
	 * 하루치 예약 가능 시작 시각.
	 *
	 * @param object $resource 시술
	 * @param object $staff 담당자
	 * @param string $date YYYYMMDD
	 * @param int $duration 소요시간(분)
	 * @return array HH:MM 목록
	 */
	public static function getTimesForStaff(object $resource, object $staff, string $date, int $duration): array
	{
		$times = [];
		foreach (self::getDayGrid($resource, $staff, $date, $duration) as $row)
		{
			if ($row['open'])
			{
				$times[] = $row['time'];
			}
		}

		return $times;
	}

	/**
	 * 그날 근무시간 안의 모든 시작 시각과 각각의 가능 여부.
	 *
	 * 가능한 시각만 돌려주면 화면에서 "원래 없는 시간" 과 "이미 찬 시간" 을 구분하지
	 * 못한다. 손님은 그 차이를 알아야 다음 선택을 정한다.
	 *
	 * @param object $resource 시술
	 * @param object $staff 담당자
	 * @param string $date YYYYMMDD
	 * @param int $duration 소요시간(분)
	 * @return array time 과 open 을 담은 목록
	 */
	public static function getDayGrid(object $resource, object $staff, string $date, int $duration): array
	{
		$staff_srl = (int)$staff->staff_srl;
		if ($staff_srl <= 0 || $duration <= 0 || !preg_match('/^\d{8}$/', $date))
		{
			return [];
		}

		$config = Config::getConfig();
		$unit = max(5, (int)($config->slot_unit ?? 10));

		$windows = self::getWorkWindows($staff_srl, $date);
		if (!count($windows))
		{
			return [];
		}

		$windows = self::subtractHolidays($windows, $staff_srl, $date);
		if (!count($windows))
		{
			return [];
		}

		// 매장 영업시간 밖은 잘라낸다. 담당자 근무표만 보면 문 닫은 뒤에도 예약이 잡힌다
		$windows = self::clampToBranch($windows, (int)($staff->branch_srl ?? 0), $date);
		if (!count($windows))
		{
			return [];
		}

		// 앞뒤 버퍼는 이 시술이 차지하는 시간에 더한다. 다음 손님과 겹치지 않게 한다
		$before = max(0, (int)($resource->buffer_before ?? 0));
		$after = max(0, (int)($resource->buffer_after ?? 0));
		$busy = self::getBusyRanges($staff_srl, $date);

		$earliest = self::getEarliestMinute($resource, $date);
		$latest = self::getLatestMinute($resource, $date);
		if ($latest !== null && $earliest !== null && $latest < $earliest)
		{
			return [];
		}

		$grid = [];
		foreach ($windows as [$win_start, $win_end])
		{
			// 시작 시각은 칸 단위로만 노출한다. 09:03 같은 시작을 만들지 않는다
			$cursor = (int)(ceil($win_start / $unit) * $unit);
			for (; $cursor + $duration <= $win_end; $cursor += $unit)
			{
				$open = true;

				// 너무 임박했거나 예약 창을 넘긴 시각은 자리로는 두되 고를 수 없게 한다
				if ($earliest !== null && $cursor < $earliest)
				{
					$open = false;
				}
				if ($latest !== null && $cursor > $latest)
				{
					$open = false;
				}

				if ($open)
				{
					$occupy_start = $cursor - $before;
					$occupy_end = $cursor + $duration + $after;
					$open = !self::overlapsAny($occupy_start, $occupy_end, $busy);
				}

				$grid[] = [
					'time' => sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60),
					'open' => $open,
				];
			}
		}

		return $grid;
	}

	/**
	 * 기간 안에서 자리가 하나라도 남은 날.
	 *
	 * 달력에서 꽉 찬 날을 눌러 들어갔다가 빈손으로 돌아 나오게 두지 않는다.
	 *
	 * @param object $resource 시술
	 * @param array $staff_list 담당자 목록. 비면 빈 배열을 준다
	 * @param string $from YYYYMMDD
	 * @param string $to YYYYMMDD
	 * @return array 자리가 남은 날짜 목록
	 */
	public static function getOpenDates(object $resource, array $staff_list, string $from, string $to): array
	{
		if (!count($staff_list) || !preg_match('/^\d{8}$/', $from) || !preg_match('/^\d{8}$/', $to) || $from > $to)
		{
			return [];
		}

		// 담당자마다 값과 소요시간이 다르다. 날마다 다시 풀지 않도록 미리 정해 둔다
		$resolved = [];
		foreach ($staff_list as $staff)
		{
			$resolved[] = [$staff, (int)Staff::resolveService($resource, $staff)['duration']];
		}

		$open = [];
		$cursor = strtotime($from);
		$limit = strtotime($to);

		for (; $cursor <= $limit; $cursor = strtotime('+1 day', $cursor))
		{
			$date = date('Ymd', $cursor);

			foreach ($resolved as [$staff, $duration])
			{
				// 한 자리만 찾으면 그날은 열린 날이다. 나머지는 볼 필요가 없다
				if (count(self::getTimesForStaff($resource, $staff, $date, $duration)))
				{
					$open[] = $date;
					break;
				}
			}
		}

		return $open;
	}

	/**
	 * 담당자를 고르지 않았을 때. 이 시술이 가능한 담당자 전원의 합집합.
	 *
	 * 소요시간은 담당자마다 다를 수 있으므로 각자의 값으로 계산한다.
	 *
	 * @param object $resource
	 * @param array $staff_list
	 * @param string $date
	 * @return array HH:MM => 가능한 staff_srl 목록
	 */
	public static function getTimesForAnyStaff(object $resource, array $staff_list, string $date): array
	{
		$merged = [];
		foreach ($staff_list as $staff)
		{
			$resolved = Staff::resolveService($resource, $staff);
			foreach (self::getDayGrid($resource, $staff, $date, (int)$resolved['duration']) as $row)
			{
				$time = $row['time'];
				if (!isset($merged[$time]))
				{
					$merged[$time] = ['open' => false, 'staff' => []];
				}

				// 한 명이라도 가능하면 그 시각은 열린 것이다
				if ($row['open'])
				{
					$merged[$time]['open'] = true;
					$merged[$time]['staff'][] = (int)$staff->staff_srl;
				}
			}
		}

		ksort($merged);
		return $merged;
	}

	/**
	 * 담당자의 그날 근무 구간. 분 단위 [시작, 끝] 목록.
	 *
	 * @param int $staff_srl
	 * @param string $date
	 * @return array
	 */
	public static function getWorkWindows(int $staff_srl, string $date): array
	{
		$byday = Staff::getSchedules($staff_srl);
		$weekday = (int)date('w', strtotime($date));
		if (!isset($byday[$weekday]))
		{
			return [];
		}

		$windows = [];
		foreach ($byday[$weekday] as $rule)
		{
			$from = trim((string)($rule->valid_from ?? ''));
			$to = trim((string)($rule->valid_to ?? ''));
			if ($from !== '' && $date < $from)
			{
				continue;
			}
			if ($to !== '' && $date > $to)
			{
				continue;
			}

			$start = self::toMinutes((string)$rule->start_time);
			$end = self::toMinutes((string)$rule->end_time);
			if ($start === null || $end === null || $end <= $start)
			{
				continue;
			}

			$windows[] = [$start, $end];
		}

		return self::mergeRanges($windows);
	}

	/**
	 * 근무 구간에서 휴무를 뺀다. 매장 전체 휴무와 개인 휴무를 함께 본다.
	 *
	 * @param array $windows
	 * @param int $staff_srl
	 * @param string $date
	 * @return array
	 */
	protected static function subtractHolidays(array $windows, int $staff_srl, string $date): array
	{
		$args = new \stdClass;
		$args->staff_srl = $staff_srl;
		$args->from_date = $date;
		$args->to_date = $date;

		$output = executeQueryArray('reservation.getStaffHolidayList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return $windows;
		}

		foreach ($output->data as $row)
		{
			if ((string)($row->holiday_type ?? 'closed') !== 'closed')
			{
				continue;
			}

			$start = self::toMinutes((string)($row->start_time ?? ''));
			$end = self::toMinutes((string)($row->end_time ?? ''));

			// 시간을 안 적은 휴무는 그날 종일이다
			if ($start === null || $end === null || $end <= $start)
			{
				return [];
			}

			$windows = self::subtractRange($windows, $start, $end);
		}

		return $windows;
	}

	/**
	 * 근무 구간을 매장 영업시간 안으로 자른다.
	 *
	 * 지점을 쓰지 않는 사이트에서는 아무것도 하지 않는다. 매장이 그날 쉬면
	 * 담당자가 출근하기로 되어 있어도 예약을 받지 않는다.
	 *
	 * @param array $windows
	 * @param int $branch_srl
	 * @param string $date
	 * @return array
	 */
	protected static function clampToBranch(array $windows, int $branch_srl, string $date): array
	{
		if ($branch_srl <= 0 || !BranchLink::isAvailable())
		{
			return $windows;
		}

		$open = BranchLink::getOpenWindow($branch_srl, $date);
		if (!$open)
		{
			return [];
		}

		$clamped = [];
		foreach ($windows as [$start, $end])
		{
			$start = max($start, (int)$open['open']);
			$end = min($end, (int)$open['close']);
			if ($end > $start)
			{
				$clamped[] = [$start, $end];
			}
		}

		// 매장 쉬는 시간에는 예약을 받지 않는다
		if ($open['break_start'] !== null && $open['break_end'] !== null && $open['break_end'] > $open['break_start'])
		{
			$clamped = self::subtractRange($clamped, (int)$open['break_start'], (int)$open['break_end']);
		}

		return $clamped;
	}

	/**
	 * 담당자가 이미 잡혀 있는 구간. 점유 칸을 그대로 읽는다.
	 *
	 * @param int $staff_srl
	 * @param string $date
	 * @return array 분 단위 [시작, 끝] 목록
	 */
	public static function getBusyRanges(int $staff_srl, string $date): array
	{
		$cells = Occupancy::getCellsOfDay($staff_srl, $date);
		if (!count($cells))
		{
			return [];
		}

		$config = Config::getConfig();
		$unit = max(5, (int)($config->slot_unit ?? 10));

		$ranges = [];
		foreach ($cells as $cell)
		{
			$minute = (int)substr($cell, 8, 2) * 60 + (int)substr($cell, 10, 2);
			$ranges[] = [$minute, $minute + $unit];
		}

		return self::mergeRanges($ranges);
	}

	/**
	 * 지금부터 최소 몇 분 뒤여야 예약할 수 있는가. 오늘이 아니면 제한이 없다.
	 *
	 * @param object $resource
	 * @param string $date
	 * @return ?int 그날의 분 단위 하한. null 이면 제한 없음
	 */
	protected static function getEarliestMinute(object $resource, string $date): ?int
	{
		$lead = max(0, (int)($resource->min_lead_minutes ?? 0));
		$threshold = time() + $lead * 60;

		if ($date > Base::localDate('Ymd', $threshold))
		{
			return null;
		}
		if ($date < Base::localDate('Ymd', $threshold))
		{
			// 이미 지난 날이다. 어떤 시각도 담지 못하게 한다
			return 24 * 60;
		}

		return (int)Base::localDate('G', $threshold) * 60 + (int)Base::localDate('i', $threshold);
	}

	/**
	 * 며칠 뒤까지 열어 둘 것인가. 범위를 넘으면 그날은 통째로 닫는다.
	 *
	 * @param object $resource
	 * @param string $date
	 * @return ?int
	 */
	protected static function getLatestMinute(object $resource, string $date): ?int
	{
		$days = max(0, (int)($resource->max_advance_days ?? 0));
		if ($days <= 0)
		{
			return null;
		}

		$limit = Base::localDay($days);
		return $date > $limit ? -1 : null;
	}

	/**
	 * HH:MM 을 자정 기준 분으로.
	 *
	 * @param string $time
	 * @return ?int
	 */
	public static function toMinutes(string $time): ?int
	{
		$time = trim($time);
		if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m))
		{
			return null;
		}

		$hour = (int)$m[1];
		$minute = (int)$m[2];
		if ($hour > 24 || $minute > 59)
		{
			return null;
		}

		return $hour * 60 + $minute;
	}

	/**
	 * 겹치거나 맞닿은 구간을 합친다.
	 *
	 * @param array $ranges
	 * @return array
	 */
	protected static function mergeRanges(array $ranges): array
	{
		if (!count($ranges))
		{
			return [];
		}

		usort($ranges, function ($a, $b) {
			return $a[0] <=> $b[0];
		});

		$merged = [];
		$current = array_shift($ranges);
		foreach ($ranges as $range)
		{
			if ($range[0] <= $current[1])
			{
				$current[1] = max($current[1], $range[1]);
				continue;
			}

			$merged[] = $current;
			$current = $range;
		}
		$merged[] = $current;

		return $merged;
	}

	/**
	 * 구간 목록에서 한 구간을 뺀다.
	 *
	 * @param array $windows
	 * @param int $start
	 * @param int $end
	 * @return array
	 */
	protected static function subtractRange(array $windows, int $start, int $end): array
	{
		$result = [];
		foreach ($windows as [$win_start, $win_end])
		{
			if ($end <= $win_start || $start >= $win_end)
			{
				$result[] = [$win_start, $win_end];
				continue;
			}

			if ($start > $win_start)
			{
				$result[] = [$win_start, $start];
			}
			if ($end < $win_end)
			{
				$result[] = [$end, $win_end];
			}
		}

		return $result;
	}

	/**
	 * 이 구간이 이미 잡힌 구간과 겹치는가.
	 *
	 * @param int $start
	 * @param int $end
	 * @param array $ranges
	 * @return bool
	 */
	protected static function overlapsAny(int $start, int $end, array $ranges): bool
	{
		foreach ($ranges as [$busy_start, $busy_end])
		{
			if ($start < $busy_end && $end > $busy_start)
			{
				return true;
			}
		}

		return false;
	}
}
