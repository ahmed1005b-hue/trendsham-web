<?php

namespace App\Communications;

use App\Communications\Contracts\OtpChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppCloudChannel implements OtpChannel
{
    public function send(string $phone, string $code): bool
    {
        $cfg = config('services.whatsapp');

        if (empty($cfg['token']) || empty($cfg['phone_id'])) {
            Log::warning('WhatsApp Cloud: missing configuration');
            return false;
        }

        try {
            $res = Http::withToken($cfg['token'])
                ->timeout(15)
                ->post("https://graph.facebook.com/{$cfg['version']}/{$cfg['phone_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'   => $phone,
                    'type' => 'template',
                    'template' => [
                        'name'     => $cfg['template'],
                        'language' => ['code' => $cfg['lang']],
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0',
                             'parameters' => [['type' => 'text', 'text' => $code]]],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp Cloud exception', ['error' => $e->getMessage()]);
            return false;
        }

        if (! $res->successful()) {
            Log::warning('WhatsApp Cloud failed', [
                'status' => $res->status(),
                'error'  => $res->json('error.message'),
            ]);
            return false;
        }

        return true;
    }
}
