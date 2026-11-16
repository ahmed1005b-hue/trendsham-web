<?php

namespace App\Http\Controllers\Customer;

use App\Coupons\CouponIssuer;
use App\Coupons\Models\CustomerCoupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** صفحة "كوبوناتي": المتاحة، والمستخدمة، والمنتهية. */
class CouponWalletController extends Controller
{
    public function index(Request $request, CouponIssuer $issuer)
    {
        $user = $request->user();
        $issuer->syncFor($user);

        $coupons = CustomerCoupon::where('user_id', $user->id)
            ->with(['campaign.seller'])
            ->latest()
            ->get();

        // كوبون متاح انتهت صلاحيته يُنقل للمنتهية
        $coupons->filter(fn ($c) => $c->status === 'available' && $c->isExpired())
            ->each(fn ($c) => $c->update(['status' => 'expired']));

        $tab = in_array($request->query('tab'), ['used', 'expired'], true) ? $request->query('tab') : 'available';

        return view('customer.coupons.index', [
            'tab' => $tab,
            'coupons' => $coupons->filter(fn ($c) => match ($tab) {
                'available' => $c->status === 'available' && $c->campaign?->isRunning(),
                'used' => $c->status === 'used',
                'expired' => in_array($c->status, ['expired', 'revoked'], true) || ($c->status === 'available' && ! $c->campaign?->isRunning()),
            })->values(),
            'counts' => [
                'available' => $coupons->filter(fn ($c) => $c->status === 'available' && $c->campaign?->isRunning())->count(),
                'used' => $coupons->where('status', 'used')->count(),
            ],
        ]);
    }
}
