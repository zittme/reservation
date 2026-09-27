<?php

namespace Zittme\Modules\Reservation\Models;

/**
 * 예약 알림.
 *
 * 채널은 셋이다. 메일과 문자는 코어가 발송을 맡고(Zittme\Framework\Mail, SMS),
 * 알림톡은 코어에 발송기가 없어 자리만 만들어 두었다. setAlimtalkSender() 로
 * 발송기를 꽂기 전까지는 문자로 흘려보낸다.
 *
 * 알림은 절대 예약을 막지 않는다. 발송이 실패해도 예약은 그대로 남고
 * 실패 사실만 기록한다. 문자 한 통 때문에 손님의 예약이 사라지면 안 된다.
 */
class Notify
{
	public const CHANNEL_MAIL = 'mail';
	public const CHANNEL_SMS = 'sms';
	public const CHANNEL_ALIMTALK = 'alimtalk';

	/**
	 * 알림 종류. 설정의 notify_on_* 및 언어 키와 이름을 맞춘다.
	 */
	public const TPL_BOOKED = 'booked';
	public const TPL_CONFIRMED = 'confirmed';
	public const TPL_CANCELLED = 'cancelled';
	public const TPL_REMIND = 'remind';
	public const TPL_STAFF_NEW = 'staff_new';

	/**
	 * 알림톡 발송기. 시그니처는 function(string $phone, string $text, array $context): bool
	 *
	 * @var ?callable
	 */
	protected static $_alimtalk_sender = null;

	/**
	 * 알림톡 발송기를 꽂는다. 부가 모듈이나 사이트 코드에서 부른다.
	 *
	 * @param ?callable $sender
	 * @return void
	 */
	public static function setAlimtalkSender(?callable $sender): void
	{
		self::$_alimtalk_sender = $sender;
	}

	/**
	 * 예약 한 건에 대해 알림을 보낸다.
	 *
	 * @param object $booking
	 * @param string $template
	 * @param array $extra 템플릿에 넣을 추가 값
	 * @return void
	 */
	public static function send(object $booking, string $template, array $extra = []): void
	{
		try
		{
			$config = Config::getConfig();
			if ($template === self::TPL_BOOKED && (string)($config->notify_admin ?? 'N') === 'Y')
			{
				$admin_context = self::buildContext($booking, $extra);
				self::sendAdminMail($booking, (string)($config->notify_admin_email ?? ''),
					self::renderSubject($template, $admin_context), self::renderBody($template, $admin_context));
			}
			if (!self::isEnabled($config, $template))
			{
				return;
			}

			$context = self::buildContext($booking, $extra);
			$subject = self::renderSubject($template, $context);
			$body = self::renderBody($template, $context);

			if ((string)($config->notify_mail ?? 'N') === 'Y')
			{
				self::sendMail($booking, $template, $subject, $body);
			}

			$sms_used = false;
			if ((string)($config->notify_alimtalk ?? 'N') === 'Y' && self::$_alimtalk_sender)
			{
				$sms_used = self::sendAlimtalk($booking, $template, $body, $context);
			}

			if (!$sms_used && (string)($config->notify_sms ?? 'N') === 'Y')
			{
				self::sendSMS($booking, $template, $body);
			}
		}
		catch (\Throwable $e)
		{
			// 알림 실패가 예약 흐름을 끊지 않게 한다
			self::log($booking, 'mail', $template, '', 'failed', $e->getMessage());
		}
	}

	/**
	 * 이 알림을 보내기로 설정되어 있는가.
	 *
	 * @param object $config
	 * @param string $template
	 * @return bool
	 */
	protected static function isEnabled(object $config, string $template): bool
	{
		$key = [
			self::TPL_BOOKED => 'notify_on_booked',
			self::TPL_CONFIRMED => 'notify_on_confirmed',
			self::TPL_CANCELLED => 'notify_on_cancelled',
			self::TPL_REMIND => 'notify_remind',
		][$template] ?? null;

		if ($key === null)
		{
			return true;
		}

		return (string)($config->{$key} ?? 'N') === 'Y';
	}

