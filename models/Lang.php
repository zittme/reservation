<?php

namespace Zittme\Modules\Reservation\Models;

/**
 * 다국어 문구 — 코어의 사용자 정의 언어(lang 테이블)를 그대로 쓴다.
 *
 * 값은 '$user_lang->코드' 로 저장하고, 화면·알림·결제로 넘기기 전에 현재 언어 값으로 바꾼다.
 */
class Lang
{
	public const PREFIX = '$user_lang->';

	/**
	 * 시술에서 다국어를 쓰는 칸.
	 */
	public const RESOURCE_FIELDS = ['title', 'summary', 'category', 'content'];

	/**
	 * 담당자에서 다국어를 쓰는 칸.
	 */
	public const STAFF_FIELDS = ['name', 'position', 'summary', 'content'];

	/**
	 * 예약 양식 항목에서 다국어를 쓰는 칸.
	 */
	public const FIELD_FIELDS = ['label', 'options'];

	/**
	 * 사이트에 켜둔 언어 목록 (코드 => 이름).
	 *
	 * @return array<string, string>
	 */
	public static function languages(): array
	{
		$langs = \Context::loadLangSelected();
		return is_array($langs) ? $langs : [];
	}

	/**
	 * 값이 다국어 코드면 코드 이름만, 아니면 빈 문자열.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function codeOf($value): string
	{
		$value = trim((string)$value);
		if (strpos($value, self::PREFIX) !== 0)
		{
			return '';
		}
		$code = substr($value, strlen(self::PREFIX));
		return preg_match('/^[a-zA-Z0-9_]+$/', $code) ? $code : '';
	}

	/**
	 * 코드 이름을 저장값으로.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function toValue(string $code): string
	{
		$code = self::filterCode($code);
		return $code === '' ? '' : self::PREFIX . $code;
	}

	/**
	 * 코드 이름 정리 (영문·숫자·밑줄만).
	 *
	 * @param string $code
	 * @return string
	 */
	public static function filterCode(string $code): string
	{
		$code = preg_replace('/[^a-zA-Z0-9_]/', '', trim($code));
		return substr((string)$code, 0, 100);
	}

	/**
	 * 폼에서 온 값. <필드>_langcode 가 있으면 저장값('$user_lang->코드'), 아니면 입력한 글자.
	 *
	 * @param string $field
	 * @param string $fallback
	 * @return string
	 */
	public static function fromRequest(string $field, string $fallback): string
	{
		$code = self::filterCode((string)\Context::get($field . '_langcode'));
		return $code !== '' ? self::toValue($code) : $fallback;
	}

	/**
	 * 코드 하나의 언어별 값.
	 *
	 * @param string $code
	 * @return array<string, string>
	 */
	public static function values(string $code): array
	{
		$code = self::filterCode($code);
		if ($code === '')
		{
			return [];
		}
		$output = executeQueryArray('module.getLang', (object)['name' => $code]);
		$values = [];
		foreach (($output->toBool() ? ($output->data ?: []) : []) as $row)
		{
			$values[(string)$row->lang_code] = (string)$row->value;
		}
		return $values;
	}

	/**
	 * 현재 언어로 읽은 값. 없으면 다른 언어 값, 그것도 없으면 코드 이름.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function display(string $code): string
	{
		$values = self::values($code);
		$lang = \Context::getLangType();
		if (trim((string)($values[$lang] ?? '')) !== '')
		{
			return $values[$lang];
		}
		foreach ($values as $value)
		{
			if (trim($value) !== '')
			{
				return $value;
			}
		}
		return $code;
	}

	/**
	 * 출력용 값. 템플릿이 escape 하면 코어의 최종 치환에 걸리지 않으므로 미리 바꾼다.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function text($value): string
	{
		$value = (string)$value;
		if (strpos($value, self::PREFIX) === false)
		{
			return $value;
		}
		return \Context::replaceUserLang($value);
	}

	/**
	 * 객체의 지정한 칸들을 현재 언어 값으로 바꾼다. 원래 값은 <칸>_raw 에 남긴다.
	 *
	 * @param mixed $row
	 * @param array<int, string> $fields
	 * @return mixed
	 */
	public static function apply($row, array $fields)
	{
		if (!is_object($row) || !empty($row->_rsv_lang_applied))
		{
			return $row;
		}
		foreach ($fields as $field)
		{
			if (isset($row->{$field}) && is_string($row->{$field}))
			{
				$row->{$field . '_raw'} = $row->{$field};
				$row->{$field} = self::text($row->{$field});
			}
		}
		$row->_rsv_lang_applied = true;
		return $row;
	}

	/**
	 * 목록 전체에 apply.
	 *
	 * @param array $rows
	 * @param array<int, string> $fields
	 * @return array
	 */
	public static function applyAll(array $rows, array $fields): array
	{
		foreach ($rows as $row)
		{
			self::apply($row, $fields);
		}
		return $rows;
	}

