<?php

namespace App\Referrals;

/**
 * كود الدعوة مشتق من رقم الحساب + بصمة سرية قصيرة، فلا يحتاج عموداً في قاعدة البيانات
 * ولا يمكن تخمين كود حساب آخر بتغيير رقم فيه.
 * الشكل: رقم الحساب بالأساس 36 ثم 4 أحرف تحقق (مثل L7QX2).
 */
final class ReferralCode
{
    private const CHECK_LENGTH = 4;

    public static function for(int $userId): string
    {
        return strtoupper(base_convert((string) $userId, 10, 36)).self::check($userId);
    }

    /** رقم صاحب الكود، أو null إن كان الكود غير صحيح. */
    public static function decode(string $code): ?int
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        if (strlen($code) <= self::CHECK_LENGTH || strlen($code) > 16) {
            return null;
        }

        $userId = (int) base_convert(substr($code, 0, -self::CHECK_LENGTH), 36, 10);
        if ($userId < 1 || ! hash_equals(self::check($userId), substr($code, -self::CHECK_LENGTH))) {
            return null;
        }

        return $userId;
    }

    private static function check(int $userId): string
    {
        $hash = hash_hmac('sha256', 'referral:'.$userId, (string) config('app.key'));
        $value = strtoupper(base_convert(substr($hash, 0, 10), 16, 36));

        return str_pad(substr($value, 0, self::CHECK_LENGTH), self::CHECK_LENGTH, '0', STR_PAD_LEFT);
    }
}
