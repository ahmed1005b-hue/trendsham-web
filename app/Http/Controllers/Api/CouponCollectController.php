<?php

namespace App\Http\Controllers\Api;

use App\Coupons\CouponCollectException;
use App\Coupons\CouponIssuer;
use App\Coupons\Models\CouponCampaign;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** POST /api/v1/coupons/{campaign}/collect */
class CouponCollectController extends Controller
{
    public function store(Request $request, CouponCampaign $campaign, CouponIssuer $issuer)
    {
        try {
            $coupon = $issuer->collect($campaign, $request->user());
        } catch (CouponCollectException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->reason], $e->status());
        }

        return response()->json([
            'coupon_id' => $coupon->id,
            'expires_at' => ($coupon->expires_at ?? $campaign->ends_at)?->toDateString(),
            'message' => 'حُفظ الكوبون في محفظتك',
        ], 201);
    }
}
