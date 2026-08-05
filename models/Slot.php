<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 슬롯 — 예약의 단일 진실 공급원.
 *
 * 점유·반환은 오직 이 클래스의 원자적 UPDATE 로만 한다.
 *   PHP 에서 "조회 → 판단 → 저장" 으로 나누면 그 사이에 다른 요청이 끼어들어
 *   더블부킹이 난다. 관리자 수동 예약도 반드시 같은 경로를 탄다.
 */
class Slot
{
	/**
	 * 슬롯 점유 (원자적).
	 *
	 * 상태 검사(open)·정원 검사·차감이 한 문장에서 일어난다.
	 * affected rows = 1 일 때만 점유 성공이다.
	 *
	 * @param int $slot_srl
	 * @param int $count 점유 인원
	 * @return bool 점유 성공 여부
	 */
	public static function occupy(int $slot_srl, int $count = 1): bool
	{
		if ($slot_srl <= 0 || $count <= 0)
		{
			return false;
		}

		$oDB = \Rhymix\Framework\DB::getInstance();
		$stmt = $oDB->query(
			'UPDATE reservation_slot SET booked_count = booked_count + ? ' .
			'WHERE slot_srl = ? AND status = ? AND booked_count + ? <= capacity',
			$count, $slot_srl, 'open', $count
		);

		return $stmt !== null && $stmt->rowCount() === 1;
	}

	/**
	 * 슬롯 반환 (원자적).
	 *
	 * 취소·만료 시 점유 인원을 되돌린다. 0 미만으로 내려가지 않게 방어한다.
	 *
	 * @param int $slot_srl
	 * @param int $count
	 * @return bool
	 */
	public static function release(int $slot_srl, int $count = 1): bool
	{
		if ($slot_srl <= 0 || $count <= 0)
		{
			return false;
		}

		$oDB = \Rhymix\Framework\DB::getInstance();
		$stmt = $oDB->query(
			'UPDATE reservation_slot SET booked_count = booked_count - ? ' .
			'WHERE slot_srl = ? AND booked_count >= ?',
			$count, $slot_srl, $count
		);

		return $stmt !== null && $stmt->rowCount() === 1;
	}

	/**
	 * 슬롯 1건 조회.
	 *
	 * @param int $slot_srl
	 * @return ?object
	 */
	public static function get(int $slot_srl): ?object
	{
		$output = executeQuery('reservation.getSlot', (object)['slot_srl' => $slot_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->slot_srl)) ? $output->data : null;
	}

