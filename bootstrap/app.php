<?php

use App\Http\Middleware\AuthenticateWebSession;
use App\Http\Middleware\EnsureSellerHasStore;
use App\Http\Middleware\EnsureTwoFactorSetup;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceUtf8;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'seller.store' => EnsureSellerHasStore::class,
            'admin.2fa' => EnsureTwoFactorSetup::class,
            'auth.session' => AuthenticateWebSession::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        // تغيير كلمة السر يُخرج جلسات الموقع الأخرى (يشترطه Auth::logoutOtherDevices)
        $middleware->authenticateSessions();
        $middleware->appendToGroup('web', [ForceUtf8::class, SetLocale::class, 'throttle:writes']);
        $middleware->appendToGroup('api', [ForceUtf8::class, SetApiLocale::class, 'throttle:api', 'throttle:writes']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // تجاوز حد المحاولات: رسالة واضحة بلغة المستخدم بدل «Too Many Attempts.»
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $message = __('محاولات كثيرة جداً. حاول مجدداً بعد :seconds ثانية.', ['seconds' => $seconds]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $message, 'retry_after' => $seconds], 429, $e->getHeaders());
            }
            if ($request->isMethodSafe()) {
                return null; // صفحات العرض تبقى 429 عادية؛ الرجوع للنموذج فقط عند إرسال بيانات
            }

            return back()
                ->withInput($request->except('password', 'password_confirmation', 'current_password', 'code', 'otp'))
                ->withErrors(['throttle' => $message])
                ->with('error', $message);
        });
    })->create();
