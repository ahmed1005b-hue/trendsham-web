<?php

namespace App\Support;

use App\Models\Product;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * منتجات أقسام الصفحة الرئيسية في الموقع والتطبيق: الأحدث أولاً مع حدّ لكل متجر،
 * حتى لا يحتل القسمَ متجرٌ أضاف منتجات كثيرة دفعة واحدة.
 * إن لم تكفِ المتاجر لملء القسم تُكمَّل الأماكن الباقية بالأحدث دون حدّ.
 */
class HomeProducts
{
    public const NEW_ARRIVALS_LIMIT = 12;

    public const NEW_ARRIVALS_PER_STORE = 2;

    public const DEALS_LIMIT = 4;

    public const DEALS_PER_STORE = 1;

    /** «وصل حديثاً». */
    public static function newArrivals(): Collection
    {
        return self::spreadAcrossStores(
            fn (Builder $query) => $query,
            self::NEW_ARRIVALS_LIMIT,
            self::NEW_ARRIVALS_PER_STORE,
        );
    }

    /** «العروض»: منتجات سعرها أقل من سعرها قبل الخصم. */
    public static function deals(): Collection
    {
        return self::spreadAcrossStores(
            fn (Builder $query) => $query->whereNotNull('products.compare_at_price')
                ->whereColumn('products.compare_at_price', '>', 'products.price'),
            self::DEALS_LIMIT,
            self::DEALS_PER_STORE,
        );
    }

    /**
     * @param  Closure(Builder): Builder  $filter
     */
    private static function spreadAcrossStores(Closure $filter, int $limit, int $perStore): Collection
    {
        $rankedWithinStore = $filter(Product::query()->publiclyVisible())
            ->select('products.id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY products.vendor_id ORDER BY products.created_at DESC, products.id DESC) AS store_rank');

        $ids = Product::query()
            ->joinSub($rankedWithinStore, 'ranked', 'ranked.id', '=', 'products.id')
            ->where('ranked.store_rank', '<=', $perStore)
            ->orderByDesc('products.created_at')
            ->orderByDesc('products.id')
            ->limit($limit)
            ->pluck('products.id');

        if ($ids->count() < $limit) {
            $ids = $ids->concat($filter(Product::query()->publiclyVisible())
                ->whereNotIn('products.id', $ids)
                ->orderByDesc('products.created_at')
                ->orderByDesc('products.id')
                ->limit($limit - $ids->count())
                ->pluck('products.id'));
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->with(['primaryImage', 'images', 'vendor', 'category', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get()
            ->keyBy('id');

        return new Collection($ids->map(fn (int $id) => $products->get($id))->filter()->values()->all());
    }
}
