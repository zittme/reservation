<?php

namespace Zittme\Modules\Reservation\Controllers;

/**
 * 예약 전용 콘솔 — zittme 관리자에 귀속되지 않는 별도 풀스크린 운영 패널.
 *
 * bizchat 콘솔과 같은 규약: standalone act + layout 'none'.
 * 화면 데이터 로직은 Admin 을 그대로 상속해 재사용하고,
 * 콘솔 셸(사이드바·링크 재작성)은 views/admin/_tabs 의 콘솔 분기가 담당한다.
 */
class Console extends Admin
{
	/**
	 * 콘솔 페이지 → Admin disp 메서드 매핑.
	 */
	public const PAGES = [
		'dashboard' => 'dispReservationAdminDashboard',
		'bookings' => 'dispReservationAdminBookings',
		'resources' => 'dispReservationAdminResources',
		'resource_edit' => 'dispReservationAdminResourceEdit',
		'schedule' => 'dispReservationAdminSchedule',
		'forms' => 'dispReservationAdminForms',
		'stats' => 'dispReservationAdminStats',
		'config' => 'dispReservationAdminConfig',
	];

	/**
	 * 콘솔 진입점. ?act=dispReservationConsole&p=<page>
	 */
	public function dispReservationConsole()
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || $logged_info->is_admin !== 'Y')
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}

		$p = (string)\Context::get('p');
		if (!isset(self::PAGES[$p]))
		{
			$p = 'dashboard';
		}

		\Context::set('zmc_console', true);
		\Context::set('zmc_page', $p);
		\Context::setBrowserTitle(lang('reservation.reservation') . ' 콘솔');
		\Context::set('layout', 'none');

		return $this->{self::PAGES[$p]}();
	}
}
