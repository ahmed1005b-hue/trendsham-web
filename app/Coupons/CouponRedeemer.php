<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CouponRedemption;
use App\Coupons\Models\CustomerCoupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * تطبيق الكوبون عند الدفع (داخل معاملة الطلب) وعكسه عند الإلغاء.
 * ترتيب الأقفال: المنتجات ← الألوان ← كوبون العميل ← الحملة.
 */
class CouponRedeemer
{
    public function __construct(
        private readonly CouponSelector $selector,
        private readonly CouponEligibility $eligibility,
    ) {}

    /**
     * يحدد الكوبون ويقفله ويعيد فحصه على السلة الفعلية.
     *
     * @param  int|string|null  $choice  null = الأفضل تلقائياً، 'none' = بلا كوبون، رقم = كوبون اختاره العميل
     * @return array{0: ?CustomerCoupon, 1: ?EligibilityResult, 2: ?string} الكوبون، النتيجة، سبب إسقاطه إن سقط
     */
    public function resolve(User $user, int|string|null $choice, Collection $lines, int $shippingFee): array
    {
        if ($choice === 'none') {
            return [null, null, null];
        }

        $id = $choice === null
            ? $this->selector->best($user, $lines, $shippingFee)['chosen']?->id
            : (int) $choice;
        if (! $id) {
            return [null, null, null];
        }

        // كوبون في محفظة هذا العميل فقط
        $coupon = CustomerCoupon::whereKey($id)->where('user_id', $user->id)->lockForUpdate()->first();
        if (! $coupon) {
            return [null, null, 'الكوبون المختار غير موجود في محفظتك'];
        }
        $campaign = CouponCampaign::whereKey($coupon->campaign_id)->lockForUpdate()->first();
        $campaign->load('targets', 'seller');
        $coupon->setRelation('campaign', $campaign);

        $result = $this->eligibility->check($coupon, $lines, $shippingFee);
        if (! $result->ok || $result->saving() <= 0) {
            return [null, null, $result->reason ?? 'الكوبون لا ينطبق على سلتك'];
        }

        return [$coupon, $result, null];
    }

    /** بعد إنشاء الطلب: الكوبون مستخدم، وسجل يوضح كم خصم ومن دفع. */
    public function record(CustomerCoupon $coupon, EligibilityResult $result, Order $order): CouponRedemption
    {
        $campaign = $coupon->campaign;
        $coupon->update(['status' => 'used', 'used_at' => now()]);
        $campaign->increment('redeemed_count');

        return CouponRedemption::create([
            'customer_coupon_id' => $coupon->id,
            'campaign_id' => $campaign->id,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'funder' => $campaign->funder,
            'seller_profile_id' => $campaign->seller_profile_id,
            'discount_amount' => $result->discount,
            'shipping_discount' => $result->shippingDiscount,
            'status' => 'applied',
        ]);
    }

    /** إلغاء الطلب: الكوبون يعود للمحفظة إن كان صالحاً بعد. آمن للتكرار. */
    public function reverse(Order $order): void
    {
        $redemption = CouponRedemption::where('order_id', $order->id)->where('status', 'applied')->lockForUpdate()->first();
        if (! $redemption) {
            return;
        }

        $redemption->update(['status' => 'reversed']);

        $coupon = CustomerCoupon::whereKey($redemption->customer_coupon_id)->lockForUpdate()->first();
        if ($coupon && $coupon->status === 'used') {
            $coupon->update(['status' => $coupon->isExpired() ? 'expired' : 'available', 'used_at' => null]);
        }

        CouponCampaign::whereKey($redemption->campaign_id)->where('redeemed_count', '>', 0)->decrement('redeemed_count');
    }
}
