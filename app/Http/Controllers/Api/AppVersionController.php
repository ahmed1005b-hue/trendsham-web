<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * أحدث إصدار للتطبيق وأقدم إصدار مسموح: التطبيق يقارن رقمه بهما
 * فيعرض «يوجد تحديث جديد» أو يطلب التحديث إجبارياً.
 */
class AppVersionController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $android = config('mobile.android');

        return response()->json([
            'latest_version' => (string) $android['latest_version'],
            'min_version' => (string) $android['min_version'],
            'store_url' => $android['store_url'] ?: null,
        ]);
    }
}
