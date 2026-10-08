<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Booking as BookingModel;
use Zittme\Modules\Reservation\Models\BranchLink;
use Zittme\Modules\Reservation\Models\Config as ConfigModel;
use Zittme\Modules\Reservation\Models\Coupon;
use Zittme\Modules\Reservation\Models\Credit;
use Zittme\Modules\Reservation\Models\Grade;
use Zittme\Modules\Reservation\Models\Lang;
use Zittme\Modules\Reservation\Models\Remind;
use Zittme\Modules\Reservation\Models\Settlement as SettlementModel;
use Zittme\Modules\Reservation\Models\Slot;
use Zittme\Modules\Reservation\Models\Staff as StaffModel;

/**
 * 전용 운영 화면.
 *
 * 예약은 "설정"이 아니라 일상 운영 업무가 중심이다. 대시보드·예약 관리·예약상품 관리를
 * 독립 페이지 세트로 제공한다. 디자인은 관리자 리디자인 토큰(Pretendard, #2677e3)을 따른다.
 *
 * 수동 예약도 반드시 BookingModel::create 의 원자 점유 경로를 탄다.
 */
class Admin extends Base
{
	/**
	 * 설정 저장을 허용할 키 (요청 값을 그대로 붓지 않는다).
	 */
	public const CONFIG_FIELDS = [
		'enabled', 'code_prefix', 'hold_minutes', 'generate_days', 'max_active_per_member',
		'allow_guest', 'privacy_text', 'privacy_version', 'retention_days',
		'notify_admin', 'notify_admin_email', 'refund_policy',
		'credit_enabled', 'credit_rate', 'credit_min_use', 'credit_max_use_rate', 'coupon_enabled',
		'notify_mail', 'notify_sms', 'notify_on_booked', 'notify_on_confirmed', 'notify_on_cancelled',
		'notify_remind', 'remind_hours', 'sms_from', 'slot_unit', 'allow_any_staff',
	];

	/**
	 * 다국어 문구를 연결할 수 있는 설정.
	 */
	protected const LANG_CONFIG_FIELDS = ['privacy_text'];

	protected const BOOLEAN_FIELDS = [
		'enabled', 'allow_guest', 'notify_admin', 'credit_enabled', 'coupon_enabled',
		'notify_mail', 'notify_sms', 'notify_on_booked', 'notify_on_confirmed', 'notify_on_cancelled',
		'notify_remind', 'allow_any_staff',
	];
	protected const INT_FIELDS = [
		'remind_hours' => [1, 168],
		'slot_unit' => [5, 120],
		'hold_minutes' => [3, 120],
		'generate_days' => [7, 366],
		'max_active_per_member' => [0, 100],
		'retention_days' => [0, 3650],
		'credit_min_use' => [0, 10000000],
	];

	/**
	 * 소수를 허용하는 설정. [최소, 최대]
	 */
	protected const FLOAT_FIELDS = [
		'credit_rate' => [0, 100],
		'credit_max_use_rate' => [0, 100],
	];

	/**
	 * 공통 컨텍스트 + 템플릿.
	 */
	protected function renderView(string $tab, string $file): void
	{
		\Context::set('rsv_tab', $tab);
		\Context::set('rsv_config', self::config());
		$this->setTemplatePath($this->module_path . 'views/admin/');
		$this->setTemplateFile($file);
	}

	/**
	 * 리소스 전체 (상태 무관).
	 *
	 * @return array<int, object> resource_srl => resource
	 */
	protected static function getAllResources(): array
	{
		$output = executeQuery('reservation.getResourceList', new \stdClass);
		$map = [];
		if ($output->toBool() && !empty($output->data))
		{
			foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
			{
				if (!empty($row->resource_srl))
				{
					$map[(int)$row->resource_srl] = Lang::resource($row);
				}
			}
		}
		return $map;
	}

	// ────────────────────────── 화면 ──────────────────────────

	/**
	 * 대시보드 — 오늘/이번 주 요약.
	 */
	public function dispReservationAdminDashboard()
	{
		BookingModel::expireStaleHolds();
		Remind::runThrottled();

		$today = self::localDay();
		$week_end = self::localDay(6);
		$active = implode(',', self::OCCUPYING_STATUSES);

		$count = function(array $args): int {
			$output = executeQuery('reservation.getBookingCount', (object)$args);
			return $output->toBool() ? (int)($output->data->count ?? 0) : 0;
		};

		\Context::set('stat_today', $count(['status_list' => $active, 'from_date' => $today, 'to_date' => $today]));
		\Context::set('stat_week', $count(['status_list' => $active, 'from_date' => $today, 'to_date' => $week_end]));
		\Context::set('stat_wait', $count(['status_list' => self::STATUS_HOLD . ',' . self::STATUS_PENDING]));

		// 임박 예약 목록 (오늘부터, 확정 위주)
		$output = executeQuery('reservation.getBookingList', (object)[
			'status_list' => $active,
			'from_date' => $today,
			'sort_index' => 'slot.slot_date',
			'order_type' => 'asc',
			'list_count' => 10,
		]);
		\Context::set('upcoming', ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : []);
		\Context::set('resources_map', self::getAllResources());
		\Context::set('pay_available', self::isPayAvailable());

		$this->renderView('dashboard', 'dashboard');
	}

	/**
	 * 예약 관리 — 리스트 + 필터 + 상세 처리.
	 */
	public function dispReservationAdminBookings()
	{
		BookingModel::expireStaleHolds();

		$args = new \stdClass;
		$status = trim((string)\Context::get('f_status'));
		if ($status !== '')
		{
			$args->status_list = $status;
		}
		$resource_srl = (int)\Context::get('f_resource');
		if ($resource_srl > 0)
		{
			$args->resource_srl = $resource_srl;
		}
		$from = preg_replace('/\D/', '', (string)\Context::get('f_from'));
		$to = preg_replace('/\D/', '', (string)\Context::get('f_to'));
		if (strlen($from) === 8)
		{
			$args->from_date = $from;
		}
		if (strlen($to) === 8)
		{
			$args->to_date = $to;
		}
		$keyword = trim((string)\Context::get('f_keyword'));
		if ($keyword !== '')
		{
			$args->search_keyword = '%' . $keyword . '%';
		}
		$args->page = max(1, (int)\Context::get('page'));
		$args->list_count = 20;

		$output = executeQuery('reservation.getBookingList', $args);
		$bookings = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		\Context::set('bookings', $bookings);
		\Context::set('page_navigation', $output->page_navigation ?? null);
		\Context::set('resources_map', self::getAllResources());
		\Context::set('filters', (object)[
			'status' => $status, 'resource' => $resource_srl,
			'from' => $from, 'to' => $to, 'keyword' => $keyword,
		]);

		$this->renderView('bookings', 'bookings');
	}

