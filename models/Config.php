<?php

namespace Zittme\Modules\Reservation\Models;

/**
 * 예약 모듈 설정.
 */
class Config
{
	/**
	 * 기본값.
	 *
	 * 새 키를 추가하면 여기와 관리자 설정 화면에 함께 반영할 것.
	 * (extra_var 미설정 undefined 함정 방지 — 반드시 기본값을 둔다)
	 */
	public const DEFAULTS = [
		'enabled' => 'Y',
		// 예약번호 접두사
		'code_prefix' => 'R',
		// 결제 홀드 유지 시간(분)
		'hold_minutes' => 10,
		// 슬롯 실체화 범위(일)
		'generate_days' => 90,
		// 1인 동시 활성 예약 상한 (0 = 무제한)
		'max_active_per_member' => 3,
		// 비회원 예약 허용
		'allow_guest' => 'Y',
		// 개인정보 수집 동의 문구
		'privacy_text' => '예약 서비스 제공을 위해 이름, 연락처를 수집합니다. 수집된 정보는 예약 이행 및 안내 목적으로만 사용됩니다.',
		'privacy_version' => '1.0',
		// 예약 정보 보관 기간(일) — 경과 시 자동 파기 (0 = 무기한)
		'retention_days' => 365,
		// 알림: 관리자 메일
		'notify_admin' => 'N',
		'notify_admin_email' => '',
		// 환불 규정: "일수:비율" 줄바꿈 목록. 예) "3:100\n1:50\n0:0"
		'refund_policy' => "3:100\n1:50\n0:0",
	];

	/**
	 * 설정 캐시.
	 *
	 * @var ?object
	 */
	protected static $_config = null;

	/**
	 * 설정을 읽는다. 빠진 키는 기본값으로 채운다.
	 *
	 * @return object
	 */
	public static function getConfig(): object
	{
		if (self::$_config !== null)
		{
			return self::$_config;
		}

		$config = \ModuleModel::getModuleConfig('reservation');
		if (!is_object($config))
		{
			$config = new \stdClass;
		}
		foreach (self::DEFAULTS as $key => $value)
		{
			if (!isset($config->{$key}))
			{
				$config->{$key} = $value;
			}
		}

		return self::$_config = $config;
	}

	/**
	 * 설정을 저장한다.
	 *
	 * @param object $config
	 * @return object
	 */
	public static function setConfig(object $config): object
	{
		$output = \ModuleController::getInstance()->updateModuleConfig('reservation', $config);
		self::$_config = null;
		return $output;
	}

	/**
	 * 환불 규정 파싱. [일수 => 비율] 내림차순.
	 *
	 * @return array<int, int>
	 */
	public static function getRefundPolicy(): array
	{
		$rules = [];
		foreach (preg_split('/[\r\n]+/', (string)self::getConfig()->refund_policy) as $line)
		{
			if (preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', $line, $m))
			{
				$rules[(int)$m[1]] = min(100, (int)$m[2]);
			}
		}
		krsort($rules);
		return $rules;
	}
}
