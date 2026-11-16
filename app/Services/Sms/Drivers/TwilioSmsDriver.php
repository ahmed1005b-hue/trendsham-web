<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * إرسال عبر Twilio: رسالة SMS عادية، أو واتساب إذا كان المُرسِل يبدأ بـ "whatsapp:".
 */
class TwilioSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $sid,
        private readonly string $token,
        private readonly string $from,
        private readonly bool $whatsapp = false,
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        if ($this->sid === '' || $this->token === '' || $this->from === '') {
            Log::error('Twilio: بيانات الحساب ناقصة (TWILIO_SID / TWILIO_TOKEN / TWILIO_FROM).');

            return false;
        }

        $to = self::toE164($phone);
        if ($to === null) {
            Log::warning('Twilio: رقم غير صالح', ['phone' => $phone]);

            return false;
        }

        $from = $this->from;
        $useWhatsapp = $this->whatsapp || str_starts_with($from, 'whatsapp:');
        if ($useWhatsapp) {
            $to = 'whatsapp:'.$to;
            if (! str_starts_with($from, 'whatsapp:')) {
                $from = 'whatsapp:'.$from;
            }
        }

        try {
            (new Client($this->sid, $this->token))->messages->create($to, [
                'from' => $from,
                'body' => $message,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Twilio: فشل الإرسال', ['to' => $to, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Twilio يتطلب الصيغة الدولية E.164: 0933123456 → +963933123456
     */
    public static function toE164(string $phone): ?string
    {
        $digits = Phone::normalize($phone);

        return $digits ? '+'.$digits : null;
    }
}
