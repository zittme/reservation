<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Booking as BookingModel;
use Zittme\Modules\Reservation\Models\Slot;

/**
 * 프론트 화면 (disp).
 */
class Front extends Base
{
	/**
	 * 스킨 경로.
	 *
	 * @return string
	 */
	protected function getSkinPath(): string
	{
		$skin = (string)($this->module_info->skin ?? '');
		if ($skin === '' || $skin === '/USE_DEFAULT/')
		{
			$skin = 'default';
		}
		$skin = preg_replace('/[^A-Za-z0-9_-]/', '', $skin);
		if ($skin === '' || !is_dir($this->module_path . 'skins/' . $skin))
		{
			$skin = 'default';
		}
		return $this->module_path . 'skins/' . $skin . '/';
	}

	/**
	 * 예약 대상 목록.
	 */
	public function dispReservationList()
	{
		$output = executeQuery('reservation.getResourceList', (object)['status' => 'open']);
		$resources = [];
		if ($output->toBool() && !empty($output->data))
		{
			foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
			{
				if (!empty($row->resource_srl))
				{
					$resources[] = $row;
				}
			}
		}

		// 썸네일은 전부 채워졌을 때만 켠다 — 하나라도 빈 상품이 있으면
		// 회색 플레이스홀더가 더 지저분하므로 텍스트 카드로 통일한다.
		$show_thumbs = count($resources) > 0;
		foreach ($resources as $r)
		{
			if (empty($r->thumb))
			{
				$show_thumbs = false;
				break;
			}
		}

		\Context::set('resources', $resources);
		\Context::set('show_thumbs', $show_thumbs);
		\Context::set('rsv_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('list');
	}

	/**
	 * 달력·슬롯 선택.
	 */
	public function dispReservationCalendar()
	{
		$resource = $this->requireResource();
		if ($resource instanceof \BaseObject)
		{
			return $resource;
		}

		// 슬롯을 미리 실체화해 둔다 (규칙이 새로 생겼을 수 있으므로 조회 시 보충 생성)
		Slot::generate($resource);

		\Context::set('resource', $resource);
		\Context::set('rsv_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('calendar');
	}

	/**
	 * 예약 폼.
	 */
	public function dispReservationForm()
	{
		$resource = $this->requireResource();
		if ($resource instanceof \BaseObject)
		{
			return $resource;
		}

		$slot_srl = (int)\Context::get('slot_srl');
		$slot = Slot::get($slot_srl);
		if (!$slot || (int)$slot->resource_srl !== (int)$resource->resource_srl)
		{
			return new \BaseObject(-1, 'msg_reservation_no_slot');
		}

		$logged_info = \Context::get('logged_info');
		$config = self::config();

		\Context::set('resource', $resource);
		\Context::set('slot', $slot);
		\Context::set('form_fields', Booking::getFormFields((int)$resource->resource_srl));
		\Context::set('rsv_config', $config);
		\Context::set('is_member', $logged_info && $logged_info->member_srl ? true : false);
		\Context::set('need_pay', ($resource->require_payment ?? 'N') === 'Y' && (int)$resource->price > 0);
		\Context::set('pay_available', self::isPayAvailable());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('form');
	}

	/**
	 * 예약 결과·상세.
	 *
	 * 회원은 본인 예약만, 비회원은 코드+비밀번호(gp)로 접근.
	 */
	public function dispReservationResult()
	{
		$code = trim((string)\Context::get('code'));
		$booking = $code !== '' ? BookingModel::getByCode($code) : null;
		if (!$booking)
		{
			return new \BaseObject(-1, 'msg_reservation_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		$is_admin = $logged_info && $logged_info->is_admin === 'Y';

		$authorized = false;
		if ($is_admin)
		{
			$authorized = true;
		}
		elseif ((int)$booking->member_srl > 0)
		{
			$authorized = $member_srl === (int)$booking->member_srl;
		}
		else
		{
			$gp = (string)\Context::get('gp');
			$authorized = $gp !== '' && !empty($booking->guest_password)
				&& \Rhymix\Framework\Password::checkPassword($gp, $booking->guest_password);
			// 결제 복귀 직후(리다이렉트)는 비밀번호가 없다 — 방금 만든 예약(5분)만 요약 노출
			if (!$authorized)
			{
				$age = time() - (strtotime(sprintf(
					'%s-%s-%s %s:%s:%s',
					substr($booking->regdate, 0, 4), substr($booking->regdate, 4, 2), substr($booking->regdate, 6, 2),
					substr($booking->regdate, 8, 2), substr($booking->regdate, 10, 2), substr($booking->regdate, 12, 2)
				)) ?: 0);
				$authorized = $age >= 0 && $age < 300;
			}
		}
		if (!$authorized)
		{
			return new \BaseObject(-1, 'msg_reservation_not_yours');
		}

		$slot = Slot::get((int)$booking->slot_srl);
		$resource_output = executeQuery('reservation.getResource', (object)['resource_srl' => (int)$booking->resource_srl]);
		$resource = ($resource_output->toBool() && is_object($resource_output->data)) ? $resource_output->data : null;

		\Context::set('booking', $booking);
		\Context::set('slot', $slot);
		\Context::set('resource', $resource);
		\Context::set('rsv_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('result');
	}

	/**
	 * 내 예약 (회원) / 비회원 조회 폼.
	 */
	public function dispReservationMy()
	{
		$logged_info = \Context::get('logged_info');
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;

		$bookings = [];
		if ($member_srl > 0)
		{
			$output = executeQuery('reservation.getBookingListByMember', (object)['member_srl' => $member_srl]);
			if ($output->toBool() && !empty($output->data))
			{
				foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
				{
					if (!empty($row->booking_srl))
					{
						$bookings[] = $row;
					}
				}
			}
		}

		\Context::set('is_member', $member_srl > 0);
		\Context::set('bookings', $bookings);
		\Context::set('rsv_config', self::config());
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('my');
	}

	/**
	 * resource_srl 파라미터의 열린 리소스.
	 *
	 * @return object|\BaseObject
	 */
	protected function requireResource()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$output = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
		$resource = ($output->toBool() && is_object($output->data) && !empty($output->data->resource_srl)) ? $output->data : null;
		if (!$resource || ($resource->status ?? '') !== 'open')
		{
			return new \BaseObject(-1, 'msg_reservation_no_resource');
		}
		return $resource;
	}
}
