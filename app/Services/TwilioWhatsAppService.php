<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppService
{
    public function sendOtp(string $phone, string $code): bool
    {
        $cfg = config('services.twilio');

        if (empty($cfg['sid']) || empty($cfg['token']) || empty($cfg['from'])) {
            Log::warning('Twilio WhatsApp: missing configuration');
            return false;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        $to = 'whatsapp:+' . $digits;

        try {
            $res = Http::asForm()
                ->withBasicAuth($cfg['sid'], $cfg['token'])
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Messages.json", [
                    'From' => $cfg['from'],
                    'To'   => $to,
                    'Body' => "رمز التحقق الخاص بك في تريندشام: {$code}",
                ]);
        } catch (\Throwable $e) {
            Log::warning('Twilio WhatsApp exception', ['error' => $e->getMessage()]);
            return false;
        }

        if (! $res->successful()) {
            Log::warning('Twilio WhatsApp failed', [
                'status' => $res->status(),
                'error'  => $res->json('message'),
            ]);
            return false;
        }

        return true;
    }
}