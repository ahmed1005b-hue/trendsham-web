<?php

namespace App\Http\Controllers\Api;

use App\Coupons\CouponIssuer;
use App\Coupons\CouponPresenter;
use App\Coupons\Models\CustomerCoupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** محفظة كوبونات العميل في التطبيق. */
class CouponWalletController extends Controller
{
    public function index(Request $request, CouponIssuer $issuer)
    {
        $user = $request->user();
        $issuer->syncFor($user);

        $coupons = CustomerCoupon::where('user_id', $user->id)->with('campaign.seller')->latest()->get();
        $coupons->filter(fn ($c) => $c->status === 'available' && $c->isExpired())
            ->each(fn ($c) => $c->update(['status' => 'expired']));

        $tab = in_array($request->query('tab'), ['used', 'expired'], true) ? $request->query('tab') : 'available';
        $filtered = $coupons->filter(fn ($c) => match ($tab) {
            'available' => $c->status === 'available' && $c->campaign?->isRunning(),
            'used' => $c->status === 'used',
            'expired' => in_array($c->status, ['expired', 'revoked'], true) || ($c->status === 'available' && ! $c->campaign?->isRunning()),
        });

        return response()->json(['data' => $filtered->map(fn ($c) => CouponPresenter::coupon($c))->values()]);
    }
}