	/**
	 * 시술 한 건.
	 *
	 * @param mixed $resource
	 * @return mixed
	 */
	public static function resource($resource)
	{
		return self::apply($resource, self::RESOURCE_FIELDS);
	}

	/**
	 * 담당자 한 명.
	 *
	 * @param mixed $staff
	 * @return mixed
	 */
	public static function staff($staff)
	{
		return self::apply($staff, self::STAFF_FIELDS);
	}

	/**
	 * 예약 양식 항목 목록.
	 *
	 * @param array $fields
	 * @return array
	 */
	public static function formFields(array $fields): array
	{
		return self::applyAll($fields, self::FIELD_FIELDS);
	}

	/**
	 * 개인정보 동의 문구. 저장값이 비었거나 예전 기본 문구 그대로면 언어 파일의 기본 문구를 쓴다.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function privacyText($value): string
	{
		$value = trim((string)$value);
		if ($value === '' || $value === Config::LEGACY_PRIVACY_TEXT)
		{
			return (string)lang('reservation.reservation_privacy_default');
		}
		return self::text($value);
	}

	/**
	 * 날짜(Ymd 또는 YmdHis)를 현재 언어 형식으로. 시각이 있으면 H:i 를 붙인다.
	 *
	 * @param string $date
	 * @param string $time 'H:i' 또는 빈 값. 비우면 $date 의 시각을 쓴다
	 * @return string
	 */
	public static function date(string $date, string $time = ''): string
	{
		$digits = preg_replace('/\D/', '', $date);
		if (strlen($digits) < 8)
		{
			return trim($date . ' ' . $time);
		}
		$format = (string)lang('reservation.reservation_date_format');
		if ($format === '' || $format === 'reservation_date_format')
		{
			$format = 'Y-m-d';
		}
		$ts = mktime(0, 0, 0, (int)substr($digits, 4, 2), (int)substr($digits, 6, 2), (int)substr($digits, 0, 4));
		$text = date($format, $ts);
		if ($time === '' && strlen($digits) >= 12)
		{
			$time = substr($digits, 8, 2) . ':' . substr($digits, 10, 2);
		}
		return trim($text . ' ' . $time);
	}

	/**
	 * 금액을 현재 언어 표기로.
	 *
	 * @param int $amount
	 * @return string
	 */
	public static function money(int $amount): string
	{
		return sprintf((string)lang('reservation.reservation_price_format'), number_format($amount));
	}

	/**
	 * 등록된 코드 목록 (검색어로 좁힘). 코드마다 현재 언어 값을 함께 준다.
	 *
	 * @param string $keyword
	 * @param int $limit
	 * @return array<int, object> [{code, value}]
	 */
	public static function search(string $keyword = '', int $limit = 30): array
	{
		$output = executeQueryArray('module.getLang', new \stdClass);
		$rows = $output->toBool() ? ($output->data ?: []) : [];

		$lang = \Context::getLangType();
		$map = [];
		foreach ($rows as $row)
		{
			$code = (string)$row->name;
			if (!isset($map[$code]))
			{
				$map[$code] = ['code' => $code, 'value' => '', 'fallback' => ''];
			}
			$value = (string)$row->value;
			if ((string)$row->lang_code === $lang)
			{
				$map[$code]['value'] = $value;
			}
			elseif ($map[$code]['fallback'] === '')
			{
				$map[$code]['fallback'] = $value;
			}
		}

		$keyword = trim($keyword);
		$result = [];
		foreach ($map as $row)
		{
			$value = $row['value'] !== '' ? $row['value'] : $row['fallback'];
			if ($keyword !== '' && mb_stripos($row['code'], $keyword) === false && mb_stripos($value, $keyword) === false)
			{
				continue;
			}
			$result[] = (object)['code' => $row['code'], 'value' => $value];
			if (count($result) >= $limit)
			{
				break;
			}
		}
		return $result;
	}

	/**
	 * 코드를 만들거나 고친다. 코어의 lang 테이블에 그대로 쓴다.
	 *
	 * @param string $code 비우면 자동 생성
	 * @param array<string, string> $values 언어 코드 => 값
	 * @return string 저장된 코드 이름 ('' = 실패)
	 */
	public static function save(string $code, array $values): string
	{
		$allowed = self::languages();
		$clean = [];
		foreach ($values as $lang => $value)
		{
			$value = trim((string)$value);
			if (isset($allowed[$lang]) && $value !== '')
			{
				$clean[$lang] = $value;
			}
		}
		if (!count($clean))
		{
			return '';
		}

		$code = self::filterCode($code);
		if ($code === '')
		{
			$code = 'rsv_' . date('YmdHis') . sprintf('%03d', mt_rand(0, 999));
		}

		executeQuery('module.deleteLang', (object)['name' => $code]);
		foreach ($clean as $lang => $value)
		{
			executeQuery('module.insertLang', (object)[
				'name' => $code,
				'lang_code' => $lang,
				'value' => $value,
			]);
		}

		\ModuleAdminController::getInstance()->makeCacheDefinedLangCode();

		return $code;
	}
}
