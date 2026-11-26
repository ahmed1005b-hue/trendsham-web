<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'variant_id', 'quantity', 'unit_price', 'original_unit_price', 'storefront_visit_id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storefrontVisit()
    {
        return $this->belongsTo(StorefrontVisit::class);
    }

    /**
     * سعر الوحدة الآن وسعرها الأصلي: سعر «عرض اليوم» ما دام العرض قائماً، مع فرق اللون/المقاس.
     *
     * @return array{0: int, 1: int}
     */
    public static function currentPrices(Product $product, ?ProductVariant $variant): array
    {
        $deal = $product->relationLoaded('activeDailyDeal') ? $product->activeDailyDeal : $product->activeDailyDeal()->first();
        $adjustment = $variant ? (int) $variant->price_adjustment : 0;
        // العرض لا يرفع السعر أبداً
        $unitPrice = ($deal ? min((int) $product->price, (int) round($deal->discounted_price)) : (int) $product->price) + $adjustment;

        return [$unitPrice, (int) $product->price + $adjustment];
    }

    /** هل سعر الوحدة المحفوظ في السلة هو سعر المنتج الآن؟ */
    public function hasCurrentPrice(Product $product): bool
    {
        return $this->unit_price !== null
            && (int) $this->unit_price === self::currentPrices($product, $this->variant)[0];
    }

    /**
     * يحدّث أسعار البنود التي تغيّر سعرها منذ أُضيفت (انتهى العرض أو عدّل البائع السعر).
     * بند قديم بلا سعر محفوظ تعرضه السلة بسعره الحالي أصلاً، فيُملأ دون أن يُعدّ تغيّراً.
     *
     * @param  Collection<int, self>  $items
     * @return bool هل تغيّر سعر بند واحد على الأقل
     */
    public static function repriceStale(Collection $items): bool
    {
        $changed = false;
        foreach ($items as $item) {
            $hadPrice = $item->unit_price !== null;
            if ($hadPrice && $item->original_unit_price !== null && $item->hasCurrentPrice($item->product)) {
                continue;
            }
            $changed = $changed || ($hadPrice && ! $item->hasCurrentPrice($item->product));
            [$item->unit_price, $item->original_unit_price] = self::currentPrices($item->product, $item->variant);
            $item->save();
        }

        return $changed;
    }

    public function getSubtotalAttribute(): int
    {
        $unitPrice = $this->unit_price ?? ($this->product ? $this->product->price : 0);

        return $unitPrice * $this->quantity;
    }
}
