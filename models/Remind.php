<?php

namespace Zittme\Modules\Reservation\Models;

use Zittme\Modules\Reservation\Controllers\Base;

/**
 * 방문 전 알림.
 *
 * 코어 예약 작업(큐)이 켜져 있으면 5분마다 run() 이 불린다. 큐가 꺼진 사이트도
 * 많아서, 예약 화면이 열릴 때 runThrottled() 로 한 번 더 확인한다. 두 길이 겹쳐도
 * 보낸 시각을 조건부로 먼저 적은 쪽만 발송하므로 두 번 나가지 않는다.
 */
class Remind
{
	public const QUEUE_HANDLER = 'Zittme\\Modules\\Reservation\\Models\\Remind::run';
	public const QUEUE_INTERVAL = '*/5 * * * *';

	/**
	 * 화면 경로에서 부를 때 쓰는 간격(초).
	 */
	protected const THROTTLE_SECONDS = 300;

	/**
	 * 알릴 때가 된 확정 예약에 알림을 보낸다.
	 *
	 * @return int 보낸 건수
	 */
	public static function run(): int
	{
		$config = Config::getConfig();
		if ((string)($config->notify_remind ?? 'N') !== 'Y')
		{
			return 0;
		}

		$hours = max(1, min(168, (int)($config->remind_hours ?? 24)));
		$output = executeQuery('reservation.getRemindDue', (object)[
			'status' => Base::STATUS_CONFIRMED,
			'from_datetime' => date('YmdHis'),
			'to_datetime' => date('YmdHis', time() + 3600 * $hours),
			'list_count' => 30,
		]);
		if (!$output->toBool() || empty($output->data))
		{
			return 0;
		}

		$count = 0;
		foreach (is_array($output->data) ? $output->data : [$output->data] as $booking)
		{
			if (empty($booking->booking_srl))
			{
				continue;
			}

			$claim = executeQuery('reservation.updateBookingRemindSent', (object)[
				'booking_srl' => (int)$booking->booking_srl,
				'remind_sent' => date('YmdHis'),
			]);
			if (!$claim->toBool() || \DB::getInstance()->getAffectedRows() < 1)
			{
				continue;
			}

			Notify::send($booking, Notify::TPL_REMIND);
			$count++;
		}
		return $count;
	}

	/**
	 * 일정 간격으로만 run() 을 부른다. 방문자 요청이 몰려도 한 번이면 된다.
	 *
	 * @return void
	 */
	public static function runThrottled(): void
	{
		try
		{
			$dir = \RX_BASEDIR . 'files/cache/reservation';
			$stamp = $dir . '/remind.last';
			if (is_file($stamp) && time() - (int)@filemtime($stamp) < self::THROTTLE_SECONDS)
			{
				return;
			}
			if (!is_dir($dir))
			{
				\FileHandler::makeDir($dir);
			}
			@touch($stamp);
			self::run();
		}
		catch (\Throwable $e)
		{
			// 알림 확인이 화면을 막으면 안 된다
		}
	}

	/**
	 * 코어 큐가 켜져 있으면 주기 작업으로 등록한다. 이미 있으면 그대로 둔다.
	 *
	 * @return void
	 */
	public static function registerQueue(): void
	{
		try
		{
			if (!function_exists('config') || !config('queue.enabled'))
			{
				return;
			}
			$found = false;
			$rows = \DB::getInstance()->query('SELECT COUNT(*) AS cnt FROM task_schedule WHERE handler = ?', [self::QUEUE_HANDLER]);
			if ($rows)
			{
				$row = $rows->fetchObject();
				$rows->closeCursor();
				$found = $row && (int)$row->cnt > 0;
			}
			if (!$found)
			{
				\Rhymix\Framework\Queue::addTaskAtInterval(self::QUEUE_INTERVAL, self::QUEUE_HANDLER);
			}
		}
		catch (\Throwable $e)
		{
			// 큐 등록 실패는 화면 경로 확인으로 메운다
		}
	}
}
