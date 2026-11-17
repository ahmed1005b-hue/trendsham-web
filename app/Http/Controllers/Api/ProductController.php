<?php

namespace App\Http\Controllers\Api;

use App\Coupons\StoreCoupons;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Loyalty\LoyaltyCalculator;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use App\Support\RecentlyViewed;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // «شاهدته مؤخراً» في التطبيق: ?ids=5,9,2 بنفس الترتيب ودون صفحات
        if ($request->filled('ids')) {
            return ProductResource::collection(
                RecentlyViewed::products(RecentlyViewed::parseIds($request->get('ids'), 20), limit: 20)
            );
        }

        $products = Product::publiclyVisible()
            ->with(['primaryImage', 'images', 'category', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->priceBetween($request->get('min_price'), $request->get('max_price'))
            ->when($request->get('category'), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->applySort($request->get('sort'))
            ->paginate(min(max((int) $request->get('per_page', 20), 1), 100));

        return ProductResource::collection($products);
    }

    public function show(Request $request, Product $product)
    {
        $canPreview = $request->user() && (
            $request->user()->role === 'admin'
            || ($request->user()->role === 'seller' && $request->user()->sellerProfile?->id === $product->vendor_id)
        );

        abort_unless($product->isLive() || $canPreview, 404);

        $product->load(['images', 'vendor', 'category', 'activeDailyDeal', 'variants' => fn ($q) => $q->where('is_active', true)]);
        $product->loadCount('reviews')->loadAvg('reviews', 'rating');

        if ($request->user()) {
            $product->is_favorited = $request->user()->hasFavorited($product->id);
        }

        // نقاط الولاء المتوقعة لشراء قطعة واحدة
        $calc = app(LoyaltyCalculator::class);
        $product->will_earn_points = $calc->settings()->enabled() ? $calc->pointsFor((int) $product->effective_price) : 0;

        $product->store_coupons = app(StoreCoupons::class)
            ->forProduct($product, auth('sanctum')->user() ?? $request->user())
            ->map(fn ($row) => StoreCoupons::toApi($row))->values()->all();

        return new ProductResource($product);
    }

    public function reviews(Product $product)
    {
        abort_unless($product->isLive(), 404);

        return ReviewResource::collection(
            $product->reviews()->with('user')->latest()->paginate(20)
        );
    }

    public function recommendations(Request $request, Product $product, ProductRecommendationService $recommendations)
    {
        abort_unless($product->isLive(), 404);

        $suggestions = $recommendations->forProduct($product);

        return response()->json([
            'similar' => ProductResource::collection($suggestions['similar'])->resolve($request),
            'popular' => ProductResource::collection($suggestions['popular'])->resolve($request),
        ]);
    }
}
