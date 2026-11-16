<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Services\StorefrontAttribution;
use App\Services\BusinessHoursStatus;
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
        $viewer = auth('sanctum')->user() ?? $request->user();

        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $validated = $request->validate([
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'rating_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'in_stock' => ['nullable', 'boolean'],
        ]);
        if (isset($validated['price_min'], $validated['price_max']) && $validated['price_min'] > $validated['price_max']) {
            throw ValidationException::withMessages(['price_min' => 'الحد الأدنى للسعر يجب ألا يتجاوز الحد الأعلى.']);
        }

        $query = Product::publiclyVisible()
            ->where('vendor_id', $sellerProfile->id)
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withAvg('reviews', 'rating');
        if ($viewer) {
            $query->withExists(['favorites as is_favorited' => fn ($favorites) => $favorites->where('user_id', $viewer->id)]);
        }
        if (isset($validated['price_min'])) {
            $query->where('price', '>=', (int) round($validated['price_min'] * 100));
        }
        if (isset($validated['price_max'])) {
            $query->where('price', '<=', (int) round($validated['price_max'] * 100));
        }
        if (isset($validated['rating_min']) && $validated['rating_min'] > 0) {
            $query->having('reviews_avg_rating', '>=', $validated['rating_min']);
        }
        if (! empty($validated['in_stock'])) {
            $query->where('stock_quantity', '>', 0);
        }
        $products = $query->latest()->paginate($perPage)->withQueryString();

        $bestSelling = $this->featuredProducts($sellerProfile, true);
        $recentArrivals = $this->featuredProducts($sellerProfile, false);

        return response()->json([
            'store' => [
                'id' => $sellerProfile->id,
                'store_name' => $sellerProfile->store_name,
                'store_description' => $sellerProfile->store_description,
                'store_logo' => $sellerProfile->store_logo
                    ? asset('storage/'.$sellerProfile->store_logo)
                    : null,
                'store_banner' => $sellerProfile->store_banner
                    ? asset('storage/'.$sellerProfile->store_banner)
                    : null,
                'products_count' => $products->total(),
                'seller_rating' => $sellerProfile->getSellerRatingAttribute(),
                'seller_reviews_count' => $sellerProfile->sellerReviews()->count(),
                'store_contact_phone' => $sellerProfile->store_contact_phone,
                'store_whatsapp' => $sellerProfile->store_whatsapp_url,
                'store_instagram' => $sellerProfile->store_instagram_url,
                'business_hours' => $sellerProfile->business_hours,
                'business_hours_schedule' => $sellerProfile->business_hours_schedule,
                'shipping_policy' => $sellerProfile->shipping_policy,
                'return_policy' => $sellerProfile->return_policy,
                'business_hours_status' => $hoursStatus->forSchedule($sellerProfile->business_hours_schedule),
                'is_followed' => $viewer
                    ? $sellerProfile->followers()->where('user_id', $viewer->id)->exists()
                    : false,
                'followers_count' => $sellerProfile->followers()->count(),
            ],
            'visit_token' => $token,
            // كوبونات المتجر القابلة للجمع وحالتها لهذا العميل
            'coupons' => app(\App\Coupons\StoreCoupons::class)->forStore($sellerProfile, $viewer)
                ->map(fn ($row) => \App\Coupons\StoreCoupons::toApi($row))->values(),
            'featured' => [
                'best_selling' => ProductResource::collection($bestSelling)->resolve($request),
                'recent_arrivals' => ProductResource::collection($recentArrivals)->resolve($request),
            ],
            'products' => [
                'data' => ProductResource::collection($products->getCollection())->resolve($request),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ],
            'similar_products' => $this->similarProducts($sellerProfile, $products->getCollection()->first()),
            'stories' => $sellerProfile->stories()->visible()->latest()->limit(8)->get()->map(fn ($story) => [
                'id' => $story->id,
                'image' => asset('storage/'.$story->image),
                'caption' => $story->caption,
                'expires_at' => $story->expires_at,
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function products(Request $request, SellerProfile $sellerProfile)
    {
        abort_unless($sellerProfile->isApproved(), 404);
        $validated = $request->validate([
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'rating_min' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'in_stock' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        if (isset($validated['price_min'], $validated['price_max']) && $validated['price_min'] > $validated['price_max']) {
            throw ValidationException::withMessages(['price_min' => 'الحد الأدنى للسعر يجب ألا يتجاوز الحد الأعلى.']);
        }
        $viewer = auth('sanctum')->user() ?? $request->user();
        $query = Product::publiclyVisible()
            ->where('vendor_id', $sellerProfile->id)
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withAvg('reviews', 'rating');
        if ($viewer) {
            $query->withExists(['favorites as is_favorited' => fn ($favorites) => $favorites->where('user_id', $viewer->id)]);
        }
        if (isset($validated['price_min'])) $query->where('price', '>=', (int) round($validated['price_min'] * 100));
        if (isset($validated['price_max'])) $query->where('price', '<=', (int) round($validated['price_max'] * 100));
        if (isset($validated['rating_min']) && $validated['rating_min'] > 0) $query->having('reviews_avg_rating', '>=', $validated['rating_min']);
        if (! empty($validated['in_stock'])) $query->where('stock_quantity', '>', 0);

        $products = $query->latest()->paginate($validated['per_page'] ?? 20)->withQueryString();

        return response()->json([
            'data' => ProductResource::collection($products->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function similarProducts(SellerProfile $store, ?Product $anchor): array
    {
        if (! $anchor || ! $anchor->category_id) {
            return [];
        }

        $minimum = (int) floor($anchor->price * 0.7);
        $maximum = (int) ceil($anchor->price * 1.3);
        $similar = Product::publiclyVisible()
            ->where('vendor_id', $store->id)
            ->where('category_id', $anchor->category_id)
            ->whereKeyNot($anchor->id)
            ->whereBetween('price', [$minimum, $maximum])
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withAvg('reviews', 'rating')
            ->latest()
            ->limit(8)
            ->get();

        return \App\Http\Resources\ProductResource::collection($similar)->resolve(request());
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
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal']);

        if ($bestSelling) {
            $query->orderByDesc('sold_quantity');
        }

        return $query->latest('products.created_at')->limit(8)->get();
    }
}