	/**
	 * 예약상품 관리 — 목록.
	 */
	public function dispReservationAdminResources()
	{
		\Context::set('resources', array_values(self::getAllResources()));
		$this->renderView('resources', 'resources');
	}

	/**
	 * 예약상품 편집 — 리소스 + 그 리소스의 운영 규칙·휴무를 한 화면에서.
	 */
	public function dispReservationAdminResourceEdit()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$resource = null;
		$rules = [];
		$holidays = [];

		if ($resource_srl > 0)
		{
			$output = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
			$resource = ($output->toBool() && is_object($output->data) && !empty($output->data->resource_srl)) ? $output->data : null;
			if (!$resource)
			{
				return new \BaseObject(-1, 'msg_reservation_no_resource');
			}

			$output = executeQuery('reservation.getRuleList', (object)['resource_srl' => $resource_srl]);
			if ($output->toBool() && !empty($output->data))
			{
				$rules = is_array($output->data) ? $output->data : [$output->data];
			}
			$output = executeQuery('reservation.getHolidayList', (object)['resource_srl' => $resource_srl]);
			if ($output->toBool() && !empty($output->data))
			{
				$holidays = is_array($output->data) ? $output->data : [$output->data];
			}
		}

		\Context::set('resource', $resource ? Lang::resource($resource) : null);
		\Context::set('rules', $rules);
		\Context::set('holidays', $holidays);
		$this->renderView('resources', 'resource_edit');
	}

	/**
	 * 운영 일정 — 전체 리소스 통합 (휴무·임시오픈 관리).
	 */
	public function dispReservationAdminSchedule()
	{
		$output = executeQuery('reservation.getHolidayList', (object)['resource_srl' => 0]);
		$holidays = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		\Context::set('holidays', $holidays);
		\Context::set('resources_map', self::getAllResources());
		$this->renderView('schedule', 'schedule');
	}

	/**
	 * 추가 문항.
	 */
	public function dispReservationAdminForms()
	{
		$output = executeQuery('reservation.getFormFieldList', (object)['resource_srl' => 0]);
		$fields = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];

		\Context::set('fields', Lang::formFields($fields));
		\Context::set('resources_map', self::getAllResources());
		$this->renderView('forms', 'forms');
	}

	/**
	 * 통계 — 기간별 상태 집계.
	 */
	public function dispReservationAdminStats()
	{
		$from = preg_replace('/\D/', '', (string)\Context::get('f_from')) ?: self::localDay(-29);
		$to = preg_replace('/\D/', '', (string)\Context::get('f_to')) ?: self::localDay();

		$count = function(string $status_list) use ($from, $to): int {
			$output = executeQuery('reservation.getBookingCount', (object)[
				'status_list' => $status_list, 'from_date' => $from, 'to_date' => $to,
			]);
			return $output->toBool() ? (int)($output->data->count ?? 0) : 0;
		};

		$confirmed = $count(self::STATUS_CONFIRMED . ',' . self::STATUS_DONE);
		$cancelled = $count(self::STATUS_CANCELLED);
		$noshow = $count(self::STATUS_NOSHOW);
		$total = $confirmed + $cancelled + $noshow;

		\Context::set('stats', (object)[
			'from' => $from, 'to' => $to,
			'confirmed' => $confirmed, 'cancelled' => $cancelled, 'noshow' => $noshow, 'total' => $total,
			'noshow_rate' => $confirmed + $noshow > 0 ? round($noshow / ($confirmed + $noshow) * 100, 1) : 0,
			'cancel_rate' => $total > 0 ? round($cancelled / $total * 100, 1) : 0,
		]);
		$this->renderView('stats', 'stats');
	}

	/**
	 * 설정.
	 */
	public function dispReservationAdminConfig()
	{
		\Context::set('pay_available', self::isPayAvailable());
		$privacy = (string)(self::config()->privacy_text ?? '');
		\Context::set('rsv_privacy_input', $privacy === ConfigModel::LEGACY_PRIVACY_TEXT ? '' : $privacy);

		// 스킨 — 커머스 콘솔과 같은 방식. 기본값(/USE_DEFAULT/)이면 사이트 기본 디자인을 따른다.
		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		\Context::set('rsv_instance', $module_info);
		\Context::set('rsv_skins', \ModuleModel::getSkins(\RX_BASEDIR . 'modules/reservation') ?: []);
		\Context::set('rsv_default_skin', (string)(\ModuleModel::getModuleDefaultSkin('reservation', 'P') ?: 'default'));

		// 레이아웃 — 스킨과 한자리에서 고르게 둔다. 모듈 관리 화면까지 찾아가지 않아도 된다
		$layout_model = getModel('layout');
		\Context::set('rsv_layouts', $layout_model->getLayoutList(0, 'P') ?: []);
		\Context::set('rsv_mlayouts', $layout_model->getLayoutList(0, 'M') ?: []);
		$this->renderView('config', 'config');
	}

	/**
	 * 스킨 저장 — 기본 인스턴스(mid)의 skin 갱신.
	 */
	public function procReservationAdminUpdateSkin()
	{
		$instance = self::getDefaultInstance();
		$module_info = $instance ? \ModuleModel::getModuleInfoByMid($instance->mid) : null;
		if (!$module_info || empty($module_info->module_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$skin = preg_replace('/[^A-Za-z0-9_\-.\/|@]/', '', (string)\Context::get('skin'));
		if ($skin !== '')
		{
			$module_info->skin = $skin;
			// is_skin_fix 가 N 이면 코어가 저장된 스킨을 무시하고 기본 디자인을 따른다
			$module_info->is_skin_fix = ($skin === '/USE_DEFAULT/') ? 'N' : 'Y';
		}

		// 레이아웃 — -1 은 사이트 기본, -2 는 모바일에서 PC 설정을 따름
		$layout_srl = \Context::get('layout_srl');
		if ($layout_srl !== null && $layout_srl !== '')
		{
			$module_info->layout_srl = (int)$layout_srl;
		}
		$mlayout_srl = \Context::get('mlayout_srl');
		if ($mlayout_srl !== null && $mlayout_srl !== '')
		{
			$module_info->mlayout_srl = (int)$mlayout_srl;
		}
		$module_info->isMenuCreate = false;

		$output = \ModuleController::getInstance()->updateModule($module_info);
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminConfig'));
	}

	// ────────────────────────── 처리 ──────────────────────────

	/**
	 * 다국어 코드 목록 — 이미 만들어 둔 코드를 골라 쓰기 위한 검색.
	 */
	public function procReservationAdminGetLangCodes()
	{
		$rows = [];
		foreach (Lang::search((string)\Context::get('keyword'), 40) as $row)
		{
			$rows[] = ['code' => $row->code, 'value' => $row->value];
		}
		$this->add('codes', $rows);
	}

	/**
	 * 다국어 코드 하나의 언어별 값.
	 */
	public function procReservationAdminGetLangCode()
	{
		$code = Lang::filterCode((string)\Context::get('code'));
		$this->add('code', $code);
		$this->add('values', Lang::values($code));
	}

	/**
	 * 다국어 코드 저장 — 코어 lang 테이블에 그대로 쓴다.
	 */
	public function procReservationAdminSaveLangCode()
	{
		$values = \Context::get('values');
		$code = Lang::save((string)\Context::get('code'), is_array($values) ? $values : []);
		if ($code === '')
		{
			return new \BaseObject(-1, lang('reservation.rsv_adm_lang_need_value'));
		}
		$this->add('code', $code);
		$this->add('value', Lang::display($code));
	}

	/**
	 * 설정 저장 (허용 키만).
	 */
	public function procReservationAdminInsertConfig()
	{
		$config = \ModuleModel::getModuleConfig('reservation') ?: new \stdClass;

		foreach (self::CONFIG_FIELDS as $key)
		{
			$value = \Context::get($key);
			if ($value === null)
			{
				continue;
			}
			if (in_array($key, self::BOOLEAN_FIELDS, true))
			{
				$value = $value === 'Y' ? 'Y' : 'N';
			}
			elseif (isset(self::INT_FIELDS[$key]))
			{
				[$min, $max] = self::INT_FIELDS[$key];
				$value = max($min, min($max, (int)$value));
			}
			elseif (isset(self::FLOAT_FIELDS[$key]))
			{
				[$min, $max] = self::FLOAT_FIELDS[$key];
				$value = max($min, min($max, round((float)$value, 2)));
			}
			elseif (in_array($key, self::LANG_CONFIG_FIELDS, true))
			{
				$value = Lang::fromRequest($key, trim((string)$value));
			}
			else
			{
				$value = trim((string)$value);
			}
			$config->{$key} = $value;
		}

		ConfigModel::setConfig($config);
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminConfig'));
	}

	/**
	 * 대표 이미지 업로드 처리.
	 *
	 * 정사각형 노출을 전제로 하지만 원본은 그대로 저장하고 CSS(cover)로 자른다.
	 *
	 * @param int $resource_srl
	 * @return ?string 저장된 경로 (업로드가 없으면 null)
	 */
	protected function saveThumb(int $resource_srl): ?string
	{
		$file = $_FILES['thumb_file'] ?? null;
		if (!$file || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']))
		{
			return null;
		}
		if ((int)$file['size'] > 10 * 1024 * 1024)
		{
			return null;
		}

		// 실제 이미지인지 내용으로 검사한다 (확장자 위장 방지)
		$info = @getimagesize($file['tmp_name']);
		$ext_map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
		if (!$info || !isset($ext_map[$info[2]]))
		{
			return null;
		}

		$dir = \RX_BASEDIR . 'files/attach/images/reservation/' . $resource_srl . '/';
		\Rhymix\Framework\Storage::createDirectory($dir);
		$filename = 'thumb_' . date('YmdHis') . '.' . $ext_map[$info[2]];
		if (!@move_uploaded_file($file['tmp_name'], $dir . $filename))
		{
			return null;
		}
		return \RX_BASEURL . 'files/attach/images/reservation/' . $resource_srl . '/' . $filename;
	}

	/**
	 * 리소스 저장 (신규/수정) + 슬롯 재생성.
	 */
	public function procReservationAdminInsertResource()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$fields = (object)[
			'title' => Lang::fromRequest('title', mb_substr($title, 0, 250)),
			'summary' => Lang::fromRequest('summary', mb_substr(trim((string)\Context::get('summary')), 0, 250)),
			'content' => (string)\Context::get('content'),
			'capacity_default' => max(1, min(1000, (int)\Context::get('capacity_default'))),
			'duration' => max(5, min(1440, (int)\Context::get('duration'))),
			'price' => self::amountFromInput((string)\Context::get('price')),
			'require_payment' => \Context::get('require_payment') === 'Y' ? 'Y' : 'N',
			'buffer_before' => max(0, min(240, (int)\Context::get('buffer_before'))),
			'buffer_after' => max(0, min(240, (int)\Context::get('buffer_after'))),
			'max_advance_days' => max(1, min(366, (int)(\Context::get('max_advance_days') ?: 90))),
			'min_lead_minutes' => max(0, min(10080, (int)\Context::get('min_lead_minutes'))),
			'cancel_deadline_hours' => max(0, min(720, (int)\Context::get('cancel_deadline_hours'))),
			'booking_mode' => \Context::get('booking_mode') === 'staff' ? 'staff' : 'slot',
			'category' => Lang::fromRequest('category', mb_substr(trim((string)\Context::get('category')), 0, 100)),
			'pay_mode' => in_array((string)\Context::get('pay_mode'), ['deposit', 'full'], true) ? (string)\Context::get('pay_mode') : 'none',
			'deposit_amount' => self::amountFromInput((string)\Context::get('deposit_amount')),
			'status' => \Context::get('status') === 'closed' ? 'closed' : 'open',
			'list_order' => (int)\Context::get('list_order'),
			'last_update' => self::now(),
		];

		// 폼에 칸이 없는 값(다른 화면·옛 스킨에서 저장)은 기존 값을 지킨다. 비어 오면 결제 없음으로 바뀌던 문제
		$existing = null;
		if ($resource_srl > 0)
		{
			$prev = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
			$existing = ($prev->toBool() && is_object($prev->data)) ? $prev->data : null;
		}
		if ($existing)
		{
			foreach (['booking_mode', 'category', 'pay_mode', 'deposit_amount'] as $key)
			{
				if (\Context::get($key) === null)
				{
					$fields->{$key} = $existing->{$key} ?? $fields->{$key};
				}
			}
		}
		if (\Context::get('pay_mode') === null && \Context::get('require_payment') === 'Y' && $fields->pay_mode === 'none')
		{
			$fields->pay_mode = 'full';
		}

		// 결제 방식과 옛 표시를 어긋나게 두지 않는다. 화면 한쪽만 보고 판단하는 코드가 있다
		$fields->require_payment = $fields->pay_mode === 'none' ? 'N' : 'Y';

		$is_new = $resource_srl <= 0;
		if ($is_new)
		{
			$resource_srl = getNextSequence();
		}

		// 대표 이미지: 새 업로드가 있으면 교체, 삭제 체크 시 비움, 아니면 유지
		$thumb = $this->saveThumb($resource_srl);
		if ($thumb !== null)
		{
			$fields->thumb = $thumb;
		}
		elseif (\Context::get('thumb_delete') === 'Y')
		{
			$fields->thumb = '';
		}

		$fields->resource_srl = $resource_srl;
		if ($is_new)
		{
			$fields->module_srl = 0;
			$fields->thumb = $fields->thumb ?? ($thumb ?: '');
			$fields->regdate = self::now();
			$output = executeQuery('reservation.insertResource', $fields);
		}
		else
		{
			$output = executeQuery('reservation.updateResource', $fields);
		}
		if (!$output->toBool())
		{
			return $output;
		}

		// 저장 직후 슬롯 보충 생성 (규칙이 있다면)
		$fresh = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
		if ($fresh->toBool() && is_object($fresh->data) && !empty($fresh->data->resource_srl))
		{
			Slot::generate($fresh->data, 0, true);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource_srl));
	}

	/**
	 * 리소스 삭제.
	 *
	 * 예약이 붙어 있으면 지우지 않고 닫는다(closed) — 이력 보존.
	 */
	public function procReservationAdminDeleteResource()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		if ($resource_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$output = executeQuery('reservation.getBookingCount', (object)['resource_srl' => $resource_srl]);
		$has_bookings = $output->toBool() && (int)($output->data->count ?? 0) > 0;

		if ($has_bookings)
		{
			executeQuery('reservation.updateResource', (object)[
				'resource_srl' => $resource_srl,
				'status' => 'closed',
				'last_update' => self::now(),
			]);
			$this->setMessage('msg_reservation_resource_closed');
		}
		else
		{
			executeQuery('reservation.deleteRulesByResource', (object)['resource_srl' => $resource_srl]);
			executeQuery('reservation.deleteSlotsByResource', (object)['resource_srl' => $resource_srl]);
			executeQuery('reservation.deleteResource', (object)['resource_srl' => $resource_srl]);
			$this->setMessage('success_deleted');
		}
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminResources'));
	}

	/**
	 * 운영 규칙 추가.
	 */
	public function procReservationAdminInsertRule()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$start = trim((string)\Context::get('start_time'));
		$end = trim((string)\Context::get('end_time'));
		if ($resource_srl <= 0 || !preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end) || $end <= $start)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 요일 다중 선택 지원
		$weekdays = \Context::get('weekday');
		if (!is_array($weekdays))
		{
			$weekdays = [$weekdays];
		}
		// 간격·정원은 비우면 리소스 기본값을 쓴다
		$res_output = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
		$res = ($res_output->toBool() && is_object($res_output->data)) ? $res_output->data : null;
		$default_interval = $res ? max(5, (int)$res->duration) : 60;

		$created = 0;
		foreach ($weekdays as $weekday)
		{
			$weekday = (int)$weekday;
			if ($weekday < 0 || $weekday > 6)
			{
				continue;
			}
			$output = executeQuery('reservation.insertRule', (object)[
				'rule_srl' => getNextSequence(),
				'resource_srl' => $resource_srl,
				'weekday' => $weekday,
				'start_time' => $start,
				'end_time' => $end,
				'interval_minutes' => max(5, min(1440, (int)(\Context::get('interval_minutes') ?: $default_interval))),
				'capacity' => max(0, min(1000, (int)\Context::get('capacity'))),
				'valid_from' => preg_replace('/\D/', '', (string)\Context::get('valid_from')),
				'valid_to' => preg_replace('/\D/', '', (string)\Context::get('valid_to')),
				'is_active' => 'Y',
				'regdate' => self::now(),
			]);
			if ($output->toBool())
			{
				$created++;
			}
		}
		if (!$created)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		// 새 규칙 반영 — 슬롯 보충 생성
		$fresh = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
		if ($fresh->toBool() && is_object($fresh->data))
		{
			Slot::generate($fresh->data, 0, true);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource_srl));
	}

	/**
	 * 운영 규칙 삭제. (이미 생성된 슬롯은 유지 — 예약이 붙어 있을 수 있다)
	 */
	public function procReservationAdminDeleteRule()
	{
		$rule_srl = (int)\Context::get('rule_srl');
		$resource_srl = (int)\Context::get('resource_srl');
		if ($rule_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('reservation.deleteRule', (object)['rule_srl' => $rule_srl]);
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminResourceEdit', 'resource_srl', $resource_srl));
	}

	/**
	 * 휴무·임시오픈 추가.
	 */
	public function procReservationAdminInsertHoliday()
	{
		$date = preg_replace('/\D/', '', (string)\Context::get('holiday_date'));
		if (strlen($date) !== 8)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$type = \Context::get('holiday_type') === 'extra' ? 'extra' : 'closed';
		$start = trim((string)\Context::get('start_time'));
		$end = trim((string)\Context::get('end_time'));
		if ($type === 'extra' && (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		executeQuery('reservation.insertHoliday', (object)[
			'holiday_srl' => getNextSequence(),
			'resource_srl' => max(0, (int)\Context::get('resource_srl')),
			'holiday_date' => $date,
			'start_time' => preg_match('/^\d{1,2}:\d{2}$/', $start) ? $start : '',
			'end_time' => preg_match('/^\d{1,2}:\d{2}$/', $end) ? $end : '',
			'holiday_type' => $type,
			'reason' => mb_substr(trim((string)\Context::get('reason')), 0, 250),
			'regdate' => self::now(),
		]);

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSchedule'));
	}

	/**
	 * 휴무 삭제.
	 */
	public function procReservationAdminDeleteHoliday()
	{
		$holiday_srl = (int)\Context::get('holiday_srl');
		if ($holiday_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('reservation.deleteHoliday', (object)['holiday_srl' => $holiday_srl]);
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSchedule'));
	}

	/**
	 * 추가 문항 저장.
	 */
	public function procReservationAdminInsertField()
	{
		$label = trim((string)\Context::get('label'));
		$name = strtolower(trim((string)\Context::get('field_name')));
		if ($label === '' || !preg_match('/^[a-z0-9_]{1,80}$/', $name))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		$type = (string)\Context::get('field_type');
		if (!in_array($type, ['text', 'textarea', 'select', 'checkbox', 'tel'], true))
		{
			$type = 'text';
		}

		executeQuery('reservation.insertFormField', (object)[
			'field_srl' => getNextSequence(),
			'resource_srl' => max(0, (int)\Context::get('resource_srl')),
			'field_name' => $name,
			'label' => Lang::fromRequest('label', mb_substr($label, 0, 250)),
			'field_type' => $type,
			'options' => Lang::fromRequest('options', (string)\Context::get('options')),
			'required' => \Context::get('required') === 'Y' ? 'Y' : 'N',
			'list_order' => (int)\Context::get('list_order'),
			'is_active' => 'Y',
			'regdate' => self::now(),
		]);

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminForms'));
	}

	/**
	 * 추가 문항 삭제.
	 */
	public function procReservationAdminDeleteField()
	{
		$field_srl = (int)\Context::get('field_srl');
		if ($field_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		executeQuery('reservation.deleteFormField', (object)['field_srl' => $field_srl]);
		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminForms'));
	}

	/**
	 * 예약 상태 처리 — 확정 / 취소 / 노쇼 / 완료 / 메모.
	 */
	public function procReservationAdminUpdateBooking()
	{
		$booking_srl = (int)\Context::get('booking_srl');
		$booking = BookingModel::get($booking_srl);
		if (!$booking)
		{
			return new \BaseObject(-1, 'msg_reservation_not_found');
		}

		$logged_info = \Context::get('logged_info');
		$actor = $logged_info ? (int)$logged_info->member_srl : 0;
		$action = (string)\Context::get('booking_action');

		switch ($action)
		{
			case 'confirm':
				BookingModel::confirm($booking_srl, $actor);
				break;

			case 'cancel':
				// 유료 건은 전액 환불 시도 후 취소 (관리자 취소는 마감시간 무관)
				if ((int)$booking->pay_order_srl > 0 && self::isPayAvailable()
					&& in_array($booking->status, self::OCCUPYING_STATUSES, true))
				{
					$refund = \Zittme\Modules\Zittme_pay\PayService::cancel(
						(int)$booking->pay_order_srl,
						lang('reservation.msg_reservation_cancel_reason')
					);
					// 환불 실패라도 관리자 판단으로 취소는 계속한다 (로그로 남긴다)
					if (empty($refund->success))
					{
						BookingModel::log($booking_srl, 'memo', '', '', $actor, 'refund failed: ' . (string)($refund->message ?? ''));
					}
				}
				BookingModel::cancelAndRelease($booking_srl, $actor, self::STATUS_CANCELLED);
				break;

			case 'noshow':
				BookingModel::cancelAndRelease($booking_srl, $actor, self::STATUS_NOSHOW);
				break;

			case 'done':
				// 전이에 이긴 쪽만 적립한다. 두 번 눌러도 적립은 한 번이다
				if (BookingModel::transition($booking_srl, [self::STATUS_CONFIRMED], self::STATUS_DONE))
				{
					BookingModel::log($booking_srl, 'done', self::STATUS_CONFIRMED, self::STATUS_DONE, $actor);
					self::rewardVisit($booking_srl);
				}
				break;

			case 'memo':
				executeQuery('reservation.updateBookingStatusIf', (object)[
					'booking_srl' => $booking_srl,
					'status' => $booking->status,
					'from_status_list' => $booking->status,
					'admin_memo' => mb_substr((string)\Context::get('admin_memo'), 0, 2000),
				]);
				BookingModel::log($booking_srl, 'memo', '', '', $actor);
				break;

			default:
				return new \BaseObject(-1, 'msg_invalid_request');
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminBookings'));
	}

	/**
	 * 단골 관리 — 등급, 쿠폰, 적립금을 한 화면에서 본다.
	 */
	public function dispReservationAdminMembership()
	{
		$module_srl = self::instanceSrl();

		\Context::set('grades', Grade::getList($module_srl));
		\Context::set('coupons', Coupon::getList($module_srl));
		\Context::set('resources_map', self::getAllResources());

		$this->renderView('membership', 'membership');
	}

	/**
	 * 등급 등록·수정.
	 */
	public function procReservationAdminInsertGrade()
	{
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_reservation_need_title');
		}

		$grade_srl = (int)\Context::get('grade_srl');
		$discount_type = (string)\Context::get('discount_type');
		if (!in_array($discount_type, ['amount', 'percent'], true))
		{
			$discount_type = '';
		}

		$args = (object)[
			'grade_srl' => $grade_srl,
			'module_srl' => self::instanceSrl(),
			'title' => mb_substr($title, 0, 80),
			'min_spend' => max(0, (int)\Context::get('min_spend')),
			'credit_rate' => max(0, (float)\Context::get('credit_rate')),
			'coupon_srl' => max(0, (int)\Context::get('coupon_srl')),
			'discount_type' => $discount_type,
			'discount_value' => max(0, (float)\Context::get('discount_value')),
			'regdate' => self::now(),
		];

		if ($grade_srl > 0)
		{
			executeQuery('reservation.updateGrade', $args);
		}
		else
		{
			$args->grade_srl = getNextSequence();
			executeQuery('reservation.insertGrade', $args);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminMembership'));
	}

	/**
	 * 등급 삭제.
	 */
	public function procReservationAdminDeleteGrade()
	{
		$grade_srl = (int)\Context::get('grade_srl');
		if ($grade_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		executeQuery('reservation.deleteGrade', (object)['grade_srl' => $grade_srl]);

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminMembership'));
	}

	/**
	 * 쿠폰 등록·수정.
	 */
	public function procReservationAdminInsertCoupon()
	{
		$title = trim((string)\Context::get('title'));
		if ($title === '')
		{
			return new \BaseObject(-1, 'msg_reservation_need_title');
		}

		$coupon_srl = (int)\Context::get('coupon_srl');
		$discount_type = (string)\Context::get('discount_type') === 'percent' ? 'percent' : 'fixed';

		// 코드는 대문자로 통일한다. 손님이 소문자로 적어도 같은 쿠폰이어야 한다
		$code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)\Context::get('code')));

		$args = (object)[
			'coupon_srl' => $coupon_srl,
			'module_srl' => self::instanceSrl(),
			'title' => mb_substr($title, 0, 120),
			'code' => mb_substr($code, 0, 40),
			'discount_type' => $discount_type,
			'discount_value' => max(0, (int)\Context::get('discount_value')),
			'max_discount' => max(0, (int)\Context::get('max_discount')),
			'min_amount' => max(0, (int)\Context::get('min_amount')),
			'resource_srl' => max(0, (int)\Context::get('resource_srl')),
			'use_start' => preg_replace('/\D/', '', (string)\Context::get('use_start')),
			'use_end' => preg_replace('/\D/', '', (string)\Context::get('use_end')),
			'per_member' => max(1, (int)\Context::get('per_member')),
			'total_limit' => max(0, (int)\Context::get('total_limit')),
			'status' => (string)\Context::get('status') === 'N' ? 'N' : 'Y',
			'used_count' => 0,
			'regdate' => self::now(),
		];

		// 날짜만 받으면 하루의 시작과 끝으로 채운다. 종료일 당일도 쓸 수 있어야 한다
		if (strlen($args->use_start) === 8)
		{
			$args->use_start .= '000000';
		}
		if (strlen($args->use_end) === 8)
		{
			$args->use_end .= '235959';
		}

		if ($coupon_srl > 0)
		{
			executeQuery('reservation.updateCoupon', $args);
		}
		else
		{
			$args->coupon_srl = getNextSequence();
			executeQuery('reservation.insertCoupon', $args);
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminMembership'));
	}

	/**
	 * 쿠폰 삭제.
	 */
	public function procReservationAdminDeleteCoupon()
	{
		$coupon_srl = (int)\Context::get('coupon_srl');
		if ($coupon_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		executeQuery('reservation.deleteCoupon', (object)['coupon_srl' => $coupon_srl]);

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminMembership'));
	}

	/**
	 * 쿠폰 수동 발급.
	 */
	public function procReservationAdminIssueCoupon()
	{
		$coupon_srl = (int)\Context::get('coupon_srl');
		$member_srl = self::findMemberSrl((string)\Context::get('member_id'));
		if ($coupon_srl <= 0 || $member_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_reservation_no_member');
		}

		if (!Coupon::issueTo($coupon_srl, $member_srl))
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminMembership'));
	}

	/**
	 * 적립금 수동 지급·차감.
	 */
	public function procReservationAdminAdjustCredit()
	{
		$member_srl = self::findMemberSrl((string)\Context::get('member_id'));
		$amount = (int)\Context::get('amount');
		if ($member_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_reservation_no_member');
		}
		if ($amount === 0)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		Credit::add($member_srl, $amount, 'admin', 0, trim((string)\Context::get('memo')));

		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminMembership'));
	}

	/**
	 * 아이디나 회원번호로 회원을 찾는다.
	 *
	 * @param string $key
	 * @return int
	 */
	protected static function findMemberSrl(string $key): int
	{
		$key = trim($key);
		if ($key === '')
		{
			return 0;
		}

		if (ctype_digit($key))
		{
			$member = \MemberModel::getMemberInfoByMemberSrl((int)$key);
			if ($member && !empty($member->member_srl))
			{
				return (int)$member->member_srl;
			}
		}

		$member = \MemberModel::getMemberInfoByUserID($key);
		return ($member && !empty($member->member_srl)) ? (int)$member->member_srl : 0;
	}

	/**
	 * 방문 완료 보상 — 적립하고 등급을 다시 계산한다.
	 *
	 * 예약만 하고 오지 않은 건에는 주지 않는다. 그래서 확정이 아니라 방문 완료에서 부른다.
	 *
	 * @param int $booking_srl
	 * @return void
	 */
	protected static function rewardVisit(int $booking_srl): void
	{
		$booking = BookingModel::get($booking_srl);
		if (!$booking || (int)$booking->member_srl <= 0)
		{
			return;
		}

		$earned = Credit::earnForBooking($booking);
		if ($earned > 0)
		{
			executeQuery('reservation.updateBookingBenefit', (object)[
				'booking_srl' => $booking_srl,
				'discount_amount' => (int)$booking->discount_amount,
				'coupon_issue_srl' => (int)$booking->coupon_issue_srl,
				'credit_used' => (int)$booking->credit_used,
				'credit_earned' => $earned,
			]);
		}

		// 적립 뒤에 센다. 누적 금액이 바뀌면 등급도 바뀐다
		Grade::recalc((int)$booking->member_srl);
	}

	/**
	 * 수동 예약 등록 (전화 예약 대행).
	 *
	 * 관리자라고 점유 검사를 우회하지 않는다 — 같은 원자 경로.
	 */
	public function procReservationAdminManualBooking()
	{
		$slot_srl = (int)\Context::get('slot_srl');
		$slot = Slot::get($slot_srl);
		if (!$slot)
		{
			return new \BaseObject(-1, 'msg_reservation_no_slot');
		}
		$name = trim((string)\Context::get('booker_name'));
		if ($name === '')
		{
			return new \BaseObject(-1, 'msg_reservation_need_name');
		}

		$logged_info = \Context::get('logged_info');
		$output = BookingModel::create((object)[
			'slot_srl' => $slot_srl,
			'resource_srl' => (int)$slot->resource_srl,
			'member_srl' => 0,
			'booker_name' => $name,
			'booker_phone' => trim((string)\Context::get('booker_phone')),
			'person_count' => max(1, min(100, (int)(\Context::get('person_count') ?: 1))),
			'status' => self::STATUS_CONFIRMED,
			'memo' => mb_substr(trim((string)\Context::get('memo')), 0, 2000),
		]);
		if (!$output->toBool())
		{
			return $output;
		}
		$booking = $output->get('booking');
		BookingModel::log((int)$booking->booking_srl, 'memo', '', '', $logged_info ? (int)$logged_info->member_srl : 0, 'manual booking by admin');

		$this->setMessage('success_registed');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminBookings'));
	}

	/**
	 * 슬롯 잔여 조회 (수동 예약 모달용 JSON).
	 */
	public function procReservationAdminGetBookings()
	{
		$resource_srl = (int)\Context::get('resource_srl');
		$from = preg_replace('/\D/', '', (string)\Context::get('from')) ?: self::localDay();
		$to = preg_replace('/\D/', '', (string)\Context::get('to')) ?: self::localDay(30);

		$slots = [];
		foreach (Slot::getRange($resource_srl, $from, $to) as $slot)
		{
			$slots[] = [
				'slot_srl' => (int)$slot->slot_srl,
				'date' => $slot->slot_date,
				'start' => $slot->start_time,
				'remain' => max(0, (int)$slot->capacity - (int)$slot->booked_count),
				'status' => $slot->status,
			];
		}
		$this->add('slots', $slots);
	}

	/**
	 * 슬롯 수동 마감/해제.
	 */
	public function procReservationAdminCloseSlot()
	{
		$slot_srl = (int)\Context::get('slot_srl');
		$slot = Slot::get($slot_srl);
		if (!$slot)
		{
			return new \BaseObject(-1, 'msg_reservation_no_slot');
		}
		executeQuery('reservation.updateSlotStatus', (object)[
			'slot_srl' => $slot_srl,
			'status' => $slot->status === 'closed' ? 'open' : 'closed',
		]);
		$this->setMessage('success_updated');
		$this->setRedirectUrl(\Context::get('success_return_url') ?: getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSchedule'));
	}

	/**
	 * 담당자 목록.
	 */
	public function dispReservationAdminStaff()
	{
		$module_srl = self::instanceSrl();
		$staff_list = StaffModel::getList($module_srl, null);

		// 담당자마다 몇 가지 시술을 맡는지 목록에서 바로 보이게 한다
		$service_counts = [];
		foreach ($staff_list as $staff_srl => $staff)
		{
			$service_counts[$staff_srl] = count(StaffModel::getServiceMap($staff_srl));
		}

		\Context::set('staff_list', Lang::applyAll(array_values($staff_list), Lang::STAFF_FIELDS));
		\Context::set('staff_service_counts', $service_counts);
		$this->renderView('staff', 'staff');
	}

	/**
	 * 담당자 편집 — 기본 정보, 맡는 시술, 근무 요일을 한 화면에서.
	 */
	public function dispReservationAdminStaffEdit()
	{
		$staff_srl = (int)\Context::get('staff_srl');
		$staff = null;
		$service_map = [];
		$schedules = [];

		if ($staff_srl > 0)
		{
			$staff = StaffModel::get($staff_srl);
			if (!$staff)
			{
				return new \BaseObject(-1, 'msg_reservation_no_staff');
			}

			$service_map = StaffModel::getServiceMap($staff_srl, false);
			$schedules = StaffModel::getSchedules($staff_srl);
		}

		// 편집 화면은 회원 번호가 아니라 아이디로 보여 준다. 번호는 사람이 못 읽는다
		$member_id = '';
		if ($staff && (int)$staff->member_srl > 0)
		{
			$member = \MemberModel::getMemberInfoByMemberSrl((int)$staff->member_srl);
			$member_id = (is_object($member) && !empty($member->user_id)) ? (string)$member->user_id : '';
		}

		\Context::set('staff', $staff ? Lang::staff(clone $staff) : null);
		\Context::set('staff_member_id', $member_id);
		\Context::set('branches', BranchLink::getList());
		\Context::set('service_map', $service_map);
		\Context::set('schedules', $schedules);
		\Context::set('resources', array_values(self::getAllResources()));
		$this->renderView('staff_edit', 'staff_edit');
	}

	/**
	 * 담당자 저장.
	 */
	public function procReservationAdminInsertStaff()
	{
		$name = trim((string)\Context::get('name'));
		if ($name === '')
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}

		$staff_srl = (int)\Context::get('staff_srl');
		$args = (object)[
			'staff_srl' => $staff_srl,
			'module_srl' => self::instanceSrl(),
			'branch_srl' => max(0, (int)\Context::get('branch_srl')),
			'member_srl' => self::resolveMemberSrl((string)\Context::get('member_id')),
			'name' => Lang::fromRequest('name', mb_substr($name, 0, 100)),
			'position' => Lang::fromRequest('position', mb_substr(trim((string)\Context::get('position')), 0, 100)),
			'summary' => Lang::fromRequest('summary', mb_substr(trim((string)\Context::get('summary')), 0, 250)),
			'content' => Lang::fromRequest('content', (string)\Context::get('content')),
			'thumb' => mb_substr(trim((string)\Context::get('thumb')), 0, 250),
			'share_rate' => self::parseRate((string)\Context::get('share_rate')),
			'status' => \Context::get('status') === StaffModel::STATUS_HIDDEN ? StaffModel::STATUS_HIDDEN : StaffModel::STATUS_ACTIVE,
			'list_order' => (int)\Context::get('list_order'),
		];

		$staff_srl = StaffModel::save($args);
		if ($staff_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_reservation_staff_save_failed');
		}

		StaffModel::replaceServices($staff_srl, self::collectServiceRows());
		StaffModel::replaceSchedules($staff_srl, self::collectScheduleRows());

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaffEdit', 'staff_srl', $staff_srl));
	}

	/**
	 * 담당자 삭제. 예약이 걸려 있으면 숨김으로 돌린다.
	 */
	public function procReservationAdminDeleteStaff()
	{
		$staff_srl = (int)\Context::get('staff_srl');
		if (!StaffModel::remove($staff_srl))
		{
			return new \BaseObject(-1, 'msg_reservation_no_staff');
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminStaff'));
	}

	/**
	 * 정산 회차 목록.
	 */
	public function dispReservationAdminSettlements()
	{
		$module_srl = self::instanceSrl();
		$result = SettlementModel::getList($module_srl, [
			'staff_srl' => (int)\Context::get('staff_srl') ?: null,
			'status' => (string)\Context::get('status') ?: null,
			'page' => (int)\Context::get('page'),
		]);

		\Context::set('settlements', $result['list']);
		\Context::set('page_navigation', $result['navigation']);
		\Context::set('staff_map', Lang::applyAll(StaffModel::getList($module_srl, null), Lang::STAFF_FIELDS));
		$this->renderView('settlements', 'settlements');
	}

	/**
	 * 정산 회차 상세.
	 */
	public function dispReservationAdminSettlementView()
	{
		$settlement_srl = (int)\Context::get('settlement_srl');
		$settlement = SettlementModel::get($settlement_srl);
		if (!$settlement)
		{
			return new \BaseObject(-1, 'msg_reservation_no_settlement');
		}

		\Context::set('settlement', $settlement);
		\Context::set('items', SettlementModel::getItems($settlement_srl));
		\Context::set('staff', Lang::staff(StaffModel::get((int)$settlement->staff_srl)));
		\Context::set('resources', self::getAllResources());
		$this->renderView('settlement_view', 'settlement_view');
	}

	/**
	 * 기간을 정해 정산 회차를 만든다.
	 */
	public function procReservationAdminBuildSettlement()
	{
		$staff_srl = (int)\Context::get('staff_srl');
		$from = preg_replace('/\D/', '', (string)\Context::get('period_from'));
		$to = preg_replace('/\D/', '', (string)\Context::get('period_to'));

		$error = '';
		if ($staff_srl <= 0 || !StaffModel::get($staff_srl))
		{
			$error = 'need_staff';
		}
		elseif (strlen($from) !== 8 || strlen($to) !== 8 || !checkdate((int)substr($from, 4, 2), (int)substr($from, 6, 2), (int)substr($from, 0, 4))
			|| !checkdate((int)substr($to, 4, 2), (int)substr($to, 6, 2), (int)substr($to, 0, 4)))
		{
			$error = 'need_period';
		}
		elseif ($from > $to)
		{
			$error = 'period_order';
		}
		elseif (!count(SettlementModel::getTargets(self::instanceSrl(), $staff_srl, $from, $to)))
		{
			$error = 'empty';
		}

		$settlement_srl = 0;
		if ($error === '')
		{
			$settlement_srl = SettlementModel::build(self::instanceSrl(), $staff_srl, $from, $to);
			if ($settlement_srl <= 0)
			{
				$error = 'save_failed';
			}
		}

		if ($error !== '')
		{
			if (in_array(\Context::getRequestMethod(), ['JSON', 'XMLRPC'], true))
			{
				return new \BaseObject(-1, 'msg_rsv_settlement_err_' . $error);
			}
			$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlements',
				'se_error', $error, 'se_staff', $staff_srl ?: '', 'se_from', $from, 'se_to', $to));
			return;
		}

		$this->setMessage('success_registed');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlementView', 'settlement_srl', $settlement_srl));
	}

	/**
	 * 정산 회차 상태 변경.
	 */
	public function procReservationAdminChangeSettlement()
	{
		$settlement_srl = (int)\Context::get('settlement_srl');
		$status = (string)\Context::get('status');

		if (!SettlementModel::changeStatus($settlement_srl, $status))
		{
			return new \BaseObject(-1, 'msg_reservation_settlement_locked');
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlementView', 'settlement_srl', $settlement_srl));
	}

	/**
	 * 집계중인 회차를 지운다. 확정한 회차는 지우지 않는다.
	 */
	public function procReservationAdminDeleteSettlement()
	{
		$settlement_srl = (int)\Context::get('settlement_srl');
		if (!SettlementModel::remove($settlement_srl))
		{
			return new \BaseObject(-1, 'msg_reservation_settlement_locked');
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispReservationAdminSettlements'));
	}

	/**
	 * 화면에서 체크한 시술 연결을 모은다.
	 *
	 * @return array
	 */
	protected static function collectServiceRows(): array
	{
		$checked = (array)\Context::get('svc_use');
		$prices = (array)\Context::get('svc_price');
		$durations = (array)\Context::get('svc_duration');
		$rates = (array)\Context::get('svc_rate');

		$rows = [];
		foreach ($checked as $resource_srl)
		{
			$resource_srl = (int)$resource_srl;
			if ($resource_srl <= 0)
			{
				continue;
			}

			$price = trim((string)($prices[$resource_srl] ?? ''));
			$rate = trim((string)($rates[$resource_srl] ?? ''));

			$rows[$resource_srl] = [
				'price' => $price === '' ? '' : (int)preg_replace('/\D/', '', $price),
				'duration' => max(0, min(1440, (int)($durations[$resource_srl] ?? 0))),
				'share_rate' => $rate === '' ? '' : self::parseRate($rate),
			];
		}

		return $rows;
	}

	/**
	 * 화면에서 적은 근무 요일과 시간을 모은다.
	 *
	 * @return array
	 */
	protected static function collectScheduleRows(): array
	{
		$weekdays = (array)\Context::get('wd_use');
		$starts = (array)\Context::get('wd_start');
		$ends = (array)\Context::get('wd_end');

		$rows = [];
		foreach ($weekdays as $weekday)
		{
			$weekday = (int)$weekday;
			if ($weekday < 0 || $weekday > 6)
			{
				continue;
			}

			$rows[] = [
				'weekday' => $weekday,
				'start_time' => trim((string)($starts[$weekday] ?? '')),
				'end_time' => trim((string)($ends[$weekday] ?? '')),
			];
		}

		return $rows;
	}

	/**
	 * 배분율 입력을 만분율 정수로. 화면에서는 퍼센트로 적는다.
	 *
	 * @param string $input
	 * @return int
	 */
	protected static function parseRate(string $input): int
	{
		$value = (float)str_replace(',', '', trim($input));
		if ($value < 0)
		{
			return 0;
		}

		return (int)min(StaffModel::RATE_BASE, round($value * 100));
	}

	/**
	 * 회원 아이디를 회원 번호로. 비우면 연결 없음이다.
	 *
	 * @param string $user_id
	 * @return int
	 */
	protected static function resolveMemberSrl(string $user_id): int
	{
		$user_id = trim($user_id);
		if ($user_id === '')
		{
			return 0;
		}

		$member = \MemberModel::getMemberInfoByUserID($user_id);
		return (is_object($member) && !empty($member->member_srl)) ? (int)$member->member_srl : 0;
	}
}
