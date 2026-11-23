<?php

namespace App\Http\Controllers\Api\Admin;

use App\Auth\TwoFactor\Totp;
use App\Auth\TwoFactor\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * «أمان الحساب» للمدير في التطبيق (نفس صفحة الموقع): إعداد التحقق بخطوتين وتجديد رموز الاسترداد.
 * هذه المسارات تعمل قبل إعداد التحقق، لأن باقي لوحة المدير مقفلة حتى يُعدّ.
 */
class TwoFactorController extends Controller
{
    /** السرّ المؤقت يبقى في الخادم حتى يُؤكَّد برمز صحيح، فلا يُفعَّل شيء بالخطأ. */
    private function pendingKey(User $user): string
    {
        return 'two_factor.setup_secret.'.$user->id;
    }

    public function show(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $user = $request->user();
        $data = [
            'required' => TwoFactorService::requiredFor($user),
            'enabled' => $user->hasTwoFactor(),
            'recovery_remaining' => count($user->two_factor_recovery_codes ?? []),
        ];

        if (! $user->hasTwoFactor()) {
            $secret = Cache::get($this->pendingKey($user)) ?: Totp::generateSecret();
            Cache::put($this->pendingKey($user), $secret, now()->addMinutes(30));
            $data += [
                'secret' => $secret,
                'otpauth_url' => Totp::uri($secret, $user->email ?: (string) $user->phone, config('app.name', 'TrendSham')),
                'qr_svg' => $twoFactor->qrSvg($user, $secret),
            ];
        }

        return response()->json($data);
    }

    public function confirm(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return response()->json(['message' => __('التحقق بخطوتين مفعّل بالفعل.')], 409);
        }

        $secret = Cache::get($this->pendingKey($user));
        $codes = $secret ? $twoFactor->confirm($user, $secret, $request->input('code')) : null;
        if ($codes === null) {
            $message = __('الرمز غير صحيح. تأكد من الوقت في هاتفك وجرّب الرمز الجديد.');

            return response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 422);
        }

        Cache::forget($this->pendingKey($user));
        // جلسات الهاتف الأخرى تُلغى: تدخل من جديد بالرمز. هذه الجلسة أثبتت الرمز للتو فتبقى
        $current = $user->currentAccessToken();
        $keep = $current instanceof PersonalAccessToken ? $current->getKey() : null;
        $user->tokens()->when($keep, fn ($q, $id) => $q->whereKeyNot($id))->delete();

        return response()->json([
            'message' => __('تم تفعيل التحقق بخطوتين. احفظ رموز الاسترداد في مكان آمن.'),
            'recovery_codes' => $codes,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        if (! $twoFactor->verify($request->user(), $request->input('code'))) {
            $message = __('الرمز غير صحيح.');

            return response()->json(['message' => $message, 'errors' => ['code' => [$message]]], 422);
        }

        return response()->json([
            'message' => __('أُنشئت رموز استرداد جديدة، والقديمة لم تعد تعمل.'),
            'recovery_codes' => $twoFactor->regenerateRecoveryCodes($request->user()),
        ]);
    }
}
