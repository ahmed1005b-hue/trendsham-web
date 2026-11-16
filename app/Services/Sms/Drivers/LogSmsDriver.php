<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\Log;

class LogSmsDriver implements SmsDriver
{
    public function send(string $phone, string $message): bool
    {
        Log::info("[SMS -> {$phone}] {$message}");

        return true;
    }
}
