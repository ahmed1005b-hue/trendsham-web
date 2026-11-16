<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;

/** شكل الكوبون في واجهة التطبيق. المبالغ بالليرة (÷100) مثل باقي الواجهة. */
class CouponPresenter
{
    public static function coupon(CustomerCoupon $c, ?EligibilityResult $r = null): array
    {
        $campaign = $c->campaign;

        return [
            'id' => $c->id,
            'title' => $campaign->title,
            'type' => $campaign->type,
            'value_label' => $campaign->valueLabel(),
            'source_label' => $campaign->sourceLabel(),
            'funder' => $campaign->funder,
            'min_order_amount' => $campaign->min_order_amount / 100,
            'status' => $c->status,
            'expires_at' => $c->effectiveExpiry()?->toIso8601String(),
            'used_at' => $c->used_at?->toIso8601String(),
            // حالة الحملة للعميل: paused = أوقفه المتجر مؤقتاً، suspended = لم يعد متاحاً
            'campaign_state' => $campaign->status,
            'campaign_state_label' => $campaign->campaignStateLabel(),
            'ok' => $r?->ok,
            'reason' => $r?->reason,
            'saving' => $r ? (int) round($r->saving() / 100) : null,
        ];
    }

    public static function campaign(CouponCampaign $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'type' => $c->type,
            'value' => $c->type === 'percentage' ? $c->value : $c->value / 100,
            'value_label' => $c->valueLabel(),
            'max_discount' => $c->max_discount !== null ? $c->max_discount / 100 : null,
            'min_order_amount' => $c->min_order_amount / 100,
            'funder' => $c->funder,
            'store_name' => $c->seller?->store_name,
            'scope' => $c->scope,
            'audience' => $c->audience,
            'audience_label' => CouponCampaign::AUDIENCES[$c->audience] ?? $c->audience,
            'starts_at' => $c->starts_at?->toIso8601String(),
            'ends_at' => $c->ends_at?->toIso8601String(),
            'valid_days' => $c->valid_days,
            'per_customer_limit' => $c->per_customer_limit,
            'total_limit' => $c->total_limit,
            'issued_count' => $c->issued_count,
            'redeemed_count' => $c->redeemed_count,
            'status' => $c->status,
            'is_running' => $c->isRunning(),
        ];
    }
}
