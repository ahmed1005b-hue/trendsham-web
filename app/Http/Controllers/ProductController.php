<?php

namespace App\Http\Controllers;

use App\Coupons\StoreCoupons;
use App\Models\Product;
use App\Services\ProductRecommendationService;
use App\Support\RecentlyViewed;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, ProductRecommendationService $recommendations)
    {
        $canPreview = $request->user() && (
            $request->user()->role === 'admin'
            || ($request->user()->role === 'seller' && $request->user()->sellerProfile?->id === $product->vendor_id)
        );

        abort_unless($product->isLive() || $canPreview, 404);

        $product->load(['images', 'vendor', 'category', 'reviews.user', 'questions.user', 'questions.answers.user', 'activeDailyDeal', 'variants' => fn ($q) => $q->where('is_active', true)]);
        $suggestions = $recommendations->forProduct($product);
        if ($product->isLive()) {
            RecentlyViewed::remember($request, $product->id);
        }

        return view('products.show', [
            'product' => $product,
            'storeCoupons' => app(StoreCoupons::class)->forProduct($product, $request->user()),
            ...$suggestions,
        ]);
    }
}
