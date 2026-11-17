<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Referrals\ReferralException;
use App\Referrals\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** «دعوة صديق» للتطبيق. المبالغ بالليرة. */
class ReferralController extends Controller
{
    public function show(Request $request, ReferralService $referrals): JsonResponse
    {
        $summary = $referrals->summary($request->user());
        $summary['min_order'] = $summary['min_order'] / 100;

        return response()->json($summary);
    }

    public function apply(Request $request, ReferralService $referrals): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $referrals->apply($request->user(), $data['code']);
        } catch (ReferralException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'تم تسجيل كود الدعوة. ستحصل على النقاط بعد أن يصلك أول طلب.'], 201);
    }
}
