<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Config as ConfigModel;

/**
 * 예약 모듈.
 *
 * 예약 대상(리소스)과 운영 규칙을 등록하면 슬롯이 실체화되고, 방문자가 슬롯을 골라
 * 예약한다. 유료 예약은 zittme_pay 에 위임한다 (의존 방향: reservation → zittme_pay 단방향).
 *
 * 이 모듈은 엔진 기본 제공이 아니라 스토어로 따로 배포하는 부가 모듈이다.
 * zittme_pay 가 없으면 결제 기능만 비활성되고 무료 예약은 정상 동작해야 한다.
 *
 * ★ 동시성 원칙: 슬롯 점유의 단일 진실 공급원은 reservation_slot 행이며,
 *   점유·반환은 오직 조건부 UPDATE(affected rows 판정)로만 한다.
 */
class Base extends \ModuleObject
{
	/**
	 * 기본 인스턴스 주소.
	 *
	 * 예약은 단일 인스턴스 모델이다 — 리소스가 전역이라 인스턴스를 여러 개 만들면
	 * 같은 데이터가 여러 주소에 중복 노출된다. 설치 시 한 번만 자동 생성한다.
	 */
	public const DEFAULT_MID = 'reservation';

	/**
	 * 기본 인스턴스 캐시.
	 *
	 * @var object|false|null
	 */
	protected static $_default_instance = null;

	/**
	 * 이미 만들어진 예약 인스턴스를 돌려준다. (mid 이름이 아니라 module 종류로 찾는다)
	 *
	 * @return ?object
	 */
	public static function getDefaultInstance(): ?object
	{
		if (self::$_default_instance === null)
		{
			$list = \ModuleModel::getMidList((object)['module' => 'reservation']);
			self::$_default_instance = is_array($list) && count($list) ? reset($list) : false;
		}
		return self::$_default_instance ?: null;
	}

	/**
	 * 예약 상태.
	 */
	public const STATUS_HOLD = 'hold';           // 결제 대기 (슬롯 점유 중, hold_expires 지나면 만료)
	public const STATUS_PENDING = 'pending';     // 무통장 입금 대기 (점유 유지)
	public const STATUS_CONFIRMED = 'confirmed';
	public const STATUS_CANCELLED = 'cancelled';
	public const STATUS_NOSHOW = 'noshow';
	public const STATUS_DONE = 'done';
	public const STATUS_EXPIRED = 'expired';

	/**
	 * 슬롯을 점유하고 있는(=정원을 차지하는) 상태들.
	 */
	public const OCCUPYING_STATUSES = [
		self::STATUS_HOLD,
		self::STATUS_PENDING,
		self::STATUS_CONFIRMED,
	];

	/**
	 * 모듈 설정.
	 *
	 * @return object
	 */
	public static function config(): object
	{
		return ConfigModel::getConfig();
	}

	/**
	 * zittme_pay 사용 가능 여부.
	 *
	 * 부가 모듈이라 아예 없을 수 있다. 없으면 유료 예약만 막고 나머지는 그대로 돈다.
	 *
	 * @return bool
	 */
	public static function isPayAvailable(): bool
	{
		return class_exists('\\Zittme\\Modules\\Zittme_pay\\PayService')
			&& \Zittme\Modules\Zittme_pay\PayService::isAvailable();
	}

	/**
	 * 지금 시각 (라이믹스 표준 14자리).
	 *
	 * @return string
	 */
	public static function now(): string
	{
		return date('YmdHis');
	}

	/**
	 * 예약번호 생성. 예: R20260730-4F7A2C
	 *
	 * @return string
	 */
	public static function generateBookingCode(): string
	{
		$prefix = trim((string)(self::config()->code_prefix ?? 'R'));
		return sprintf('%s%s-%s', $prefix !== '' ? $prefix : 'R', date('Ymd'), strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)));
	}
}
