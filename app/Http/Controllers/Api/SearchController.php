<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        $products = Product::publiclyVisible()
            ->with(['primaryImage', 'images', 'category', 'vendor', 'activeDailyDeal'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating');

        if ($query !== '') {
            // بحث ذكي: مطابقة كاملة في الاسم والوصف + اسم البائع + القسم
            // + مطابقة جزئية للاسم ورمز المنتج (SKU).
            $terms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
            $products->where(function ($q) use ($query, $terms) {
                $q->whereFullText(['name', 'description'], $query)
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('slug', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhereHas('vendor', fn($v) => $v->where('store_name', 'like', "%{$query}%"))
                    ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$query}%"));

                // مطابقة جميع الكلمات (الكلمة الرابعة تحافظ على النتائج التي بها الثانية)
                foreach (array_slice($terms, 0, 3) as $term) {
                    $q->orWhere('name', 'like', "%{$term}%");
                }
            });
        }

        // ===== الفلاتر =====
        $products->priceBetween($request->get('min_price'), $request->get('max_price'));

        // فلتر القسم (slug القسم)
        if ($slug = $request->get('category')) {
            $products->whereHas('category', fn($c) => $c->where('slug', $slug));
        }

        // فلتر البائع (معرّف المتجر)
        if ($store = $request->get('store')) {
            $products->where('vendor_id', (int) $store);
        }

        // فلتر التوفر (in_stock=1 يقتصر على المتوفر)
        if ($request->boolean('in_stock')) {
            $products->where('stock_quantity', '>', 0);
        }

        // فلتر التقييم (3+ نجوم)
        if ($minRating = $request->get('min_rating')) {
            $products->having('reviews_avg_rating', '>=', (float) $minRating);
        }

        // فلتر الخصم (المنتجات التي لها سعر مقارنة أعلى)
        if ($request->boolean('on_sale')) {
            $products->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price');
        }

        // الترتيب: relevance يعطي أولوية للنتائج الأكثر مطابقة (الافتراضي هو newest)
        $products->applySort($request->get('sort', $query !== '' ? 'relevance' : null));

        return ProductResource::collection($products->paginate($request->get('per_page', 20)));
    }

    /**
     * اقتراحات فورية (autocomplete) أثناء كتابة المستخدم
     * يعيد: أسماء المنتجات المطابقة + الأقسام + المتاجر
     */
    public function suggestions(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'products' => [],
                'categories' => [],
                'stores' => [],
            ]);
        }

        // أفضل 5 منتجات مطابقة (بالاسم أولاً)
        $products = Product::publiclyVisible()
            ->with('primaryImage')
            ->where('name', 'like', "%{$query}%")
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'slug', 'name', 'price']);

        // الأقسام المطابقة
        $categories = Category::query()
            ->where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(3)
            ->get(['id', 'slug', 'name']);

        // المتاجر المطابقة
        $stores = SellerProfile::query()
            ->where('status', 'approved')
            ->where('store_name', 'like', "%{$query}%")
            ->limit(3)
            ->get(['id', 'store_name']);

        return response()->json([
            'products' => $products->map(fn($p) => [
                'slug' => $p->slug,
                'name' => $p->name,
                'price' => $p->price / 100,
                'image' => $p->primaryImage ? asset('storage/'.$p->primaryImage->path) : null,
            ]),
            'categories' => $categories->map(fn($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
            ]),
            'stores' => $stores->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->store_name,
            ]),
        ]);
    }

    /**
     * الكلمات الأكثر رواجاً وبحثاً في المتجر
     */
    public function trending()
    {
        return response()->json([
            'trending' => [
                'عطور',
                'إلكترونيات',
                'أزياء نسائية',
                'أزياء رجالية',
                'ساعات',
                'حقائب',
                'أحذية',
                'المنزل والمطبخ',
                'تنزيلات',
            ],
        ]);
    }
}
