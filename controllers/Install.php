<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Config as ConfigModel;

/**
 * 설치와 업데이트.
 *
 * 테이블은 schemas/*.xml 을 보고 코어가 만든다. 여기서는 설정 기본값 저장과
 * 나중에 추가된 칼럼 붙이기만 한다.
 */
class Install extends Base
{
	/**
	 * 최초 스키마 이후에 추가된 칼럼들. [테이블, 칼럼, 타입, 길이]
	 *
	 * 코어는 이미 만들어진 테이블에 스키마 XML 의 새 칼럼을 자동으로 붙여 주지 않는다.
	 * 스키마에 칼럼을 추가할 때는 반드시 이 표에도 같이 적을 것.
	 */
	public const ADDED_COLUMNS = [
		['reservation_resource', 'booking_mode', 'varchar', 10, 'slot'],
		['reservation_resource', 'category', 'varchar', 100, null],
		['reservation_resource', 'pay_mode', 'varchar', 10, 'none'],
		['reservation_resource', 'deposit_amount', 'bigint', 0, 0],
		['reservation_booking', 'staff_srl', 'bigint', 0, 0],
		['reservation_booking', 'service_date', 'char', 8, null],
		['reservation_booking', 'start_datetime', 'char', 14, null],
		['reservation_booking', 'end_datetime', 'char', 14, null],
		['reservation_booking', 'duration_minutes', 'int', 0, 0],
		['reservation_booking', 'paid_amount', 'bigint', 0, 0],
		['reservation_booking', 'share_rate_snapshot', 'int', 0, -1],
		['reservation_booking', 'settlement_srl', 'bigint', 0, 0],
		['reservation_holiday', 'staff_srl', 'bigint', 0, 0],
		['reservation_staff', 'branch_srl', 'bigint', 0, 0],
		['reservation_booking', 'discount_amount', 'bigint', 0, 0],
		['reservation_booking', 'coupon_issue_srl', 'bigint', 0, 0],
		['reservation_booking', 'credit_used', 'bigint', 0, 0],
		['reservation_booking', 'credit_earned', 'bigint', 0, 0],
		['reservation_booking', 'remind_sent', 'char', 14, null],
	];

	/**
	 * 최초 스키마 이후에 추가된 테이블들.
	 *
	 * 코어는 업데이트 때 새 스키마 XML 을 알아서 만들어 주지 않는다.
	 * schemas/ 에 표를 추가하면 반드시 이 목록에도 같이 적을 것.
	 */
	public const ADDED_TABLES = [
		'reservation_staff',
		'reservation_staff_service',
		'reservation_staff_schedule',
		'reservation_occupancy',
		'reservation_settlement',
		'reservation_settlement_item',
		'reservation_notify_log',
		'reservation_credit_balance',
		'reservation_credit_log',
		'reservation_grade',
		'reservation_member_grade',
		'reservation_coupon',
		'reservation_coupon_issue',
	];

	/**
	 * 최초 설치.
	 */
	public function moduleInstall()
	{
		$this->prepareConfig();
		self::createDefaultInstance();
		\Zittme\Modules\Reservation\Models\Remind::registerQueue();
		return new \BaseObject();
	}

