<?php

namespace App\Communications;

use App\Communications\Contracts\OtpChannel;
use Illuminate\Support\Facades\Log;

class LogOtpChannel implements OtpChannel
{
    public function send(string $phone, string $code): bool
    {
        if (! app()->environment('local', 'testing')) {
            Log::error('SMS_DRIVER=log is not allowed outside local/testing');
            return false;
        }

        Log::info("OTP for {$phone}: {$code}");
        return true;
    }
}
