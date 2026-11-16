<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

class StoreFollowController extends Controller
{
    public function toggle(Request $request, SellerProfile $sellerProfile)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        abort_unless($request->user()->isCustomer(), 403);

        $couponsReceived = 0;
        $askOffers = false;
        $follow = $sellerProfile->followers()->where('user_id', $request->user()->id)->first();
        if ($follow) {
            $follow->delete();
            $followed = false;
        } else {
            $sellerProfile->followers()->firstOrCreate(['user_id' => $request->user()->id]);
            $followed = true;
            // كوبونات المتابعين الفعّالة تصل للمتابع الجديد (مرة واحدة لكل عميل)
            $couponsReceived = app(\App\Coupons\FollowerIssuer::class)->issueForNewFollower($sellerProfile, $request->user());
            // أول متابعة: التطبيق يسأل العميل هل يريد إشعارات العروض (مرة واحدة)
            $askOffers = app(\App\Notifications\Push\PushPreferences::class)->askOffersOnFollow($request->user());
        }

        return response()->json([
            'followed' => $followed,
            'followers_count' => $sellerProfile->followers()->count(),
            'coupons_received' => $couponsReceived,
            'ask_offers_opt_in' => $askOffers,
        ]);
    }
}
