<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Availability;
use Zittme\Modules\Reservation\Models\Booking as BookingModel;
use Zittme\Modules\Reservation\Models\BranchLink;
use Zittme\Modules\Reservation\Models\Coupon;
use Zittme\Modules\Reservation\Models\Credit;
use Zittme\Modules\Reservation\Models\Grade;
use Zittme\Modules\Reservation\Models\Lang;
use Zittme\Modules\Reservation\Models\Remind;
use Zittme\Modules\Reservation\Models\Slot;
use Zittme\Modules\Reservation\Models\Staff as StaffModel;

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
		// 기본 스킨 위임이면 사이트 기본 디자인 값을 따른다 (테마 적용이 여길 바꾼다)
		if ($skin === '' || $skin === '/USE_DEFAULT/')
		{
			$skin = (string)(\ModuleModel::getModuleDefaultSkin('reservation', 'P') ?: 'default');
		}
		// 일반 이름과 테마 결합명('테마|@|스킨')만 허용 — 경로 조작 방지
		if (!preg_match('/^[A-Za-z0-9_-]+(\|@\|[A-Za-z0-9_-]+)?$/', $skin))
		{
			$skin = 'default';
		}
		$path = \Zittme\Framework\Theme::resolveSkinPath($this->module_path, $skin, 'skins');
		if (!is_dir($path) && strpos($skin, \Zittme\Framework\Theme::SEPARATOR) === false)
		{
			foreach (array_keys(\Zittme\Framework\Theme::getModuleSkins('reservation', 'skins')) as $combined)
			{
				if (substr($combined, -strlen(\Zittme\Framework\Theme::SEPARATOR . $skin)) === \Zittme\Framework\Theme::SEPARATOR . $skin)
				{
					$path = \Zittme\Framework\Theme::resolveSkinPath($this->module_path, $combined, 'skins');
					break;
				}
			}
		}
		if (!is_dir($path))
		{
			$path = $this->module_path . 'skins/default/';
		}
		return rtrim($path, '/') . '/';
	}

	/**
	 * 예약 대상 목록.
	 */
	public function dispReservationList()
	{
		Remind::runThrottled();

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

		\Context::set('resources', Lang::applyAll($resources, Lang::RESOURCE_FIELDS));
		\Context::set('show_thumbs', $show_thumbs);
		\Context::set('rsv_config', self::frontConfig());
		$this->setManageLinks();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('list');
	}

	/**
	 * 달력·슬롯 선택.
	 */
	public function dispReservationCalendar()
	{
		Remind::runThrottled();

		$resource = $this->requireResource();
		if ($resource instanceof \BaseObject)
		{
			return $resource;
		}

		$is_staff_mode = (string)($resource->booking_mode ?? 'slot') === 'staff';

		// 담당자 모드는 슬롯을 쓰지 않는다. 만들면 쓰지도 않을 행만 쌓인다
		if (!$is_staff_mode)
		{
			// 슬롯을 미리 실체화해 둔다 (규칙이 새로 생겼을 수 있으므로 조회 시 보충 생성)
			Slot::generate($resource);
		}
		else
		{
			$module_srl = self::instanceSrl();
			$branch_srl = max(0, (int)\Context::get('branch_srl'));
			$branches = BranchLink::getList();

			// 지점이 한 곳뿐이면 고르는 단계를 건너뛴다
			if ($branch_srl <= 0 && count($branches) === 1)
			{
				$branch_srl = BranchLink::getSoleBranchSrl();
			}

			$staff_list = StaffModel::getListForService($module_srl, (int)$resource->resource_srl, $branch_srl);

			// 담당자마다 값과 소요시간이 다르다. 화면에서 계산하지 않게 미리 풀어 둔다
			foreach ($staff_list as $person)
			{
				Lang::staff($person);
				$resolved = StaffModel::resolveService($resource, $person);
				$person->resolved_price = (int)$resolved['price'];
				$person->resolved_duration = (int)$resolved['duration'];
				$person->branch_name = isset($branches[(int)$person->branch_srl]) ? (string)$branches[(int)$person->branch_srl]->name : '';
				$person->initial = mb_substr((string)$person->name, 0, 1);
			}

			\Context::set('staff_list', array_values($staff_list));
			\Context::set('branches', array_values($branches));
			\Context::set('branch_srl', $branch_srl);
			\Context::set('allow_any_staff', (string)(self::config()->allow_any_staff ?? 'Y') === 'Y');
		}

		\Context::set('resource', $resource);
		\Context::set('is_staff_mode', $is_staff_mode);
		\Context::set('rsv_config', self::frontConfig());
		\Context::set('rsv_locale', (string)\Context::getLangType());
		$this->addResourceStructuredData($resource);
		$this->setManageLinks();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('calendar');
	}

	/**
	 * 예약 자원의 구조화 데이터. 코어가 head 에 한 번에 출력한다.
	 *
	 * 날짜가 정해진 행사가 아니라 예약을 받는 서비스이므로 Event 가 아니라 Service 로 낸다.
	 */
	protected function addResourceStructuredData(object $resource): void
	{
		if (!method_exists('\Context', 'addStructuredData'))
		{
			return;
		}

		$image = trim((string)($resource->thumb ?? ''));
		if ($image !== '' && !preg_match('#^https?://#', $image))
		{
			$image = \Zittme\Framework\URL::getCurrentDomainURL('/') . ltrim(preg_replace('#^\./#', '', $image), '/');
		}

		$price = (int)($resource->price ?? 0);
		$offer = [];
		if ($price > 0)
		{
			$offer = [
				'@type' => 'Offer',
				'price' => (string)$price,
				'priceCurrency' => 'KRW',
				'availability' => 'https://schema.org/InStock',
				'url' => \Context::getCanonicalURL() ?: \Zittme\Framework\URL::getCurrentURL(),
			];
		}

		\Context::addStructuredData('Service', [
			'name' => trim((string)($resource->title ?? '')),
			'description' => trim(utf8_normalize_spaces(strip_tags((string)($resource->summary ?? '')))),
			'image' => $image,
			'category' => trim((string)($resource->category ?? '')),
			'provider' => [
				'@type' => 'Organization',
				'name' => trim((string)\Context::getSiteTitle()),
			],
			'offers' => $offer,
		]);
	}

	/**
	 * 예약 폼.
	 */
	public function dispReservationForm()
	{
		Remind::runThrottled();

		$resource = $this->requireResource();
		if ($resource instanceof \BaseObject)
		{
			return $resource;
		}

		$is_staff_mode = (string)($resource->booking_mode ?? 'slot') === 'staff';
		$slot = null;
		$staff = null;
		$pick_date = '';
		$pick_time = '';
		$price = (int)($resource->price ?? 0);
		$duration = (int)($resource->duration ?? 0);

		if ($is_staff_mode)
		{
			$pick_date = preg_replace('/\D/', '', (string)\Context::get('date'));
			$pick_time = trim((string)\Context::get('start_time'));
			if (strlen($pick_date) !== 8 || Availability::toMinutes($pick_time) === null)
			{
				return new \BaseObject(-1, 'msg_invalid_request');
			}

			$staff = StaffModel::get((int)\Context::get('staff_srl'));
			if ($staff)
			{
				$staff = Lang::staff(clone $staff);
				$resolved = StaffModel::resolveService($resource, $staff);
				$price = (int)$resolved['price'];
				$duration = (int)$resolved['duration'];
				$staff->branch_name = '';
				$branch = BranchLink::get((int)$staff->branch_srl);
				if ($branch)
				{
					$staff->branch_name = (string)$branch->name;
				}
			}
		}
		else
		{
			$slot_srl = (int)\Context::get('slot_srl');
			$slot = Slot::get($slot_srl);
			if (!$slot || (int)$slot->resource_srl !== (int)$resource->resource_srl)
			{
				return new \BaseObject(-1, 'msg_reservation_no_slot');
			}
		}

		$logged_info = \Context::get('logged_info');
		$config = self::config();

		\Context::set('resource', $resource);
		\Context::set('slot', $slot);
		\Context::set('is_staff_mode', $is_staff_mode);
		\Context::set('staff', $staff);
		\Context::set('pick_date', $pick_date);
		\Context::set('pick_time', $pick_time);
		\Context::set('rsv_when', $slot
			? Lang::date((string)$slot->slot_date, (string)$slot->start_time) . ' ~ ' . $slot->end_time
			: Lang::date((string)$pick_date, (string)$pick_time));
		\Context::set('pick_price', $price);
		\Context::set('pick_duration', $duration);
		\Context::set('form_fields', Lang::formFields(Booking::getFormFields((int)$resource->resource_srl)));
		\Context::set('rsv_config', self::frontConfig());
		\Context::set('is_member', $logged_info && $logged_info->member_srl ? true : false);
		/* 결제 방식이 있으면 그쪽을 따르고, 그 칸이 없던 시절의 자원은 옛 표시를 본다 */
		$pay_mode = (string)($resource->pay_mode ?? 'none');
		if ($pay_mode === 'none' && ($resource->require_payment ?? 'N') === 'Y')
		{
			$pay_mode = 'full';
		}

		$upfront = 0;
		if ($pay_mode === 'full')
		{
			$upfront = $price;
		}
		elseif ($pay_mode === 'deposit')
		{
			$upfront = min($price, max(0, (int)($resource->deposit_amount ?? 0)));
		}

		// 혜택. 회원이 아니면 계산할 것이 없다
		$member_srl = ($logged_info && $logged_info->member_srl) ? (int)$logged_info->member_srl : 0;
		$grade = $member_srl > 0 ? Grade::getForMember($member_srl) : null;
		$grade_off = $member_srl > 0 ? ($price - Grade::applyDiscount($price, Grade::discountFor($member_srl))) : 0;
		$after_grade = max(0, $price - $grade_off);

		\Context::set('my_grade', $grade);
		\Context::set('grade_discount', $grade_off);
		\Context::set('credit_enabled', (string)($config->credit_enabled ?? 'N') === 'Y');
		\Context::set('coupon_enabled', (string)($config->coupon_enabled ?? 'N') === 'Y');
		\Context::set('credit_balance', $member_srl > 0 ? Credit::balanceOf($member_srl) : 0);
		\Context::set('credit_usable', $member_srl > 0 ? Credit::usableFor($member_srl, $after_grade) : 0);
		\Context::set('my_coupons', ($member_srl > 0 && (string)($config->coupon_enabled ?? 'N') === 'Y')
			? Coupon::listUsableForMember($member_srl, $after_grade, (int)$resource->resource_srl)
			: []);

		// 예약금은 할인 뒤 금액을 기준으로 다시 잡는다. 화면과 서버가 다른 값을 말하면 안 된다
		$upfront = min($after_grade, $upfront);

		\Context::set('pay_mode', $pay_mode);
		\Context::set('upfront_amount', $upfront);
		\Context::set('need_pay', $upfront > 0);
		\Context::set('pay_available', self::isPayAvailable());
		$this->setManageLinks();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('form');
	}

	/**
	 * 예약 결과·상세.
	 *
	 * 회원은 본인 예약만, 비회원은 이 세션에서 예약하거나 조회로 확인한 예약만 연다.
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
			$authorized = self::hasGuestAccess((string)$booking->booking_code);
		}
		if (!$authorized)
		{
			return new \BaseObject(-1, 'msg_reservation_not_yours');
		}

		$slot = Slot::get((int)$booking->slot_srl);
		$resource_output = executeQuery('reservation.getResource', (object)['resource_srl' => (int)$booking->resource_srl]);
		$resource = ($resource_output->toBool() && is_object($resource_output->data)) ? Lang::resource($resource_output->data) : null;

		$when = '';
		if ($slot)
		{
			$when = Lang::date((string)$slot->slot_date, (string)$slot->start_time) . ' ~ ' . $slot->end_time;
		}
		elseif (!empty($booking->service_date))
		{
			$start = (string)($booking->start_datetime ?? '');
			$when = Lang::date((string)$booking->service_date, strlen($start) >= 12 ? substr($start, 8, 2) . ':' . substr($start, 10, 2) : '');
		}

		\Context::set('booking', $booking);
		\Context::set('slot', $slot);
		\Context::set('rsv_when', $when);
		\Context::set('resource', $resource);
		\Context::set('rsv_config', self::frontConfig());
		$this->setManageLinks();
		$this->setTemplatePath($this->getSkinPath());
		$this->setTemplateFile('result');
	}

	/**
	 * 내 예약 (회원) / 비회원 조회 폼.
	 */
	public function dispReservationMy()
	{
		Remind::runThrottled();

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
		\Context::set('my_grade', $member_srl > 0 ? Grade::getForMember($member_srl) : null);
		\Context::set('credit_balance', $member_srl > 0 ? Credit::balanceOf($member_srl) : 0);
		\Context::set('credit_logs', $member_srl > 0 ? Credit::getLogs($member_srl, 20) : []);
		\Context::set('my_coupons', $member_srl > 0 ? Coupon::listMine($member_srl) : []);
		\Context::set('rsv_config', self::frontConfig());
		$this->setManageLinks();
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
		return Lang::resource($resource);
	}

	/**
	 * 화면용 설정. 동의 문구를 현재 언어 값으로 바꾼 사본을 준다.
	 *
	 * @return object
	 */
	protected static function frontConfig(): object
	{
		$config = clone self::config();
		$config->privacy_text_raw = (string)($config->privacy_text ?? '');
		$config->privacy_text = Lang::privacyText($config->privacy_text_raw);
		return $config;
	}

	/**
	 * 사이트 관리자에게만 보이는 운영 바로가기(관리자 화면·예약 콘솔).
	 *
	 * 스킨과 테마는 $rsv_manage_links 가 비어 있지 않을 때만 버튼을 그린다.
	 *
	 * @return void
	 */
	protected function setManageLinks(): void
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || ($logged_info->is_admin ?? 'N') !== 'Y')
		{
			\Context::set('rsv_manage_links', []);
			return;
		}
		\Context::set('rsv_manage_links', [
			'admin' => getUrl('', 'module', 'admin', 'act', 'dispReservationAdminDashboard'),
			'console' => getUrl('', 'act', 'dispReservationConsole'),
		]);
	}
}
