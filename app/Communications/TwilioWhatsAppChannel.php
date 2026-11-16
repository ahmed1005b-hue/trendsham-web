<?php

namespace App\Communications;

use App\Communications\Contracts\OtpChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioWhatsAppChannel implements OtpChannel
{
    public function send(string $phone, string $code): bool
    {
        $cfg = config('services.twilio');

        if (empty($cfg['sid']) || empty($cfg['token']) || empty($cfg['from'])) {
            Log::warning('Twilio WhatsApp: missing configuration');
            return false;
        }

        $body = str_replace(':code', $code, config('sms.message'));

        try {
            $res = Http::asForm()
                ->withBasicAuth($cfg['sid'], $cfg['token'])
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Messages.json", [
                    'From' => $cfg['from'],
                    'To'   => 'whatsapp:+' . $phone,
                    'Body' => $body,
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
