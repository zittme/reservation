<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Booking;

/**
 * zittme_pay 결제 통지 수신.
 *
 * ⚠️ 이 핸들러들은 PG 콜백 요청 안에서 실행된다 — 세션을 읽거나 쓰면 안 된다
 *    (크로스사이트 콜백에서 세션을 건드리면 원래 창의 CSRF 토큰이 무효화된다, pitfall #57).
 *
 * ⚠️ eventHandler 는 conf/module.xml 선언만으로 동작하지 않는다.
 *    모듈 설치/업데이트 1회 실행으로 DB triggers 에 등록해야 한다.
 */
class Trigger extends Base
{
	/**
	 * 사이트맵 "모듈 연결" 목록에서 예약을 제외한다.
	 *
	 * 예약은 단일 인스턴스 모델이다 — 설치 시 기본 mid 가 자동 생성되므로
	 * 사이트맵에서 추가 인스턴스를 만들면 같은 리소스가 여러 주소에 중복 노출된다.
	 * (코어는 인스턴스가 있는 모듈을 자동으로 목록에 올리므로 여기서 걸러낸다)
	 *
	 * @param array $moduleList (참조)
	 * @return void
	 */
	public function triggerModuleListInSitemap(&$moduleList)
	{
		if (is_array($moduleList))
		{
			$moduleList = array_values(array_diff($moduleList, ['reservation']));
		}
	}

	/**
	 * 결제 승인 → 예약 확정.
	 *
	 * 조건부 전이(멱등)라 트리거가 중복 도착해도 한 번만 확정된다.
	 *
	 * @param object $order zittme_pay 주문 객체
	 * @return void
	 */
	public function triggerPayApproved($order)
	{
		if (!is_object($order) || ($order->source_module ?? '') !== 'reservation')
		{
			return;
		}

		$booking_srl = (int)($order->source_srl ?? 0);
		if ($booking_srl <= 0)
		{
			return;
		}

		// 결제 주문 연결을 남긴다 (이미 연결돼 있으면 그대로)
		$booking = Booking::get($booking_srl);
		if (!$booking)
		{
			return;
		}

		if (Booking::confirm($booking_srl, 0))
		{
			Booking::log($booking_srl, 'pay', (string)$booking->status, self::STATUS_CONFIRMED, 0, 'pay_order_srl=' . (int)($order->order_srl ?? 0));
		}
	}

	/**
	 * 결제 취소/환불 → 예약 취소 + 슬롯 반환.
	 *
	 * @param object $order
	 * @return void
	 */
	public function triggerPayCancelled($order)
	{
		if (!is_object($order) || ($order->source_module ?? '') !== 'reservation')
		{
			return;
		}

		$booking_srl = (int)($order->source_srl ?? 0);
		if ($booking_srl <= 0)
		{
			return;
		}

		if (Booking::cancelAndRelease($booking_srl, 0, self::STATUS_CANCELLED))
		{
			Booking::log($booking_srl, 'refund', '', self::STATUS_CANCELLED, 0, 'pay_order_srl=' . (int)($order->order_srl ?? 0));
		}
	}
}