	/**
	 * 리소스의 날짜 범위 슬롯 목록.
	 *
	 * 조회 전에 만료된 hold 를 lazy 정리한다 — cron 없이도 잔여 수량이 맞아야 하므로
	 * 반드시 조회 경로에서 정리가 일어난다.
	 *
	 * @param int $resource_srl
	 * @param string $from YYYYMMDD
	 * @param string $to YYYYMMDD
	 * @return array
	 */
	public static function getRange(int $resource_srl, string $from, string $to): array
	{
		Booking::expireStaleHolds();

		$output = executeQuery('reservation.getSlotList', (object)[
			'resource_srl' => $resource_srl,
			'from_date' => $from,
			'to_date' => $to,
		]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$data = array_values(array_filter($data, function($row) { return !empty($row->slot_srl); }));
		usort($data, function($a, $b) {
			return [$a->slot_date, $a->start_time] <=> [$b->slot_date, $b->start_time];
		});
		return $data;
	}

	/**
	 * 슬롯 실체화 — 규칙 + 휴무를 오늘부터 N일치 슬롯으로 편다.
	 *
	 * unique(resource_srl, slot_date, start_time) 인덱스가 있으므로 이미 있는 슬롯은
	 * INSERT 가 조용히 실패하고 넘어간다(중복 생성 방지). 규칙이 바뀌어도 기존 슬롯은
	 * 지우지 않는다 — 이미 예약이 붙어 있을 수 있기 때문이다. 노출에서만 제외하려면
	 * 관리자가 슬롯을 수동 마감(closed)한다.
	 *
	 * @param object $resource
	 * @param int $days 0 이면 설정값(generate_days)
	 * @return int 새로 만든 슬롯 수
	 */
	public static function generate(object $resource, int $days = 0, bool $force = false): int
	{
		$days = $days > 0 ? $days : (int)(Base::config()->generate_days ?? 90);
		$days = min($days, 366);
		$resource_srl = (int)$resource->resource_srl;
		if ($resource_srl <= 0)
		{
			return 0;
		}

		// 조기 종료 — 이미 충분히 실체화돼 있으면 아무것도 하지 않는다.
		//   이 검사가 없으면 달력을 열 때마다 90일치 전체에 INSERT 를 시도해
		//   (유니크 충돌로 전부 실패하며) 페이지가 수 초씩 느려진다.
		//   목표일의 7일 이내까지 커버돼 있으면 충분한 것으로 본다.
		if (!$force)
		{
			$output = executeQuery('reservation.getSlotCoverage', (object)['resource_srl' => $resource_srl]);
			$max_date = ($output->toBool() && is_object($output->data)) ? (string)($output->data->max_date ?? '') : '';
			$target = date('Ymd', strtotime('+' . max(0, $days - 7) . ' day'));
			if ($max_date !== '' && $max_date >= $target)
			{
				return 0;
			}
		}

		// 이미 있는 슬롯 키를 한 번에 읽는다 — 존재하는 날짜·시간은 INSERT 자체를 건너뛴다.
		// (충돌하는 INSERT 를 수천 번 던지면 저장·달력이 수 초씩 느려진다)
		$existing = [];
		$range_end = date('Ymd', strtotime('+' . $days . ' day'));
		$output = executeQuery('reservation.getSlotList', (object)[
			'resource_srl' => $resource_srl,
			'from_date' => date('Ymd'),
			'to_date' => $range_end,
		]);
		if ($output->toBool() && !empty($output->data))
		{
			foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
			{
				if (!empty($row->slot_srl))
				{
					$existing[$row->slot_date . ' ' . $row->start_time] = $row;
				}
			}
		}

		// 규칙 로드 (요일별 다중 구간)
		$output = executeQuery('reservation.getRuleList', (object)['resource_srl' => $resource_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return 0;
		}
		$rules = is_array($output->data) ? $output->data : [$output->data];
		$rules_by_weekday = [];
		foreach ($rules as $rule)
		{
			if (($rule->is_active ?? 'Y') === 'Y')
			{
				$rules_by_weekday[(int)$rule->weekday][] = $rule;
			}
		}
		if (!count($rules_by_weekday))
		{
			return 0;
		}

		// 휴무·임시오픈 로드 (리소스 지정 + 전체 공통)
		$holidays = [];
		$output = executeQuery('reservation.getHolidayList', (object)['resource_srl' => $resource_srl]);
		if ($output->toBool() && !empty($output->data))
		{
			foreach (is_array($output->data) ? $output->data : [$output->data] as $h)
			{
				$holidays[$h->holiday_date][] = $h;
			}
		}

		$created = 0;
		$today = new \DateTime('today');
		for ($i = 0; $i < $days; $i++)
		{
			$date = (clone $today)->modify("+{$i} day");
			$ymd = $date->format('Ymd');
			$weekday = (int)$date->format('w');

			// 종일 휴무면 그 날은 통째로 건너뛴다
			$day_holidays = $holidays[$ymd] ?? [];
			$closed_all_day = false;
			foreach ($day_holidays as $h)
			{
				if (($h->holiday_type ?? 'closed') === 'closed' && empty($h->start_time))
				{
					$closed_all_day = true;
					break;
				}
			}
			if ($closed_all_day)
			{
				continue;
			}

			foreach ($rules_by_weekday[$weekday] ?? [] as $rule)
			{
				// 적용 기간 검사
				if (!empty($rule->valid_from) && $ymd < $rule->valid_from) continue;
				if (!empty($rule->valid_to) && $ymd > $rule->valid_to) continue;

				$created += self::materializeRange(
					$resource, $ymd,
					(string)$rule->start_time, (string)$rule->end_time,
					max(5, (int)$rule->interval_minutes),
					(int)$rule->capacity > 0 ? (int)$rule->capacity : (int)$resource->capacity_default,
					$day_holidays,
					$existing
				);
			}

			// 임시 오픈(extra): 규칙과 무관하게 그 구간을 연다
			foreach ($day_holidays as $h)
			{
				if (($h->holiday_type ?? '') === 'extra' && !empty($h->start_time) && !empty($h->end_time))
				{
					$created += self::materializeRange(
						$resource, $ymd,
						(string)$h->start_time, (string)$h->end_time,
						max(5, (int)$resource->duration),
						(int)$resource->capacity_default,
						[],
						$existing
					);
				}
			}
		}

		return $created;
	}

	/**
	 * 시간 구간 하나를 슬롯들로 편다.
	 *
	 * @param object $resource
	 * @param string $ymd
	 * @param string $start HH:MM
	 * @param string $end HH:MM
	 * @param int $interval 분
	 * @param int $capacity
	 * @param array $day_holidays 부분 휴무(시간대 지정 closed) 목록
	 * @return int
	 */
	protected static function materializeRange(object $resource, string $ymd, string $start, string $end, int $interval, int $capacity, array $day_holidays, array &$existing = []): int
	{
		$start_min = self::toMinutes($start);
		$end_min = self::toMinutes($end);
		if ($start_min === null || $end_min === null || $end_min <= $start_min)
		{
			return 0;
		}

		$duration = max(5, (int)$resource->duration);
		$buffer = max(0, (int)$resource->buffer_before) + max(0, (int)$resource->buffer_after);
		$step = max($interval, 5);

		$created = 0;
		for ($t = $start_min; $t + $duration <= $end_min; $t += $step + $buffer)
		{
			$slot_start = self::toHHMM($t);
			$slot_end = self::toHHMM($t + $duration);

			// 부분 휴무 구간과 겹치면 건너뛴다
			$blocked = false;
			foreach ($day_holidays as $h)
			{
				if (($h->holiday_type ?? 'closed') !== 'closed' || empty($h->start_time) || empty($h->end_time))
				{
					continue;
				}
				$h_start = self::toMinutes((string)$h->start_time);
				$h_end = self::toMinutes((string)$h->end_time);
				if ($h_start !== null && $h_end !== null && $t < $h_end && ($t + $duration) > $h_start)
				{
					$blocked = true;
					break;
				}
			}
			if ($blocked)
			{
				continue;
			}

			// 이미 있는 슬롯: INSERT 를 건너뛴다. 정원이 바뀌었으면 동기화한다
			// (예약된 인원보다 줄이지는 않는다 — 이미 받은 예약을 초과 상태로 만들 수 없다).
			$key = $ymd . ' ' . $slot_start;
			if (isset($existing[$key]))
			{
				$row = $existing[$key];
				$new_capacity = max(1, $capacity, (int)$row->booked_count);
				if ((int)$row->capacity !== $new_capacity)
				{
					executeQuery('reservation.updateSlotCapacity', (object)[
						'slot_srl' => (int)$row->slot_srl,
						'capacity' => $new_capacity,
					]);
					$existing[$key]->capacity = $new_capacity;
				}
				continue;
			}

			$args = (object)[
				'slot_srl' => getNextSequence(),
				'resource_srl' => (int)$resource->resource_srl,
				'slot_date' => $ymd,
				'start_time' => $slot_start,
				'end_time' => $slot_end,
				'capacity' => max(1, $capacity),
				'booked_count' => 0,
				'status' => 'open',
				'regdate' => Base::now(),
			];
			// unique 인덱스 충돌(이미 있는 슬롯)은 정상 — 조용히 넘어간다
			$output = executeQuery('reservation.insertSlot', $args);
			if ($output->toBool())
			{
				$created++;
				$existing[$key] = $args;
			}
		}

		return $created;
	}

	/**
	 * @param string $hhmm
	 * @return ?int
	 */
	protected static function toMinutes(string $hhmm): ?int
	{
		if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m))
		{
			return null;
		}
		return (int)$m[1] * 60 + (int)$m[2];
	}

	/**
	 * @param int $minutes
	 * @return string
	 */
	protected static function toHHMM(int $minutes): string
	{
		return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
	}
}
