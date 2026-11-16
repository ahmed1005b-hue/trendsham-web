<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\StorefrontAttribution;
use App\Services\BusinessHoursStatus;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicStoreController extends Controller
{
    public function __construct(private readonly StorefrontAttribution $attribution)
    {
    }

    public function show(Request $request, SellerProfile $sellerProfile, BusinessHoursStatus $hoursStatus)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        $token = $this->attribution->tokenFor($request);
        $this->attribution->recordVisit($sellerProfile, $token);
        $filters = $this->validatedFilters($request);
        $products = $this->filteredProducts($sellerProfile, $request, $filters)->paginate(24)->withQueryString();

        return response()->view('stores.show', [
            'store' => $sellerProfile,
            'products' => $products,
            'bestSelling' => $this->featuredProducts($sellerProfile, true),
            'recentArrivals' => $this->featuredProducts($sellerProfile, false),
            'businessHoursStatus' => $hoursStatus->forSchedule($sellerProfile->business_hours_schedule),
            'isFollowed' => $request->user()
                ? $sellerProfile->followers()->where('user_id', $request->user()->id)->exists()
                : false,
            'similarProducts' => $this->similarProducts($sellerProfile, $products->getCollection()->first()),
            'stories' => $sellerProfile->stories()->visible()->latest()->limit(8)->get(),
            'storeCoupons' => app(\App\Coupons\StoreCoupons::class)->forStore($sellerProfile, $request->user()),
        ])->cookie(
            'trendsham_store_visitor',
            $token,
            525600,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        );
    }

    public function products(Request $request, SellerProfile $sellerProfile)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        $filters = $this->validatedFilters($request);
        $query = $this->filteredProducts($sellerProfile, $request, $filters);
        $products = $query->paginate(24)->withQueryString();

        return response()->json([
            'data' => ProductResource::collection($products->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function validatedFilters(Request $request): array
    {
        $filters = $request->validate([
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'rating_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'in_stock' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if (isset($filters['price_min'], $filters['price_max']) && $filters['price_min'] > $filters['price_max']) {
            throw ValidationException::withMessages(['price_min' => 'الحد الأدنى للسعر يجب ألا يتجاوز الحد الأعلى.']);
        }

        return $filters;
    }

    private function filteredProducts(SellerProfile $store, Request $request, array $filters)
    {
        $query = Product::publiclyVisible()
            ->where('vendor_id', $store->id)
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');
        if ($request->user()) {
            $query->withExists(['favorites as is_favorited' => fn ($favorites) => $favorites->where('user_id', $request->user()->id)]);
        }
        if (isset($filters['price_min'])) $query->where('price', '>=', (int) round($filters['price_min'] * 100));
        if (isset($filters['price_max'])) $query->where('price', '<=', (int) round($filters['price_max'] * 100));
        if (isset($filters['rating_min']) && $filters['rating_min'] > 0) $query->having('reviews_avg_rating', '>=', $filters['rating_min']);
        if (! empty($filters['in_stock'])) $query->where('stock_quantity', '>', 0);

        return $query->latest();
    }

    private function similarProducts(SellerProfile $store, ?Product $anchor)
    {
        if (! $anchor || ! $anchor->category_id) return collect();
        return Product::publiclyVisible()
            ->where('vendor_id', $store->id)
            ->where('category_id', $anchor->category_id)
            ->whereKeyNot($anchor->id)
            ->whereBetween('price', [(int) floor($anchor->price * .7), (int) ceil($anchor->price * 1.3)])
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->limit(8)
            ->get();
    }

    private function featuredProducts(SellerProfile $store, bool $bestSelling)
    {
        $sales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as sold_quantity');

        $query = Product::publiclyVisible()
            ->where('products.vendor_id', $store->id)
            ->leftJoinSub($sales, 'sales', fn ($join) => $join->on('sales.product_id', '=', 'products.id'))
            ->select('products.*')
            ->selectRaw('COALESCE(sales.sold_quantity, 0) as sold_quantity')
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($bestSelling) {
            $query->orderByDesc('sold_quantity');
        }

        return $query->latest('products.created_at')->limit(8)->get();
    }
}
