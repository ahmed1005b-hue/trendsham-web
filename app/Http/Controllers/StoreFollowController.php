<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use App\Notifications\Push\PushPreferences;
use Illuminate\Http\Request;

class StoreFollowController extends Controller
{
    public function toggle(Request $request, SellerProfile $sellerProfile)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        abort_unless($request->user()->isCustomer(), 403);

        $couponsReceived = 0;
        $follow = $sellerProfile->followers()->where('user_id', $request->user()->id)->first();
        if ($follow) {
            $follow->delete();
            $followed = false;
        } else {
            $sellerProfile->followers()->firstOrCreate(['user_id' => $request->user()->id]);
            $followed = true;
            // كوبونات المتابعين الفعّالة تصل للمتابع الجديد (مرة واحدة لكل عميل)
            $couponsReceived = app(\App\Coupons\FollowerIssuer::class)->issueForNewFollower($sellerProfile, $request->user());
        }

        $response = back()->with('success', $followed
            ? 'تمت متابعة المتجر.'.($couponsReceived ? ' وصلك كوبون من المتجر في محفظتك.' : '')
            : 'تم إلغاء متابعة المتجر.');

        // أول متابعة: سؤال واحد عن إشعارات العروض (يظهر في الصفحة بزرّي نعم/لا)
        if ($followed && app(PushPreferences::class)->askOffersOnFollow($request->user())) {
            $response->with('ask_offers_opt_in', true);
        }

        return $response;
    }

    /** جواب سؤال العروض من الموقع. */
    public function offers(Request $request)
    {
        $data = $request->validate(['offers' => ['required', 'boolean']]);
        app(PushPreferences::class)->update($request->user(), ['offers' => (bool) $data['offers']]);

        return back()->with('success', $data['offers']
            ? 'سنرسل لك عروض ومنتجات المتاجر التي تتابعها (حتى 3 يومياً، ولا شيء ليلاً).'
            : 'لن نرسل لك إشعارات العروض. يمكنك تغيير ذلك من إعدادات الإشعارات في التطبيق.');
    }
}
