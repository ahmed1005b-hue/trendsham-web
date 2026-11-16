<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickSendSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $username,
        private readonly string $apiKey,
        private readonly string $from = '',
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::withBasicAuth($this->username, $this->apiKey)
                ->post('https://rest.clicksend.com/v3/sms/send', [
                    'messages' => [
                        [
                            'to' => $phone,
                            'body' => $message,
                            'from' => $this->from,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('ClickSend SMS failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('ClickSend SMS exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}