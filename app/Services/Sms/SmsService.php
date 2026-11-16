<?php

namespace App\Services\Sms;

use App\Support\MessageTranslator;
use App\Services\Sms\Drivers\ClickSendSmsDriver;
use App\Services\Sms\Drivers\LogSmsDriver;
use App\Services\Sms\Drivers\TwilioSmsDriver;
use App\Services\Sms\Drivers\VonageSmsDriver;
use InvalidArgumentException;

class SmsService
{
    /**
     * الرسائل تُكتب بالعربية وتُترجم هنا: للغة المستلم إن عُرفت، وإلا لغة الطلب الحالي
     * (رموز التحقق يطلبها المستخدم بنفسه من التطبيق أو الموقع بلغته).
     */
    public function send(string $phone, string $message, ?string $locale = null): bool
    {
        return $this->driver()->send($phone, MessageTranslator::translate($message, $locale) ?? $message);
    }

    private function driver(): SmsDriver
    {
        return match (config('sms.driver')) {
            'log' => new LogSmsDriver(),
            'twilio', 'twilio_whatsapp' => new TwilioSmsDriver(
                (string) (config('sms.drivers.twilio.sid') ?: config('sms.drivers.twilio.account_sid')),
                (string) config('sms.drivers.twilio.token'),
                (string) config('sms.drivers.twilio.from'),
                config('sms.driver') === 'twilio_whatsapp',
            ),
            'vonage' => new VonageSmsDriver(
                config('sms.drivers.vonage.key'),
                config('sms.drivers.vonage.secret'),
                config('sms.drivers.vonage.from'),
            ),
            'clicksend' => new ClickSendSmsDriver(
                (string) config('sms.drivers.clicksend.username'),
                (string) config('sms.drivers.clicksend.api_key'),
                (string) config('sms.drivers.clicksend.from'),
            ),
            default => throw new InvalidArgumentException('SMS driver غير مدعوم: '.config('sms.driver')),
        };
    }
}