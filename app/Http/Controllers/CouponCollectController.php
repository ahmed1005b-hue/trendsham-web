<?php

namespace App\Http\Controllers;

use App\Coupons\CouponCollectException;
use App\Coupons\CouponIssuer;
use App\Coupons\Models\CouponCampaign;
use Illuminate\Http\Request;

/** زر "احفظ" لكوبون المتجر (الموقع) */
class CouponCollectController extends Controller
{
    public function store(Request $request, CouponCampaign $campaign, CouponIssuer $issuer)
    {
        try {
            $issuer->collect($campaign, $request->user());
        } catch (CouponCollectException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'حُفظ الكوبون في محفظتك، ويُطبَّق تلقائياً في السلة.');
    }
}
