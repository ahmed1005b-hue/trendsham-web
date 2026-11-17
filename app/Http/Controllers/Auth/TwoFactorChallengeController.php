<?php

namespace App\Http\Controllers\Auth;

use App\Auth\TwoFactor\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountClosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * الخطوة الثانية في دخول الموقع: كلمة السر صحيحة، والآن رمز تطبيق المصادقة.
 * لا تُفتح الجلسة إلا بعد الرمز؛ حتى ذلك الحين نحفظ «من ينتظر» لدقائق في الجلسة.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor.login';

    private const MAX_ATTEMPTS = 5;

    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->pending($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $pending = $this->pending($request);
        $user = $pending ? User::find($pending['id']) : null;

        if (! $user || ! $user->hasTwoFactor() || $user->status !== 'active') {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('login')->withErrors(['identifier' => __('انتهت المهلة، سجّل دخولك من جديد.')]);
        }

        $key = 'two-factor-web:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('login')->withErrors(['identifier' => __('محاولات كثيرة جداً. سجّل دخولك من جديد بعد قليل.')]);
        }

        if (! $twoFactor->verify($user, $request->input('code'))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => __('الرمز غير صحيح.')]);
        }

        RateLimiter::clear($key);
        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();

        if ($notice = app(AccountClosureService::class)->onLogin($user)) {
            $request->session()->flash('success', $notice);
        }

        return redirect()->route(match ($user->role) {
            'admin' => 'admin.dashboard',
            'seller' => 'seller.dashboard',
            'employee' => 'employee.dashboard',
            default => 'customer.dashboard',
        });
    }

    /** @return array{id: int, remember: bool, expires: int}|null */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && ($pending['expires'] ?? 0) > time() ? $pending : null;
    }
}
