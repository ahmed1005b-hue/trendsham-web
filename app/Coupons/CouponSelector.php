<?php

namespace App\Coupons;

use App\Coupons\Models\CustomerCoupon;
use App\Models\User;
use Illuminate\Support\Collection;

/** يختار أفضل كوبون في محفظة العميل لهذه السلة، ويعرض الباقي مع سبب عدم صلاحيته. */
class CouponSelector
{
    public function __construct(private readonly CouponEligibility $eligibility) {}

    /**
     * @return array{chosen: ?CustomerCoupon, result: ?EligibilityResult, options: list<array{coupon: CustomerCoupon, result: EligibilityResult}>}
     */
    public function best(User $user, Collection $lines, int $shippingFee): array
    {
        $options = CustomerCoupon::where('user_id', $user->id)
            ->where('status', 'available')
            ->with(['campaign.targets', 'campaign.seller'])
            ->get()
            ->map(fn (CustomerCoupon $c) => ['coupon' => $c, 'result' => $this->eligibility->check($c, $lines, $shippingFee)])
            // الصالح أولاً بالأكبر توفيراً، ثم غير الصالح
            ->sortBy([fn ($a, $b) => $b['result']->ok <=> $a['result']->ok, fn ($a, $b) => $b['result']->saving() <=> $a['result']->saving()])
            ->values();

        $best = $options->first(fn ($o) => $o['result']->ok && $o['result']->saving() > 0);

        return ['chosen' => $best['coupon'] ?? null, 'result' => $best['result'] ?? null, 'options' => $options->all()];
    }

    /** نتيجة كوبون محدد اختاره العميل (يجب أن يكون في محفظته). */
    public function evaluate(User $user, int $customerCouponId, Collection $lines, int $shippingFee): ?array
    {
        $coupon = CustomerCoupon::where('user_id', $user->id)->with(['campaign.targets', 'campaign.seller'])->find($customerCouponId);

        return $coupon ? ['coupon' => $coupon, 'result' => $this->eligibility->check($coupon, $lines, $shippingFee)] : null;
    }
}
