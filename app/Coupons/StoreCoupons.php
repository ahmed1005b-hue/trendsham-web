<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "كوبونات المتجر" في صفحة المتجر والمنتج (الموقع والتطبيق): الكوبونات القابلة للجمع
 * وحالتها لهذا العميل. كل عنصر: ['campaign' => CouponCampaign, 'state' => ..., 'remaining' => int].
 */
class StoreCoupons
{
    public const LIMIT = 10;

    public function forStore(SellerProfile $store, ?User $user): Collection
    {
        if (! $store->isApproved()) {
            return collect();
        }

        $campaigns = CouponCampaign::running()->ofSeller($store->id)->where('audience', 'collectable')
            ->with(['targets', 'giftProduct:id,name', 'buyProduct:id,name'])
            ->latest('id')->limit(self::LIMIT)->get()
            ->each(fn (CouponCampaign $c) => $c->setRelation('seller', $store));

        return $this->withState($campaigns, $user);
    }

    /** كوبونات متجر المنتج التي تشمل هذا المنتج */
    public function forProduct(Product $product, ?User $user): Collection
    {
        $store = $product->relationLoaded('vendor') ? $product->vendor : SellerProfile::find($product->vendor_id);
        if (! $store) {
            return collect();
        }
        $eligibility = app(CouponEligibility::class);

        return $this->forStore($store, $user)->filter(fn ($row) => $eligibility->covers($row['campaign'], $product))->values();
    }

    private function withState(Collection $campaigns, ?User $user): Collection
    {
        $held = $user && $campaigns->isNotEmpty()
            ? CustomerCoupon::where('user_id', $user->id)->whereIn('campaign_id', $campaigns->pluck('id'))
                ->where('status', 'available')->pluck('campaign_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return $campaigns->map(fn (CouponCampaign $c) => [
            'campaign' => $c,
            'remaining' => max(0, (int) $c->total_limit - (int) $c->issued_count),
            'state' => match (true) {
                in_array($c->id, $held, true) => 'collected',
                $c->isSoldOut() => 'sold_out',
                ! $user => 'login_required',
                default => 'collectable',
            },
        ]);
    }

    /** شكل واجهة التطبيق (المبالغ بالليرة) */
    public static function toApi(array $row): array
    {
        /** @var CouponCampaign $c */
        $c = $row['campaign'];

        return [
            'id' => $c->id,
            'title' => $c->title,
            'type' => $c->type,
            'value_label' => $c->valueLabel(),
            'min_order_amount' => (int) $c->min_order_amount / 100,
            'ends_at' => $c->ends_at?->toDateString(),
            'scope_label' => self::conditionLabel($c),
            'remaining' => $row['remaining'],
            'state' => $row['state'],
        ];
    }

    /** وصف قصير للشرط: النطاق والحد الأدنى */
    public static function conditionLabel(CouponCampaign $c): string
    {
        $parts = [];
        $parts[] = match (true) {
            $c->type === 'gift' => __('عند شراء :product', ['product' => $c->buyProduct?->name ?? __('المنتج المحدد')]),
            $c->type === 'free_shipping' => __('عندما تكون السلة كلها من المتجر'),
            $c->scope === 'products' => __('على منتجات محددة'),
            default => __('كل منتجات المتجر'),
        };
        if ($c->min_order_amount > 0) {
            $parts[] = __('عند شراء :product', ['product' => number_format($c->min_order_amount / 100).' '.__('ل.س')]);
        }

        return implode(' · ', $parts);
    }
}
