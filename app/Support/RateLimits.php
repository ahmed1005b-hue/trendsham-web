<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * حدود عدد المحاولات (Rate limiting) لحماية الدخول والتسجيل ورموز التحقق وكل النماذج.
 *
 * - login:  محاولات الدخول — 5 بالدقيقة لكل حساب+جهاز، و20 بالدقيقة لكل عنوان IP.
 * - otp:    إرسال رموز التحقق أو روابط الاستعادة — 3 بالدقيقة لكل حساب، و10 بالساعة لكل IP.
 * - verify: إدخال الرموز وتغيير كلمة السر — 5 بالدقيقة لكل حساب+جهاز، و20 بالدقيقة لكل IP.
 * - writes: كل نموذج يرسل بيانات (POST/PUT/PATCH/DELETE) — 60 بالدقيقة لكل مستخدم أو IP.
 * - api:    كل طلبات التطبيق — 240 بالدقيقة لكل مستخدم أو IP.
 *
 * حدود أضيق لمسارات بعينها (مثل throttle:10,1) تبقى فوق هذه الحدود العامة.
 */
class RateLimits
{
    /** حقول قد يكتب فيها المستخدم بريده أو هاتفه، بالترتيب. */
    private const IDENTIFIER_FIELDS = ['identifier', 'email', 'phone', 'login'];

    public static function register(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.self::identifier($request).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(3)->by('otp:'.self::identifier($request, fallbackToIp: true)),
            Limit::perHour(10)->by('otp-ip:'.$request->ip()),
        ]);

        RateLimiter::for('verify', fn (Request $request) => [
            Limit::perMinute(5)->by('verify:'.self::identifier($request).'|'.$request->ip()),
            Limit::perMinute(20)->by('verify-ip:'.$request->ip()),
        ]);

        RateLimiter::for('writes', function (Request $request) {
            if ($request->isMethodSafe() || $request->routeIs('payments.webhook', 'payouts.webhook')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by('writes:'.(self::userId($request) ?? $request->ip()));
        });

        RateLimiter::for('api', function (Request $request) {
            if ($request->routeIs('payments.webhook', 'payouts.webhook')) {
                return Limit::none(); // إشعارات البنك لها حدّها الخاص على مسارها
            }

            // العميل المسجّل له عدّاده الخاص حتى في الصفحات العامة (منتجات، أقسام):
            // شبكات الجوال تجمع آلاف العملاء خلف عنوان IP واحد.
            if ($userId = self::userId($request)) {
                return Limit::perMinute(240)->by('api:'.$userId);
            }

            return Limit::perMinute(self::GUEST_API_PER_MINUTE)->by('api-ip:'.$request->ip());
        });
    }

    /** الزوار بلا حساب يتشاركون عنوان IP غالباً، فحدّهم أوسع من حد المستخدم الواحد. */
    public const GUEST_API_PER_MINUTE = 600;

    /** المستخدم من الجلسة أو من رمز التطبيق، حتى قبل أن يمرّ الطلب على auth:sanctum. */
    private static function userId(Request $request): ?int
    {
        $user = $request->user() ?? ($request->bearerToken() ? $request->user('sanctum') : null);

        return $user?->getAuthIdentifier();
    }

    /** البريد أو الهاتف المُدخل (بأحرف صغيرة)، أو المستخدم المسجّل، أو IP. */
    private static function identifier(Request $request, bool $fallbackToIp = false): string
    {
        foreach (self::IDENTIFIER_FIELDS as $field) {
            $value = $request->input($field);
            if (is_string($value) && trim($value) !== '') {
                return mb_strtolower(trim($value));
            }
        }

        if ($request->user()) {
            return 'user:'.$request->user()->id;
        }

        return $fallbackToIp ? 'ip:'.$request->ip() : 'anonymous';
    }
}
