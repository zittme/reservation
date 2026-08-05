<?php

namespace Zittme\Modules\Reservation\Controllers;

use Zittme\Modules\Reservation\Models\Booking as BookingModel;
use Zittme\Modules\Reservation\Models\Config as ConfigModel;
use Zittme\Modules\Reservation\Models\Slot;

/**
 * 전용 운영 화면.
 *
 * 예약은 "설정"이 아니라 일상 운영 업무가 중심이다. 대시보드·예약 관리·예약상품 관리를
 * 독립 페이지 세트로 제공한다. 디자인은 관리자 리디자인 토큰(Pretendard, #2677e3)을 따른다.
 *
 * ★ 수동 예약도 반드시 BookingModel::create 의 원자 점유 경로를 탄다.
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
	];

	protected const BOOLEAN_FIELDS = ['enabled', 'allow_guest', 'notify_admin'];
	protected const INT_FIELDS = [
		'hold_minutes' => [3, 120],
		'generate_days' => [7, 366],
		'max_active_per_member' => [0, 100],
		'retention_days' => [0, 3650],
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
					$map[(int)$row->resource_srl] = $row;
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

		$today = date('Ymd');
		$week_end = date('Ymd', strtotime('+6 day'));
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

		\Context::set('resource', $resource);
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

		\Context::set('fields', $fields);
		\Context::set('resources_map', self::getAllResources());
		$this->renderView('forms', 'forms');
	}

	/**
	 * 통계 — 기간별 상태 집계.
	 */
	public function dispReservationAdminStats()
	{
		$from = preg_replace('/\D/', '', (string)\Context::get('f_from')) ?: date('Ymd', strtotime('-29 day'));
		$to = preg_replace('/\D/', '', (string)\Context::get('f_to')) ?: date('Ymd');

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
		$this->renderView('config', 'config');
	}

	// ────────────────────────── 처리 ──────────────────────────

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
			'title' => mb_substr($title, 0, 250),
			'summary' => mb_substr(trim((string)\Context::get('summary')), 0, 250),
			'content' => (string)\Context::get('content'),
			'capacity_default' => max(1, min(1000, (int)\Context::get('capacity_default'))),
			'duration' => max(5, min(1440, (int)\Context::get('duration'))),
			'price' => max(0, (int)preg_replace('/\D/', '', (string)\Context::get('price'))),
			'require_payment' => \Context::get('require_payment') === 'Y' ? 'Y' : 'N',
			'buffer_before' => max(0, min(240, (int)\Context::get('buffer_before'))),
			'buffer_after' => max(0, min(240, (int)\Context::get('buffer_after'))),
			'max_advance_days' => max(1, min(366, (int)(\Context::get('max_advance_days') ?: 90))),
			'min_lead_minutes' => max(0, min(10080, (int)\Context::get('min_lead_minutes'))),
			'cancel_deadline_hours' => max(0, min(720, (int)\Context::get('cancel_deadline_hours'))),
			'status' => \Context::get('status') === 'closed' ? 'closed' : 'open',
			'list_order' => (int)\Context::get('list_order'),
			'last_update' => self::now(),
		];

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
			'label' => mb_substr($label, 0, 250),
			'field_type' => $type,
			'options' => (string)\Context::get('options'),
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
				BookingModel::transition($booking_srl, [self::STATUS_CONFIRMED], self::STATUS_DONE);
				BookingModel::log($booking_srl, 'done', self::STATUS_CONFIRMED, self::STATUS_DONE, $actor);
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
	 * 수동 예약 등록 (전화 예약 대행).
	 *
	 * ★ 관리자라고 점유 검사를 우회하지 않는다 — 같은 원자 경로.
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
		$from = preg_replace('/\D/', '', (string)\Context::get('from')) ?: date('Ymd');
		$to = preg_replace('/\D/', '', (string)\Context::get('to')) ?: date('Ymd', strtotime('+30 day'));

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
}
