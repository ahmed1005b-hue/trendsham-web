<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CouponRedemption;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/** أرقام كوبون البائع: كم جُمع، كم استُخدم، كم تحمّل من خصم، وكم باع في الطلبات التي استخدمته. */
class SellerCouponStats
{
    /** @return array{issued:int, redeemed:int, discount_total:int, sales_total:int} بالوحدة الصغرى */
    public function for(CouponCampaign $campaign): array
    {
        $applied = CouponRedemption::where('campaign_id', $campaign->id)->where('status', 'applied');
        $totals = (clone $applied)->selectRaw('COUNT(*) as n, COALESCE(SUM(discount_amount + shipping_discount), 0) as d')->first();

        $sales = OrderItem::whereIn('order_id', (clone $applied)->select('order_id'))
            ->where('vendor_id', $campaign->seller_profile_id)
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->sum('subtotal');

        return [
            'issued' => (int) $campaign->issued_count,
            'redeemed' => (int) $totals->n,
            'discount_total' => (int) $totals->d,
            'sales_total' => (int) $sales,
        ];
    }

    /** آخر الطلبات التي استُخدم فيها الكوبون */
    public function recentOrders(CouponCampaign $campaign, int $limit = 10): Collection
    {
        return CouponRedemption::where('campaign_id', $campaign->id)
            ->with('order:id,order_number,status,created_at')
            ->latest('id')->limit($limit)->get();
    }
}
