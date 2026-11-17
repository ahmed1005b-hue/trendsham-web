<?php

namespace App\Http\Controllers\Admin;

use App\Auth\TwoFactor\Totp;
use App\Auth\TwoFactor\TwoFactorService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** صفحة «أمان الحساب» للمدير: إعداد التحقق بخطوتين وتجديد رموز الاسترداد. */
class TwoFactorController extends Controller
{
    private const PENDING_SECRET = 'two_factor.setup_secret';

    public function show(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();
        $secret = null;

        if (! $user->hasTwoFactor()) {
            // السرّ يبقى في الجلسة حتى يُؤكَّد برمز صحيح، فلا يُفعَّل شيء بالخطأ
            $secret = $request->session()->get(self::PENDING_SECRET) ?: Totp::generateSecret();
            $request->session()->put(self::PENDING_SECRET, $secret);
        }

        return view('admin.security.two-factor', [
            'user' => $user,
            'secret' => $secret,
            'qr' => $secret ? $twoFactor->qrSvg($user, $secret) : null,
            'recoveryCodes' => session('recovery_codes'),
            'remaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function confirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get(self::PENDING_SECRET);

        $codes = $secret ? $twoFactor->confirm($request->user(), $secret, $request->input('code')) : null;
        if ($codes === null) {
            return back()->withErrors(['code' => __('الرمز غير صحيح. تأكد من الوقت في هاتفك وجرّب الرمز الجديد.')]);
        }

        $request->session()->forget(self::PENDING_SECRET);
        // جلسات الهاتف القديمة تُلغى: تدخل من جديد بالرمز
        $request->user()->tokens()->delete();

        return redirect()->route('admin.security.two-factor')
            ->with('recovery_codes', $codes)
            ->with('success', __('تم تفعيل التحقق بخطوتين. احفظ رموز الاسترداد في مكان آمن.'));
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        if (! $twoFactor->verify($request->user(), $request->input('code'))) {
            return back()->withErrors(['code' => __('الرمز غير صحيح.')]);
        }

        return redirect()->route('admin.security.two-factor')
            ->with('recovery_codes', $twoFactor->regenerateRecoveryCodes($request->user()))
            ->with('success', __('أُنشئت رموز استرداد جديدة، والقديمة لم تعد تعمل.'));
    }
}
