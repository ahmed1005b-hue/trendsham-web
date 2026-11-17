<?php

namespace App\Referrals;

use App\Models\User;
use Illuminate\Support\Facades\Cookie;

/**
 * رابط الدعوة في الموقع يحفظ الكود في كوكي، ويُطبَّق تلقائياً عند إنشاء الحساب
 * (تسجيل بالهاتف أو Google/Facebook)، فلا يحتاج الصديق إدخاله يدوياً.
 */
final class ReferralCookie
{
    public const NAME = 'ts_ref';

    public const MINUTES = 60 * 24 * 30;

    public static function remember(string $code): void
    {
        Cookie::queue(self::NAME, $code, self::MINUTES);
    }

    public static function applyTo(User $user): void
    {
        $code = request()->cookie(self::NAME);
        if ($user->role !== 'customer' || ! is_string($code) || $code === '') {
            return;
        }

        try {
            app(ReferralService::class)->apply($user, $code);
        } catch (ReferralException) {
            // كود غير صالح لا يمنع إنشاء الحساب
        }
        Cookie::queue(Cookie::forget(self::NAME));
    }
}