	/**
	 * 문구에 넣을 값들.
	 *
	 * @param object $booking
	 * @param array $extra
	 * @return array
	 */
	protected static function buildContext(object $booking, array $extra): array
	{
		$date = (string)($booking->service_date ?? '');
		$when = $date !== '' ? substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) : '';

		$start = (string)($booking->start_datetime ?? '');
		if ($start !== '' && strlen($start) >= 12)
		{
			$when .= ' ' . substr($start, 8, 2) . ':' . substr($start, 10, 2);
		}

		$context = [
			'name' => (string)($booking->booker_name ?? ''),
			'code' => (string)($booking->booking_code ?? ''),
			'when' => trim($when),
			'service' => '',
			'staff' => '',
			'site' => (string)(\Context::get('site_title') ?: ''),
		];

		$resource_srl = (int)($booking->resource_srl ?? 0);
		if ($resource_srl > 0)
		{
			$output = executeQuery('reservation.getResource', (object)['resource_srl' => $resource_srl]);
			if ($output->toBool() && is_object($output->data))
			{
				$context['service'] = (string)$output->data->title;
			}
		}

		$staff = Staff::get((int)($booking->staff_srl ?? 0));
		if ($staff)
		{
			$context['staff'] = (string)$staff->name;
		}

		return array_merge($context, $extra);
	}

	/**
	 * 제목 문구. 언어 파일의 notify_subject_* 를 쓴다.
	 *
	 * @param string $template
	 * @param array $context
	 * @return string
	 */
	protected static function renderSubject(string $template, array $context): string
	{
		$text = (string)(lang('reservation.notify_subject_' . $template) ?: '');
		if ($text === '' || $text === 'notify_subject_' . $template)
		{
			$text = '[{site}] {service}';
		}

		return self::fill($text, $context);
	}

	/**
	 * 본문 문구. 언어 파일의 notify_body_* 를 쓴다.
	 *
	 * @param string $template
	 * @param array $context
	 * @return string
	 */
	protected static function renderBody(string $template, array $context): string
	{
		$text = (string)(lang('reservation.notify_body_' . $template) ?: '');
		if ($text === '' || $text === 'notify_body_' . $template)
		{
			$text = '{name} 님, {when} {service} 예약 안내입니다. 예약번호 {code}';
		}

		return self::fill($text, $context);
	}

	/**
	 * 중괄호 자리를 값으로 바꾼다.
	 *
	 * @param string $text
	 * @param array $context
	 * @return string
	 */
	protected static function fill(string $text, array $context): string
	{
		foreach ($context as $key => $value)
		{
			$text = str_replace('{' . $key . '}', (string)$value, $text);
		}

		return trim($text);
	}

	/**
	 * 메일 발송.
	 *
	 * @param object $booking
	 * @param string $template
	 * @param string $subject
	 * @param string $body
	 * @return void
	 */
	protected static function sendMail(object $booking, string $template, string $subject, string $body): void
	{
		$to = trim((string)($booking->booker_email ?? ''));
		if ($to === '')
		{
			self::log($booking, self::CHANNEL_MAIL, $template, '', 'skipped', 'no email');
			return;
		}

		try
		{
			$mail = new \Rhymix\Framework\Mail();
			$mail->addTo($to);
			$mail->setSubject($subject);
			$mail->setBody(nl2br(escape($body)));
			$sent = $mail->send();

			self::log($booking, self::CHANNEL_MAIL, $template, $to, $sent ? 'sent' : 'failed', $sent ? '' : 'send returned false');
		}
		catch (\Throwable $e)
		{
			self::log($booking, self::CHANNEL_MAIL, $template, $to, 'failed', $e->getMessage());
		}
	}

	/**
	 * 새 예약을 관리자에게 알린다. 주소는 쉼표로 여럿 적을 수 있다.
	 *
	 * @param object $booking
	 * @param string $addresses
	 * @param string $subject
	 * @param string $body
	 * @return void
	 */
	protected static function sendAdminMail(object $booking, string $addresses, string $subject, string $body): void
	{
		foreach (array_filter(array_map('trim', explode(',', $addresses))) as $to)
		{
			if (!filter_var($to, FILTER_VALIDATE_EMAIL))
			{
				continue;
			}
			try
			{
				$mail = new \Rhymix\Framework\Mail();
				$mail->addTo($to);
				$mail->setSubject($subject);
				$mail->setBody(nl2br(escape($body)));
				$sent = $mail->send();
				self::log($booking, self::CHANNEL_MAIL, 'admin_new', $to, $sent ? 'sent' : 'failed', $sent ? '' : 'send returned false');
			}
			catch (\Throwable $e)
			{
				self::log($booking, self::CHANNEL_MAIL, 'admin_new', $to, 'failed', $e->getMessage());
			}
		}
	}

	/**
	 * 문자 발송. 코어 SMS 를 그대로 쓴다.
	 *
	 * @param object $booking
	 * @param string $template
	 * @param string $body
	 * @return void
	 */
	protected static function sendSMS(object $booking, string $template, string $body): void
	{
		$to = preg_replace('/[^0-9]/', '', (string)($booking->booker_phone ?? ''));
		if ($to === '')
		{
			self::log($booking, self::CHANNEL_SMS, $template, '', 'skipped', 'no phone');
			return;
		}

		try
		{
			$config = Config::getConfig();
			$sms = new \Rhymix\Framework\SMS();
			$from = trim((string)($config->sms_from ?? ''));
			if ($from !== '')
			{
				$sms->setFrom($from);
			}
			$sms->addTo($to);
			$sms->setBody($body);
			$sent = $sms->send();

			self::log($booking, self::CHANNEL_SMS, $template, $to, $sent ? 'sent' : 'failed', $sent ? '' : 'send returned false');
		}
		catch (\Throwable $e)
		{
			self::log($booking, self::CHANNEL_SMS, $template, $to, 'failed', $e->getMessage());
		}
	}

	/**
	 * 알림톡 발송. 꽂아 둔 발송기가 있을 때만 동작한다.
	 *
	 * @param object $booking
	 * @param string $template
	 * @param string $body
	 * @param array $context
	 * @return bool 발송을 맡았는가. false 면 문자로 흘려보낸다
	 */
	protected static function sendAlimtalk(object $booking, string $template, string $body, array $context): bool
	{
		$to = preg_replace('/[^0-9]/', '', (string)($booking->booker_phone ?? ''));
		if ($to === '' || !self::$_alimtalk_sender)
		{
			return false;
		}

		try
		{
			$context['template'] = $template;
			$sent = (bool)call_user_func(self::$_alimtalk_sender, $to, $body, $context);
			self::log($booking, self::CHANNEL_ALIMTALK, $template, $to, $sent ? 'sent' : 'failed', '');

			return $sent;
		}
		catch (\Throwable $e)
		{
			self::log($booking, self::CHANNEL_ALIMTALK, $template, $to, 'failed', $e->getMessage());
			return false;
		}
	}

	/**
	 * 발송 이력.
	 *
	 * @param object $booking
	 * @param string $channel
	 * @param string $template
	 * @param string $recipient
	 * @param string $status
	 * @param string $message
	 * @return void
	 */
	protected static function log(object $booking, string $channel, string $template, string $recipient, string $status, string $message): void
	{
		$args = new \stdClass;
		$args->log_srl = getNextSequence();
		$args->module_srl = (int)($booking->module_srl ?? 0);
		$args->booking_srl = (int)($booking->booking_srl ?? 0);
		$args->channel = $channel;
		$args->template = $template;
		$args->recipient = mb_substr($recipient, 0, 250);
		$args->status = $status;
		$args->message = mb_substr($message, 0, 250);
		$args->regdate = date('YmdHis');

		executeQuery('reservation.insertNotifyLog', $args);
	}
}
