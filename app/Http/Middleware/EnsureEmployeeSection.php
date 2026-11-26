<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * قسم من لوحة الموظف (employee.section:orders مثلاً): يدخله الموظف الذي سمح له المدير بهذا القسم فقط.
 * يأتي بعد role:employee، فالدور والاعتماد مفحوصان قبله.
 */
class EnsureEmployeeSection
{
    public function handle(Request $request, Closure $next, string $section): Response
    {
        if (! $request->user()?->canWorkOn($section)) {
            abort(403, __('هذا القسم غير متاح لحسابك. اطلب من المدير إضافته.'));
        }

        return $next($request);
    }
}
