<?php

namespace App\Support;

/**
 * توحيد أرقام الهاتف إلى صيغة دولية بدون "+" (مثل 905301234567 أو 963933123456).
 *
 * الصيغ المحلية المعروفة تُكتشف من شكلها، فيعمل الموقع مع تركيا وسوريا معاً:
 *   تركيا:  05XX XXX XX XX (11 رقماً)  أو 5XX XXX XX XX (10 أرقام)  → 90...
 *   سوريا:  09XX XXX XXX  (10 أرقام)   أو 9XX XXX XXX  (9 أرقام)    → 963...
 * وأي رقم محلي آخر يبدأ بـ 0 يأخذ رمز الدولة الافتراضي (SMS_DEFAULT_COUNTRY_CODE).
 */
class Phone
{
    public static function normalize(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $d = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (preg_match('/^05\d{9}$/', $d)) {
            $d = '90'.substr($d, 1);
        } elseif (preg_match('/^5\d{9}$/', $d)) {
            $d = '90'.$d;
        } elseif (preg_match('/^09\d{8}$/', $d)) {
            $d = '963'.substr($d, 1);
        } elseif (preg_match('/^9\d{8}$/', $d)) {
            $d = '963'.$d;
        } elseif (str_starts_with($d, '0')) {
            $d = config('sms.default_country_code').substr($d, 1);
        }

        return (strlen($d) >= 10 && strlen($d) <= 15) ? $d : null;
    }
}
