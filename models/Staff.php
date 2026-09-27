<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 담당자 — 시술을 실제로 하는 사람.
 *
 * 자원(시술)이 "무엇을" 이라면 담당자는 "누가" 다. 두 축이 만나는 자리가
 * reservation_staff_service 이고, 값과 소요시간과 배분율은 거기서 덮어쓴다.
 *
 * 값 계산은 반드시 resolveService() 한 곳을 지난다. 화면마다 따로 계산하면
 * 예약가와 정산가가 어긋난다.
 */
class Staff
{
	public const STATUS_ACTIVE = 'active';
	public const STATUS_HIDDEN = 'hidden';

	/**
	 * 배분율의 분모. 만분율을 쓴다. 4550 = 45.5%
	 */
	public const RATE_BASE = 10000;

	/**
	 * 담당자 한 명.
	 *
	 * @param int $staff_srl
	 * @return ?object
	 */
	public static function get(int $staff_srl): ?object
	{
		if ($staff_srl <= 0)
		{
			return null;
		}

		$output = executeQuery('reservation.getStaff', (object)['staff_srl' => $staff_srl]);
		if (!$output->toBool() || !is_object($output->data))
		{
			return null;
		}

		return Lang::staff($output->data);
	}

	/**
	 * 담당자 목록.
	 *
	 * @param int $module_srl
	 * @param ?string $status 비우면 상태를 가리지 않는다
	 * @return array staff_srl 을 키로 하는 목록
	 */
	public static function getList(int $module_srl, ?string $status = self::STATUS_ACTIVE, int $branch_srl = 0): array
	{
		$args = new \stdClass;
		$args->module_srl = $module_srl;
		if ($status !== null && $status !== '')
		{
			$args->status = $status;
		}
		if ($branch_srl > 0)
		{
			$args->branch_srl = $branch_srl;
		}

		$output = executeQueryArray('reservation.getStaffList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$list = [];
		foreach ($output->data as $row)
		{
			$list[(int)$row->staff_srl] = Lang::staff($row);
		}

		return $list;
	}

	/**
	 * 회원 계정에 연결된 담당자.
	 *
	 * 담당자 본인이 로그인해 자기 예약과 정산만 보게 할 때 쓴다.
	 *
	 * @param int $module_srl
	 * @param int $member_srl
	 * @return ?object
	 */
	public static function getByMember(int $module_srl, int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}

		$args = new \stdClass;
		$args->module_srl = $module_srl;
		$args->member_srl = $member_srl;

		$output = executeQueryArray('reservation.getStaffList', $args);
		if (!$output->toBool() || !is_array($output->data) || !count($output->data))
		{
			return null;
		}

		return $output->data[0];
	}

