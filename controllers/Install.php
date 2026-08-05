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
	public const ADDED_COLUMNS = [];

	/**
	 * 최초 설치.
	 */
	public function moduleInstall()
	{
		$this->prepareConfig();
		self::createDefaultInstance();
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
		foreach (self::ADDED_COLUMNS as [$table, $column, $type, $size])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				$oDB->addColumn($table, $column, $type, $size);
			}
		}

		return new \BaseObject();
	}

	/**
	 * 캐시 재생성.
	 */
	public function recompileCache()
	{
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
