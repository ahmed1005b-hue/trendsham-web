<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\Category;
use App\Models\SellerProfile;
use Illuminate\Support\Collection;

/**
 * القاعدة الوحيدة لصلاحية كوبون على سلة (الموقع والتطبيق والدفع).
 * السطر = كائن فيه product (Product) و subtotal (int بالوحدة الصغرى).
 */
class CouponEligibility
{
    public function check(CustomerCoupon $coupon, Collection $lines, int $shippingFee): EligibilityResult
    {
        $campaign = $coupon->campaign;

        if ($reason = $this->stateReason($coupon, $campaign)) {
            return EligibilityResult::no($reason);
        }

        $eligible = $lines->filter(fn ($line) => $this->covers($campaign, $line->product));
        if ($eligible->isEmpty()) {
            return EligibilityResult::no($this->scopeReason($campaign));
        }

        // شحن مجاني من البائع: الشحن رسم واحد للطلب، فيُشترط أن تكون السلة كلها من متجره
        if ($campaign->funder === 'seller' && $campaign->type === 'free_shipping'
            && $lines->contains(fn ($line) => (int) $line->product->vendor_id !== (int) $campaign->seller_profile_id)) {
            return EligibilityResult::no(__('يعمل عندما تكون كل السلة من :store', ['store' => $campaign->seller?->store_name]));
        }

        $eligibleTotal = (int) $eligible->sum('subtotal');
        if ($eligibleTotal < $campaign->min_order_amount) {
            return EligibilityResult::no(__('أضف :amount ل.س لتستفيد', ['amount' => number_format(($campaign->min_order_amount - $eligibleTotal) / 100)]));
        }

        if ($campaign->type === 'gift') {
            return $this->giftResult($campaign, $lines);
        }

        if ($campaign->type === 'free_shipping') {
            return $shippingFee > 0
                ? new EligibilityResult(true, null, 0, $shippingFee)
                : EligibilityResult::no(__('الشحن مجاني لهذا الطلب أصلاً'));
        }

        $discount = match ($campaign->type) {
            'percentage' => (int) round($eligibleTotal * $campaign->value / 100),
            'fixed' => (int) $campaign->value,
            default => 0, // gift: المرحلة 2
        };
        if ($campaign->type === 'percentage' && $campaign->max_discount) {
            $discount = min($discount, $campaign->max_discount);
        }
        $discount = max(0, min($discount, $eligibleTotal));

        return new EligibilityResult(true, null, $discount, 0, $this->distribute($eligible, $eligibleTotal, $discount));
    }

    /** هدية: المنتج الشرط في السلة، ومنتج الهدية متاح ومخزونه يكفي (بعد ما في السلة منه). */
    private function giftResult(CouponCampaign $campaign, Collection $lines): EligibilityResult
    {
        $buy = $campaign->buyProduct;
        $gift = $campaign->giftProduct;
        if (! $buy || ! $gift) {
            return EligibilityResult::no(__('لم يعد متاحاً'));
        }
        if (! $lines->contains(fn ($line) => (int) $line->product->id === (int) $buy->id)) {
            return EligibilityResult::no(__('اشترِ :product لتحصل على الهدية', ['product' => $buy->name]));
        }
        $inCart = (int) $lines->filter(fn ($line) => (int) $line->product->id === (int) $gift->id)->sum(fn ($line) => (int) ($line->quantity ?? 1));
        if (! $gift->isLive() || (int) $gift->stock_quantity - $inCart < 1) {
            return EligibilityResult::no(__('الهدية نفدت'));
        }

        return new EligibilityResult(true, null, 0, 0, [], $gift->id, (int) $gift->effective_price);
    }

    private function stateReason(CustomerCoupon $coupon, CouponCampaign $campaign): ?string
    {
        return match (true) {
            $coupon->status === 'used' => __('استُخدم في طلب سابق'),
            $coupon->status !== 'available' => __('غير متاح'),
            $coupon->isExpired() => __('انتهت صلاحيته'),
            $campaign->starts_at && $campaign->starts_at->gt(now()) => __('يبدأ في :date', ['date' => $campaign->starts_at->format('Y-m-d')]),
            $campaign->status === 'paused' && $campaign->funder === 'seller' => __('أوقفه المتجر مؤقتاً'),
            ! $campaign->isRunning() => __('لم يعد متاحاً'),
            // كوبون العملاء الجدد للطلب الأول فقط
            $campaign->audience === 'new_customers' && $coupon->user
                && $coupon->user->orders()->where('status', '!=', 'cancelled')->exists() => __('للطلب الأول فقط'),
            default => null,
        };
    }

    /** هل يشمل الكوبون هذا المنتج؟ كوبون البائع لا يشمل إلا منتجات متجره أياً كان نطاقه. */
    public function covers(CouponCampaign $campaign, $product): bool
    {
        if ($campaign->funder === 'seller' && (int) $product->vendor_id !== (int) $campaign->seller_profile_id) {
            return false;
        }

        return match ($campaign->scope) {
            'all' => true,
            'products' => in_array((int) $product->id, $campaign->targetIds('product'), true),
            'categories' => in_array((int) $product->category_id, $campaign->targetIds('category'), true),
            'stores' => in_array((int) $product->vendor_id, $campaign->targetIds('store'), true),
        };
    }

    private function scopeReason(CouponCampaign $campaign): string
    {
        $or = ' '.__('أو').' ';

        return match (true) {
            $campaign->scope === 'stores' => __('يعمل على منتجات :stores فقط', ['stores' => SellerProfile::whereIn('id', $campaign->targetIds('store'))->pluck('store_name')->implode($or)]),
            $campaign->scope === 'categories' => __('يعمل على قسم :categories فقط', ['categories' => Category::whereIn('id', $campaign->targetIds('category'))->pluck('name')->map(fn ($name) => __('categories.'.$name) === 'categories.'.$name ? $name : __('categories.'.$name))->implode($or)]),
            $campaign->funder === 'seller' && $campaign->scope === 'all' => __('يعمل على منتجات :stores فقط', ['stores' => $campaign->seller?->store_name]),
            default => __('يعمل على منتجات محددة فقط'),
        };
    }

    /** توزيع الخصم على البنود المشمولة بنسبة قيمتها، والباقي على آخر بند حتى يطابق المجموع. */
    private function distribute(Collection $eligible, int $eligibleTotal, int $discount): array
    {
        $shares = [];
        $remaining = $discount;
        $lastKey = $eligible->keys()->last();
        foreach ($eligible as $key => $line) {
            // قسمة قبل الضرب: أسعار الليرة الكبيرة قد تتجاوز حد الأعداد الصحيحة عند الضرب أولاً
            $share = $key === $lastKey ? $remaining : (int) floor($discount * ((int) $line->subtotal / $eligibleTotal));
            $share = max(0, min($share, (int) $line->subtotal, $remaining));
            $shares[$key] = $share;
            $remaining -= $share;
        }

        return $shares;
    }
}
