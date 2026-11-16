<?php

namespace App\Services;

use App\Communications\Contracts\OtpChannel;
use App\Communications\LogOtpChannel;
use App\Communications\TwilioWhatsAppChannel;
use App\Communications\WhatsAppCloudChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    public function channel(): OtpChannel
    {
        return match (config('sms.driver')) {
            'twilio_whatsapp' => new TwilioWhatsAppChannel(),
            'whatsapp_cloud'  => new WhatsAppCloudChannel(),
            default           => new LogOtpChannel(),
        };
    }

    public function cooldown(string $phone): bool
    {
        return Cache::add("otp:cd:{$phone}", 1, config('sms.otp.cooldown', 60));
    }

    public function issue(string $phone): bool
    {
        $code = (string) random_int(100000, 999999);

        Cache::put("otp:code:{$phone}", Hash::make($code), config('sms.otp.ttl', 300));
        Cache::forget("otp:tries:{$phone}");

        if (! $this->channel()->send($phone, $code)) {
            Cache::forget("otp:code:{$phone}");
            return false;
        }

        return true;
    }

    public function verify(string $phone, string $code): string
    {
        $hash = Cache::get("otp:code:{$phone}");
        if (! $hash) {
            return 'invalid';
        }

        $triesKey = "otp:tries:{$phone}";
        Cache::add($triesKey, 0, config('sms.otp.ttl', 300));

        if ((int) Cache::increment($triesKey) >= config('sms.otp.max_tries', 5)) {
            Cache::forget("otp:code:{$phone}");
            return 'locked';
        }

        if (! Hash::check($code, $hash)) {
            return 'invalid';
        }

        Cache::forget("otp:code:{$phone}");
        Cache::forget($triesKey);
        return 'ok';
    }

    public function makeResetToken(int $userId): string
    {
        $token = Str::random(64);
        Cache::put('pwd:reset:' . hash('sha256', $token), $userId, 600);
        return $token;
    }

    public function consumeResetToken(string $token): ?int
    {
        return Cache::pull('pwd:reset:' . hash('sha256', $token));
    }
}