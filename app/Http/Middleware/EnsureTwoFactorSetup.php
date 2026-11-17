<?php

namespace App\Http\Middleware;

use App\Auth\TwoFactor\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** المدير لا يصل إلى لوحته قبل إعداد التحقق بخطوتين (صفحة الإعداد نفسها مستثناة). */
class EnsureTwoFactorSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! TwoFactorService::requiredFor($user) || $user->hasTwoFactor()
            || $request->routeIs('admin.security.*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => __('فعّل التحقق بخطوتين من لوحة المدير في الموقع أولاً (أمان الحساب).'),
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        return redirect()->route('admin.security.two-factor')
            ->with('error', __('لحماية لوحة الإدارة، فعّل التحقق بخطوتين أولاً.'));
    }
}
