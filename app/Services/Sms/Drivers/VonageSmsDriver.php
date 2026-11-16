<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Log;
use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

class VonageSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $key,
        private readonly string $secret,
        private readonly string $from,
    ) {
    }

    public function send(string $phone, string $message): bool
    {
        $client = new Client(new Basic($this->key, $this->secret));
        $sms = new SMS($this->from, ltrim($phone, '+'), $message);

        try {
            $response = $client->sms()->send($sms);
            $messageResponse = $response->current();

            if ($messageResponse->getStatus() !== 0) {
                Log::error('Vonage SMS failed', ['phone' => $phone, 'error' => $messageResponse->getErrorText()]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Vonage SMS exception', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }
    }
}