	/**
	 * 업데이트가 필요한가.
	 */
	public function checkUpdate()
	{
		$config = \ModuleModel::getModuleConfig('reservation');
		if (!is_object($config) || !isset($config->enabled))
		{
			return true;
		}
		if (!self::getDefaultInstance())
		{
			return true;
		}

		$oDB = \DB::getInstance();
		foreach (self::ADDED_TABLES as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				return true;
			}
		}
		foreach (self::ADDED_COLUMNS as [$table, $column])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * 업데이트 실행.
	 */
	public function moduleUpdate()
	{
		$this->prepareConfig();
		self::createDefaultInstance();

		$oDB = \DB::getInstance();
		foreach (self::ADDED_TABLES as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				$oDB->createTableByXmlFile(\RX_BASEDIR . 'modules/reservation/schemas/' . $table . '.xml');
			}
		}
		foreach (self::ADDED_COLUMNS as [$table, $column, $type, $size, $default])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				// 기본값이 있는 칼럼은 NOT NULL 로 붙인다. 숫자 칼럼이 NULL 이면 집계가 어긋난다
				$oDB->addColumn($table, $column, $type, $size, $default, $default !== null);
			}
		}

		self::backfillServiceDate();
		\Zittme\Modules\Reservation\Models\Remind::registerQueue();

		return new \BaseObject();
	}

	/**
	 * 캐시 재생성.
	 */
	public function recompileCache()
	{
	}

	/**
	 * 이용일 칼럼이 없던 시절의 예약에 슬롯 날짜를 채운다.
	 *
	 * 조인이 필요해 쿼리 XML 로는 표현할 수 없다. 별칭이 들어간 원시 SQL 은
	 * DB::query 의 테이블명 재작성과 충돌하므로 핸들을 직접 쓴다.
	 *
	 * @return void
	 */
	protected static function backfillServiceDate(): void
	{
		$oDB = \DB::getInstance();
		if (!$oDB->isColumnExists('reservation_booking', 'service_date'))
		{
			return;
		}

		$prefix = \Zittme\Framework\Config::get('db.master.prefix') ?: '';
		$sql = 'UPDATE `' . $prefix . 'reservation_booking` b' .
			' INNER JOIN `' . $prefix . 'reservation_slot` s ON s.slot_srl = b.slot_srl' .
			' SET b.service_date = s.slot_date' .
			" WHERE b.service_date IS NULL OR b.service_date = ''";

		try
		{
			$oDB->getHandle()->exec($sql);
		}
		catch (\Throwable $e)
		{
			// 보정은 실패해도 설치를 막지 않는다. 목록에서 옛 예약의 날짜만 비어 보인다
			trigger_error('reservation: service_date backfill failed - ' . $e->getMessage(), \E_USER_WARNING);
		}
	}

	/**
	 * 설정 기본값을 최초 1회 통째로 저장한다.
	 *
	 * @return void
	 */
	protected function prepareConfig(): void
	{
		$config = \ModuleModel::getModuleConfig('reservation');
		if (!is_object($config))
		{
			$config = new \stdClass;
		}

		$changed = false;
		foreach (ConfigModel::DEFAULTS as $key => $value)
		{
			if (!isset($config->{$key}))
			{
				$config->{$key} = $value;
				$changed = true;
			}
		}

		if ($changed)
		{
			ConfigModel::setConfig($config);
		}
	}

	/**
	 * 기본 인스턴스(예약 mid)를 만든다. 이미 있으면 아무것도 하지 않는다.
	 *
	 * 예약은 단일 인스턴스 모델 — 사이트맵에서 여러 개 만들면 같은 리소스가
	 * 여러 주소에 중복 노출되므로, 설치 시 한 번만 자동 생성하고
	 * 사이트맵 모듈 목록에서는 제외한다 (Trigger 참조).
	 *
	 * @return void
	 */
	protected static function createDefaultInstance(): void
	{
		if (self::getDefaultInstance())
		{
			return;
		}

		$mid = self::DEFAULT_MID;
		if (\ModuleModel::isIDExists($mid))
		{
			$mid = \ModuleModel::getNextAvailableMid($mid) ?: ($mid . '_' . time());
		}

		\ModuleController::getInstance()->insertModule((object)[
			'mid' => $mid,
			'module' => 'reservation',
			'browser_title' => lang('reservation.reservation') ?: 'Reservation',
			'description' => '',
			'layout_srl' => -1,
			'mlayout_srl' => -1,
			'skin' => '/USE_DEFAULT/',
			'mskin' => '/USE_DEFAULT/',
			// 메뉴 노출은 관리자가 사이트맵에서 이 mid 로 링크를 걸어 결정한다
			'isMenuCreate' => false,
		]);

		self::$_default_instance = null;
	}
}
