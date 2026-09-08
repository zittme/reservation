<?php

namespace Zittme\Modules\Reservation\Models;

/**
 * 지점 모듈과의 연결.
 *
 * 예약은 지점 모듈 없이도 그대로 돌아가야 한다. 지점이 설치되지 않은 사이트에서
 * 예약이 죽으면 안 되므로, 지점을 부르는 곳은 전부 여기를 지난다.
 *
 * 지점 모듈이 없으면 "지점을 쓰지 않는 사이트" 로 취급한다.
 */
class BranchLink
{
	/**
	 * 지점 모듈이 쓸 수 있는 상태인가.
	 *
	 * @return bool
	 */
	public static function isAvailable(): bool
	{
		return class_exists('\Zittme\Modules\Branch\Models\Branch');
	}

	/**
	 * 영업 중인 지점 목록. 지점 모듈이 없으면 빈 배열이다.
	 *
	 * @return array
	 */
	public static function getList(): array
	{
		if (!self::isAvailable())
		{
			return [];
		}

		try
		{
			return \Zittme\Modules\Branch\Models\Branch::getOpenList();
		}
		catch (\Throwable $e)
		{
			return [];
		}
	}

	/**
	 * 지점 하나.
	 *
	 * @param int $branch_srl
	 * @return ?object
	 */
	public static function get(int $branch_srl): ?object
	{
		if (!self::isAvailable() || $branch_srl <= 0)
		{
			return null;
		}

		try
		{
			return \Zittme\Modules\Branch\Models\Branch::get($branch_srl);
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	/**
	 * 손님에게 지점을 고르게 해야 하는가.
	 *
	 * 지점이 한 곳뿐인 매장에 "지점을 고르세요" 가 뜨면 안 된다.
	 *
	 * @return bool
	 */
	public static function needsChoice(): bool
	{
		return count(self::getList()) > 1;
	}

	/**
	 * 지점이 한 곳뿐일 때 그 지점 번호. 아니면 0.
	 *
	 * @return int
	 */
	public static function getSoleBranchSrl(): int
	{
		$list = self::getList();
		if (count($list) !== 1)
		{
			return 0;
		}

		return (int)array_key_first($list);
	}

	/**
	 * 그 지점이 그날 문을 여는가. 지점을 쓰지 않으면 늘 참이다.
	 *
	 * 담당자 근무표가 열려 있어도 매장이 쉬는 날이면 예약을 받으면 안 된다.
	 *
	 * @param int $branch_srl
	 * @param string $date YYYYMMDD
	 * @return bool
	 */
	public static function isOpenOn(int $branch_srl, string $date): bool
	{
		if (!self::isAvailable() || $branch_srl <= 0)
		{
			return true;
		}

		try
		{
			return \Zittme\Modules\Branch\Models\Branch::getTodayHours($branch_srl, $date) !== null;
		}
		catch (\Throwable $e)
		{
			return true;
		}
	}

	/**
	 * 그 지점의 그날 영업 구간. 분 단위 [시작, 끝] 이며 없으면 null.
	 *
	 * 담당자 근무시간을 이 구간 안으로 자른다. 매장이 닫은 뒤의 예약을 막는다.
	 *
	 * @param int $branch_srl
	 * @param string $date
	 * @return ?array
	 */
	public static function getOpenWindow(int $branch_srl, string $date): ?array
	{
		if (!self::isAvailable() || $branch_srl <= 0)
		{
			return null;
		}

		try
		{
			$hours = \Zittme\Modules\Branch\Models\Branch::getTodayHours($branch_srl, $date);
			if (!$hours)
			{
				return null;
			}

			$open = \Zittme\Modules\Branch\Models\Branch::toMinutes((string)$hours->open_time);
			$close = \Zittme\Modules\Branch\Models\Branch::toMinutes((string)$hours->close_time);
			if ($open === null || $close === null || $close <= $open)
			{
				return null;
			}

			$break_start = \Zittme\Modules\Branch\Models\Branch::toMinutes((string)$hours->break_start);
			$break_end = \Zittme\Modules\Branch\Models\Branch::toMinutes((string)$hours->break_end);

			return [
				'open' => $open,
				'close' => $close,
				'break_start' => $break_start,
				'break_end' => $break_end,
			];
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}
}
