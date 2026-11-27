<?php

namespace App\Http\Controllers;

use App\Models\DailyDeal;
use App\Models\Showcase;
use App\Models\Story;
use App\Support\AppCache;
use App\Support\HomeProducts;
use App\Support\RecentlyViewed;

class HomeController extends Controller
{
    public function index()
    {
        if (auth()->check() && in_array(auth()->user()->role, ['admin', 'seller', 'employee'])) {
            return redirect()->route(match (auth()->user()->role) {
                'admin' => 'admin.dashboard',
                'seller' => 'seller.dashboard',
                default => 'employee.dashboard',
            });
        }

        // نفس المحتوى لكل الزوار: من الكاش لمدة قصيرة بدل عشرات الاستعلامات مع كل زيارة
        $data = AppCache::home(function () {
            $dailyDeals = DailyDeal::with(['product.primaryImage', 'product.images', 'product.vendor'])
                ->with(['product' => fn ($q) => $q->withCount('reviews')->withAvg('reviews', 'rating')->with('activeDailyDeal')])
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->where(function ($q) {
                    $q->where('max_quantity', 0)
                        ->orWhereColumn('sold_quantity', '<', 'max_quantity');
                })
                ->whereHas('product', fn ($p) => $p->publiclyVisible())
                ->latest()
                ->limit(12)
                ->get();

            $showcasesBySlot = Showcase::live()
                ->with([
                    'slot',
                    'seller',
                    'products' => fn ($query) => $query
                        ->where('products.status', 'active')
                        ->whereNotNull('products.admin_approved_at')
                        ->where('products.stock_quantity', '>', 0)
                        ->with('primaryImage', 'images', 'vendor', 'activeDailyDeal')   // بطاقة المنتج الموحّدة
                        ->withCount('reviews')->withAvg('reviews', 'rating'),
                ])
                ->get()
                ->filter(fn ($showcase) => $showcase->slot && $showcase->seller
                    && $showcase->products->count() >= $showcase->slot->min_products)
                ->sortBy(fn ($showcase) => $showcase->slot->position)
                ->groupBy(fn ($showcase) => $showcase->slot->key);

            return [
                'stories' => Story::visible()->with('vendor')->orderByRaw('vendor_id IS NOT NULL')->latest()->limit(20)->get(),
                'dealProducts' => HomeProducts::deals(),
                'newProducts' => HomeProducts::newArrivals(),
                'dailyDeals' => $dailyDeals,
                'dailyDeal' => $dailyDeals->first(),
                'showcasesBySlot' => $showcasesBySlot,
            ];
        });

        return view('home', $data + [
            'categories' => AppCache::navCategories(),
            // خاص بكل زائر (من الكوكي)، فهو خارج الكاش
            'recentProducts' => RecentlyViewed::products(RecentlyViewed::ids(request()), limit: 6),
        ]);
    }
}
