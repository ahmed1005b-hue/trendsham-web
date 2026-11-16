<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مستخدم بدور "بائع" بلا متجر (مثلاً حُذف متجره) لا تنهار صفحاته بخطأ 500:
 * نحوّله لصفحة طلب فتح المتجر. صفحات التقديم نفسها مستثناة.
 */
class EnsureSellerHasStore
{
    private const EXEMPT_WEB = ['seller.identity-verification.*'];

    private const EXEMPT_API = ['api/v1/seller/profile', 'api/v1/seller/application', 'api/v1/seller/identity-document'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->sellerProfile
            || $request->routeIs(...self::EXEMPT_WEB)
            || $request->is(...self::EXEMPT_API)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'لا يوجد متجر مرتبط بحسابك. قدّم طلب فتح متجر أولاً.', 'code' => 'no_store'], 403);
        }

        return redirect()->route('seller.identity-verification.create')
            ->with('error', 'لا يوجد متجر مرتبط بحسابك. قدّم طلب فتح متجر أولاً.');
    }
}
