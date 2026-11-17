<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;

/**
 * «شاهدته مؤخراً» في الموقع: أرقام آخر المنتجات المفتوحة في كوكي على جهاز الزائر
 * (زائر أو مسجّل). لا شيء يُحفظ في قاعدة البيانات. التطبيق يحفظ قائمته على الهاتف.
 */
final class RecentlyViewed
{
    public const COOKIE = 'ts_recent';

    public const MAX = 12;

    private const MINUTES = 60 * 24 * 60;

    /** @return list<int> الأحدث أولاً */
    public static function parseIds(mixed $raw, int $max = self::MAX): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), fn (int $id) => $id > 0)));

        return array_slice($ids, 0, $max);
    }

    /** @return list<int> */
    public static function ids(Request $request): array
    {
        return self::parseIds($request->cookie(self::COOKIE));
    }

    public static function remember(Request $request, int $productId): void
    {
        $ids = array_slice([$productId, ...array_diff(self::ids($request), [$productId])], 0, self::MAX);
        Cookie::queue(self::COOKIE, implode(',', $ids), self::MINUTES);
    }

    public static function forget(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /**
     * المنتجات الظاهرة للعامة بنفس الترتيب، مع ما تحتاجه بطاقة المنتج.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    public static function products(array $ids, ?int $exceptId = null, int $limit = self::MAX): Collection
    {
        $ids = array_values(array_diff($ids, [$exceptId]));
        if ($ids === []) {
            return collect();
        }

        $order = array_flip($ids);

        return Product::publiclyVisible()->whereIn('id', $ids)
            ->with(['primaryImage', 'images', 'vendor', 'category', 'activeDailyDeal'])
            ->withCount('reviews')->withAvg('reviews', 'rating')
            ->get()
            ->sortBy(fn (Product $product) => $order[$product->id])
            ->take($limit)
            ->values();
    }
}
