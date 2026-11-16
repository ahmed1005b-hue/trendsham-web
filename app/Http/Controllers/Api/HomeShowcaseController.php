<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Showcase;
use App\Services\ShowcaseService;
use App\Showcases\ShowcasePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeShowcaseController extends Controller
{
    /** GET /api/v1/home/showcases */
    public function index()
    {
        $data = Cache::remember(ShowcaseService::CACHE_KEY, 300, function () {
            return Showcase::live()
                ->with([
                    'slot', 'seller.user',
                    'products' => fn ($q) => $q->where('products.status', 'active')->where('products.stock_quantity', '>', 0),
                    'products.images', 'products.primaryImage',
                ])
                ->get()
                // إن نقصت المنتجات المتاحة عن الحد الأدنى للمساحة (نفاد مخزون مثلاً) يُخفى العرض مؤقتاً
                ->filter(fn ($s) => $s->products->count() >= $s->slot->min_products)
                ->sortBy(fn ($s) => $s->slot->position)
                ->map(fn ($s) => ShowcasePresenter::card($s))
                ->values()
                ->all();
        });

        return response()->json(['data' => $data]);
    }

    /** POST /api/v1/showcases/track  {events:[{id, type: impression|click}]} */
    public function track(Request $r)
    {
        $r->validate([
            'events'        => 'required|array|max:20',
            'events.*.id'   => 'required|integer',
            'events.*.type' => 'required|in:impression,click',
        ]);

        foreach ($r->events as $e) {
            Showcase::live()->whereKey($e['id'])
                ->increment($e['type'] === 'click' ? 'clicks' : 'impressions');
        }

        return response()->noContent();
    }
}