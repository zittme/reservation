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
 * 동시성 원칙: 슬롯 점유의 단일 진실 공급원은 reservation_slot 행이며,
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
	 * 담당자와 정산이 매달릴 인스턴스 번호.
	 *
	 * 예약은 단일 인스턴스 모듈이고 예약상품이 이미 0 으로 저장되고 있다.
	 * 관리 화면과 프론트가 서로 다른 값을 쓰면 담당자 목록이 한쪽에서 통째로
	 * 비어 보인다. 두 곳 모두 이 함수만 부른다.
	 *
	 * @return int
	 */
	public static function instanceSrl(): int
	{
		return 0;
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
	 * 금액 입력값을 정수 금액으로 읽는다. 천 단위 쉼표·공백·통화 기호는 무시하고 소수점 아래는 버린다.
	 *
	 * @param string $value
	 * @return int
	 */
	public static function amountFromInput(string $value): int
	{
		$value = preg_replace('/[^0-9.]/', '', $value);
		if ($value === '' || !is_numeric($value))
		{
			return 0;
		}
		return max(0, (int)floor((float)$value));
	}

	/**
	 * 영업 시간대. 사이트 설정의 시간대, 없으면 전체 기본 시간대를 쓴다.
	 *
	 * 예약 시각(슬롯·시작 시각)은 매장 현지의 벽시계 시각으로 저장된다.
	 * 지금 시각과 비교할 때는 반드시 이 시간대로 맞춰야 한다.
	 *
	 * @return \DateTimeZone
	 */
	public static function siteTimezone(): \DateTimeZone
	{
		static $cache = [];
		$name = (string)(\Context::get('_default_timezone') ?: \Rhymix\Framework\Config::get('locale.default_timezone') ?: date_default_timezone_get());
		if (!isset($cache[$name]))
		{
			try
			{
				$cache[$name] = new \DateTimeZone($name);
			}
			catch (\Exception $e)
			{
				$cache[$name] = new \DateTimeZone(date_default_timezone_get());
			}
		}
		return $cache[$name];
	}

	/**
	 * 영업 시간대 기준으로 시각을 적는다.
	 *
	 * @param string $format date() 형식
	 * @param ?int $timestamp 없으면 지금
	 * @return string
	 */
	public static function localDate(string $format, ?int $timestamp = null): string
	{
		$datetime = new \DateTime('@' . ($timestamp ?? time()));
		$datetime->setTimezone(self::siteTimezone());
		return $datetime->format($format);
	}

	/**
	 * 영업 시간대 기준 오늘에서 며칠 더한 날짜 (YYYYMMDD).
	 *
	 * @param int $days
	 * @return string
	 */
	public static function localDay(int $days = 0): string
	{
		$datetime = new \DateTime('now', self::siteTimezone());
		if ($days !== 0)
		{
			$datetime->modify(sprintf('%+d day', $days));
		}
		return $datetime->format('Ymd');
	}

	/**
	 * 영업 시간대의 벽시계 시각(YYYYMMDD[HHII[SS]])을 유닉스 시각으로 바꾼다.
	 *
	 * @param string $datetime
	 * @return int|false
	 */
	public static function localTimestamp(string $datetime)
	{
		$digits = preg_replace('/\D/', '', $datetime);
		if (strlen($digits) < 8)
		{
			return false;
		}
		$digits = str_pad(substr($digits, 0, 14), 14, '0');
		$parsed = \DateTime::createFromFormat('YmdHis', $digits, self::siteTimezone());
		return $parsed ? $parsed->getTimestamp() : false;
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

	/**
	 * 슬롯 날짜와 시각(HH:MM)을 14자리로 합친다.
	 *
	 * @param string $date YYYYMMDD
	 * @param string $time HH:MM
	 * @return string
	 */
	public static function slotDatetime(string $date, string $time): string
	{
		$hm = preg_replace('/\D/', '', $time);
		if (strlen($date) !== 8 || strlen($hm) < 4)
		{
			return '';
		}
		return $date . substr($hm, 0, 4) . '00';
	}

	/**
	 * 비회원이 본인 확인을 마친 예약번호를 세션에 남긴다.
	 *
	 * 비밀번호를 주소에 싣지 않기 위해서다. 주소는 기록·공유·리퍼러로 새어 나간다.
	 *
	 * @param string $code
	 * @return void
	 */
	public static function grantGuestAccess(string $code): void
	{
		if ($code === '')
		{
			return;
		}
		$granted = isset($_SESSION['reservation_guest']) && is_array($_SESSION['reservation_guest']) ? $_SESSION['reservation_guest'] : [];
		$granted[$code] = time();
		if (count($granted) > 20)
		{
			asort($granted);
			$granted = array_slice($granted, -20, null, true);
		}
		$_SESSION['reservation_guest'] = $granted;
		\Rhymix\Framework\Session::checkStart(true);
	}

	/**
	 * 이 세션이 비회원 예약에 접근할 수 있는가. 확인 후 2시간 동안 유효하다.
	 *
	 * @param string $code
	 * @return bool
	 */
	public static function hasGuestAccess(string $code): bool
	{
		$at = (int)($_SESSION['reservation_guest'][$code] ?? 0);
		return $at > 0 && time() - $at < 7200;
	}
}
