<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Social\SocialAccountResolver;
use App\Auth\Social\SocialLoginException;
use App\Auth\Social\SocialTokenVerifier;
use App\Http\Controllers\Controller;
use App\Services\AccountClosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/** دخول العميل في الموقع بـ Google أو Facebook (تحويل إلى المزوّد ثم العودة). */
class SocialAuthController extends Controller
{
    public function redirect(string $provider): SymfonyRedirect|RedirectResponse
    {
        if (! SocialTokenVerifier::isWebConfigured($provider)) {
            return redirect()->route('login')->withErrors(['identifier' => __('الدخول بهذه الطريقة غير مفعّل حالياً.')]);
        }

        $driver = Socialite::driver($provider)->redirectUrl(url("/auth/$provider/callback"));

        return $provider === 'facebook'
            ? $driver->scopes(['email'])->redirect()
            : $driver->redirect();
    }

    public function callback(Request $request, string $provider, SocialAccountResolver $resolver): RedirectResponse
    {
        if (! SocialTokenVerifier::isWebConfigured($provider) || $request->filled('error')) {
            return redirect()->route('login')->withErrors(['identifier' => __('ألغيت الدخول أو تعذّر إكماله.')]);
        }

        try {
            $remote = Socialite::driver($provider)->redirectUrl(url("/auth/$provider/callback"))->user();
            $raw = $remote->getRaw();
            $identity = $provider === 'google'
                ? SocialTokenVerifier::fromGoogle(['sub' => $remote->getId()] + $raw)
                : SocialTokenVerifier::fromFacebook(['id' => $remote->getId(), 'email' => $remote->getEmail()] + $raw);
        } catch (Throwable $e) {
            report($e); // السبب في السجل (مفتاح خاطئ، انقطاع اتصال...) والمستخدم يرى رسالة عامة

            return redirect()->route('login')->withErrors(['identifier' => __('تعذّر التحقق من حسابك. حاول مجدداً.')]);
        }

        try {
            $user = $resolver->resolve($identity);
        } catch (SocialLoginException $e) {
            return redirect()->route('login')->withErrors(['identifier' => $e->getMessage()]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        if ($notice = app(AccountClosureService::class)->onLogin($user)) {
            $request->session()->flash('success', $notice);
        }

        return redirect()->intended(route('customer.dashboard'));
    }
}