	/**
	 * 담당자가 맡은 시술 연결 목록.
	 *
	 * @param int $staff_srl
	 * @param bool $active_only
	 * @return array resource_srl 을 키로 하는 목록
	 */
	public static function getServiceMap(int $staff_srl, bool $active_only = true): array
	{
		if ($staff_srl <= 0)
		{
			return [];
		}

		$args = new \stdClass;
		$args->staff_srl = $staff_srl;
		if ($active_only)
		{
			$args->is_active = 'Y';
		}

		$output = executeQueryArray('reservation.getStaffServiceList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$map = [];
		foreach ($output->data as $row)
		{
			$map[(int)$row->resource_srl] = $row;
		}

		return $map;
	}

	/**
	 * 한 시술을 할 수 있는 담당자 목록.
	 *
	 * @param int $module_srl
	 * @param int $resource_srl
	 * @return array staff_srl 을 키로 하는 목록
	 */
	public static function getListForService(int $module_srl, int $resource_srl, int $branch_srl = 0): array
	{
		if ($resource_srl <= 0)
		{
			return [];
		}

		$args = new \stdClass;
		$args->resource_srl = $resource_srl;
		$args->is_active = 'Y';

		$output = executeQueryArray('reservation.getStaffServiceList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$all = self::getList($module_srl, self::STATUS_ACTIVE, $branch_srl);
		$list = [];
		foreach ($output->data as $row)
		{
			$staff_srl = (int)$row->staff_srl;
			if (isset($all[$staff_srl]))
			{
				$list[$staff_srl] = $all[$staff_srl];
			}
		}

		return $list;
	}

	/**
	 * 이 담당자로 이 시술을 받을 때의 값, 소요시간, 배분율.
	 *
	 * 시술 기본값에서 시작해 담당자 개별값이 있으면 덮어쓴다.
	 * 담당자를 고르지 않은 예약(staff_srl = 0)도 여기를 지난다.
	 *
	 * @param object $resource 시술
	 * @param ?object $staff 담당자. null 이면 시술 기본값만 쓴다
	 * @param ?object $map 담당자-시술 연결. 없으면 조회한다
	 * @return array price, duration, share_rate 를 담은 배열
	 */
	public static function resolveService(object $resource, ?object $staff = null, ?object $map = null): array
	{
		$price = (int)($resource->price ?? 0);
		$duration = (int)($resource->duration ?? 0) ?: 60;
		$share_rate = -1;

		if ($staff)
		{
			if ($map === null)
			{
				$all = self::getServiceMap((int)$staff->staff_srl);
				$map = $all[(int)$resource->resource_srl] ?? null;
			}

			if ($map)
			{
				// 값은 0 원 시술이 있을 수 있으므로 -1 을 "미지정" 으로 쓴다
				if ((int)$map->price >= 0)
				{
					$price = (int)$map->price;
				}
				if ((int)$map->duration > 0)
				{
					$duration = (int)$map->duration;
				}
				if ((int)$map->share_rate >= 0)
				{
					$share_rate = (int)$map->share_rate;
				}
			}

			if ($share_rate < 0)
			{
				$share_rate = (int)($staff->share_rate ?? 0);
			}
		}

		return [
			'price' => $price,
			'duration' => $duration,
			'share_rate' => $share_rate,
		];
	}

	/**
	 * 배분액을 만분율로 계산한다.
	 *
	 * @param int $amount
	 * @param int $rate 만분율
	 * @return int
	 */
	public static function shareAmount(int $amount, int $rate): int
	{
		if ($amount <= 0 || $rate <= 0)
		{
			return 0;
		}

		return (int)floor($amount * $rate / self::RATE_BASE);
	}

	/**
	 * 담당자 저장. staff_srl 이 있으면 수정이다.
	 *
	 * @param object $args
	 * @return int 저장된 staff_srl. 실패하면 0
	 */
	public static function save(object $args): int
	{
		$staff_srl = (int)($args->staff_srl ?? 0);
		$args->last_update = date('YmdHis');

		if ($staff_srl > 0)
		{
			$output = executeQuery('reservation.updateStaff', $args);
			return $output->toBool() ? $staff_srl : 0;
		}

		$args->staff_srl = getNextSequence();
		$args->regdate = $args->last_update;

		$output = executeQuery('reservation.insertStaff', $args);
		return $output->toBool() ? (int)$args->staff_srl : 0;
	}

	/**
	 * 담당자가 맡을 시술 연결을 통째로 다시 쓴다.
	 *
	 * 화면에서 체크한 것만 남기는 방식이라 지우고 다시 넣는다.
	 *
	 * @param int $staff_srl
	 * @param array $rows resource_srl => ['price','duration','share_rate'] 형태
	 * @return bool
	 */
	public static function replaceServices(int $staff_srl, array $rows): bool
	{
		if ($staff_srl <= 0)
		{
			return false;
		}

		$output = executeQuery('reservation.deleteStaffServices', (object)['staff_srl' => $staff_srl]);
		if (!$output->toBool())
		{
			return false;
		}

		$now = date('YmdHis');
		foreach ($rows as $resource_srl => $row)
		{
			$args = new \stdClass;
			$args->map_srl = getNextSequence();
			$args->staff_srl = $staff_srl;
			$args->resource_srl = (int)$resource_srl;
			$args->price = isset($row['price']) && $row['price'] !== '' ? (int)$row['price'] : -1;
			$args->duration = isset($row['duration']) ? (int)$row['duration'] : 0;
			$args->share_rate = isset($row['share_rate']) && $row['share_rate'] !== '' ? (int)$row['share_rate'] : -1;
			$args->is_active = 'Y';
			$args->regdate = $now;

			$output = executeQuery('reservation.insertStaffService', $args);
			if (!$output->toBool())
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * 담당자 근무 요일과 시간을 통째로 다시 쓴다.
	 *
	 * @param int $staff_srl
	 * @param array $rows ['weekday','start_time','end_time'] 목록
	 * @return bool
	 */
	public static function replaceSchedules(int $staff_srl, array $rows): bool
	{
		if ($staff_srl <= 0)
		{
			return false;
		}

		$output = executeQuery('reservation.deleteStaffSchedules', (object)['staff_srl' => $staff_srl]);
		if (!$output->toBool())
		{
			return false;
		}

		$now = date('YmdHis');
		foreach ($rows as $row)
		{
			$start = trim((string)($row['start_time'] ?? ''));
			$end = trim((string)($row['end_time'] ?? ''));
			if ($start === '' || $end === '' || $start >= $end)
			{
				continue;
			}

			$args = new \stdClass;
			$args->rule_srl = getNextSequence();
			$args->staff_srl = $staff_srl;
			$args->weekday = (int)($row['weekday'] ?? 0);
			$args->start_time = $start;
			$args->end_time = $end;
			$args->valid_from = trim((string)($row['valid_from'] ?? ''));
			$args->valid_to = trim((string)($row['valid_to'] ?? ''));
			$args->is_active = 'Y';
			$args->regdate = $now;

			$output = executeQuery('reservation.insertStaffSchedule', $args);
			if (!$output->toBool())
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * 담당자 근무표.
	 *
	 * @param int $staff_srl
	 * @return array 요일을 키로 하는 근무 구간 목록
	 */
	public static function getSchedules(int $staff_srl): array
	{
		if ($staff_srl <= 0)
		{
			return [];
		}

		$args = new \stdClass;
		$args->staff_srl = $staff_srl;
		$args->is_active = 'Y';

		$output = executeQueryArray('reservation.getStaffScheduleList', $args);
		if (!$output->toBool() || !is_array($output->data))
		{
			return [];
		}

		$byday = [];
		foreach ($output->data as $row)
		{
			$byday[(int)$row->weekday][] = $row;
		}

		return $byday;
	}

	/**
	 * 담당자를 지운다. 예약이 걸려 있으면 지우지 않고 숨김으로 돌린다.
	 *
	 * 지난 예약과 정산이 담당자 이름을 잃으면 내역을 읽을 수 없다.
	 *
	 * @param int $staff_srl
	 * @return bool
	 */
	public static function remove(int $staff_srl): bool
	{
		if ($staff_srl <= 0)
		{
			return false;
		}

		$args = new \stdClass;
		$args->staff_srl = $staff_srl;
		$args->status_list = [Base::STATUS_HOLD, Base::STATUS_PENDING, Base::STATUS_CONFIRMED, Base::STATUS_DONE];
		$args->list_count = 1;

		$output = executeQueryArray('reservation.getBookingList', $args);
		$has_booking = $output->toBool() && is_array($output->data) && count($output->data) > 0;

		if ($has_booking)
		{
			return self::save((object)[
				'staff_srl' => $staff_srl,
				'status' => self::STATUS_HIDDEN,
			]) > 0;
		}

		executeQuery('reservation.deleteStaffServices', (object)['staff_srl' => $staff_srl]);
		executeQuery('reservation.deleteStaffSchedules', (object)['staff_srl' => $staff_srl]);

		$output = executeQuery('reservation.deleteStaff', (object)['staff_srl' => $staff_srl]);
		return $output->toBool();
	}
}
