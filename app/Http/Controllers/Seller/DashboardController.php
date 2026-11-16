<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Showcase;
use App\Returns\Models\ReturnRequest;
use App\Services\ImageUploadService;
use App\Services\SellerAnalyticsService;
use App\Services\StorefrontAttribution;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function analytics(Request $request, SellerAnalyticsService $analytics)
    {
        $vendor = $request->user()->sellerProfile;
        $days = in_array((int) $request->query('days'), [7, 14, 30, 90], true) ? (int) $request->query('days') : 30;

        return view('seller.analytics', [
            'profile' => $vendor,
            'days' => $days,
            'data' => $analytics->summary($vendor, $days),
        ]);
    }

    public function editStore(Request $request)
    {
        $profile = $request->user()->sellerProfile;
        abort_unless($profile, 404);

        return view('seller.store.edit', compact('profile'));
    }

    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        // عناصر طلبات هذا البائع (منضمة بالطلب ليستثني الملغاة)
        $items = OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendor->id)
            ->where('orders.status', '!=', 'cancelled');

        // ===== إجماليات =====
        $totalSales = (int) $items->clone()->sum('order_items.subtotal');
        $ordersCount = (int) $items->clone()->distinct('order_id')->count('order_id');
        $commissionAmount = (int) $items->clone()->sum('order_items.commission_amount');
        $netProfit = $totalSales - $commissionAmount;
        $itemsCount = (int) $items->clone()->sum('order_items.quantity');

        // ===== مبيعات آخر 12 شهرًا =====
        $monthlySales = $this->salesSeries($vendor->id, now()->startOfMonth()->subMonths(11), now()->endOfMonth(), 'month');
        // ===== مبيعات آخر 14 يومًا =====
        $weeklySales = $this->salesSeries($vendor->id, now()->subDays(13)->startOfDay(), now()->endOfDay(), 'day');

        // نمو الشهر الحالي مقارنة بالشهر الماضي (%)
        $thisMonth = $monthlySales[count($monthlySales) - 1]['total'] ?? 0;
        $lastMonth = $monthlySales[count($monthlySales) - 2]['total'] ?? 0;
        $monthlyGrowth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null;

        // ===== أفضل المنتجات (بالمبيعات) =====
        $topProducts = Product::query()
            ->select([
                'products.id',
                'products.slug',
                'products.name',
                'products.price',
            ])
            ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('products.vendor_id', $vendor->id)
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy(
                'products.id',
                'products.slug',
                'products.name',
                'products.price',
            )
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as sold_count')
            ->selectRaw('COALESCE(SUM(order_items.subtotal), 0) as revenue')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->with('primaryImage')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'price' => $p->price / 100,
                'image' => $p->primaryImage ? asset('storage/'.$p->primaryImage->path) : null,
                'sold_count' => (int) $p->sold_count,
                'revenue' => $p->revenue / 100,
                'views_count' => (int) ($p->views_count ?? 0),
            ]);

        $storeMetrics = app(StorefrontAttribution::class)->dashboardMetrics($vendor->id);

        // ===== تقييم البائع =====
        $sellerRating = $vendor->getSellerRatingAttribute();
        $sellerReviewsCount = $vendor->sellerReviews()->count();

        // ===== تنبيهات المخزون المنخفض =====
        $lowStock = Product::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'stock_quantity', 'low_stock_threshold'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'stock_quantity' => $p->stock_quantity,
                'low_stock_threshold' => $p->low_stock_threshold,
            ]);

        $wallet = $vendor->walletOrCreate();

        // ===== العروض المؤجّرة قيد المراجعة =====
        $pendingShowcases = Showcase::where('seller_id', $vendor->id)
            ->where('status', 'pending_review')
            ->count();

        // ===== آخر الطلبات (حصة هذا البائع فقط من مبلغ كل طلب) =====
        $recentOrders = Order::query()
            ->whereHas('items', fn ($query) => $query->where('vendor_id', $vendor->id))
            ->where('status', '!=', 'cancelled')
            ->with('user')
            ->withSum(['items as vendor_subtotal' => fn ($query) => $query->where('vendor_id', $vendor->id)], 'subtotal')
            ->latest()
            ->limit(5)
            ->get();

        return view('seller.dashboard', [
            'profile' => $vendor,
            'wallet_balance' => $wallet->balance / 100,
            'products_count' => $vendor->products()->count(),
            'pending_products_count' => $vendor->products()->where('status', 'active')->whereNull('admin_approved_at')->count(),
            'pending_showcases_count' => $pendingShowcases,
            'returns_requested_count' => ReturnRequest::where('seller_profile_id', $vendor->id)->where('status', 'requested')->count(),
            'total_sales' => $totalSales / 100,
            'orders_count' => $ordersCount,
            'items_sold' => $itemsCount,
            'commission_amount' => $commissionAmount / 100,
            'net_profit' => $netProfit / 100,
            'weekly_sales' => $weeklySales,
            'monthly_sales' => $monthlySales,
            'monthly_growth' => $monthlyGrowth,
            ...$storeMetrics,
            'top_products' => $topProducts,
            'seller_rating' => $sellerRating,
            'seller_reviews_count' => $sellerReviewsCount,
            'followers_count' => $vendor->followers()->count(),
            'low_stock_products' => $lowStock,
            'low_stock_count' => $lowStock->count(),
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * سلسلة مبيعات موحدة: تجميع عناصر طلبات البائع بفاصل يومي أو شهري.
     */
    private function salesSeries(int $vendorId, Carbon $start, Carbon $end, string $interval): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendorId)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->selectRaw(
                $interval === 'month'
                    ? "DATE_FORMAT(order_items.created_at, '%Y-%m') as bucket, COALESCE(SUM(order_items.subtotal), 0) as total"
                    : 'DATE(order_items.created_at) as bucket, COALESCE(SUM(order_items.subtotal), 0) as total'
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $series = [];
        $cursor = $interval === 'month'
            ? $start->copy()->startOfMonth()
            : $start->copy()->startOfDay();

        while ($cursor <= $end) {
            $key = $interval === 'month'
                ? $cursor->format('Y-m')
                : $cursor->format('Y-m-d');
            $series[] = [
                'date' => $key,
                'total' => isset($rows[$key]) ? $rows[$key] / 100 : 0,
            ];
            if ($interval === 'month') {
                $cursor->addMonth();
            } else {
                $cursor->addDay();
            }
        }

        return $series;
    }

    public function updateProfile(Request $request)
    {
        $rawSchedule = $request->input('business_hours_schedule');
        if (is_string($rawSchedule)) {
            $decodedSchedule = trim($rawSchedule) === '' ? null : json_decode($rawSchedule, true);
            $request->merge([
                'business_hours_schedule' => $decodedSchedule === null
                    ? (trim($rawSchedule) === '' ? null : $rawSchedule)
                    : $decodedSchedule,
            ]);
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:255'],
            'store_description' => ['nullable', 'string', 'max:2000'],
            'store_contact_phone' => ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9\s().-]{6,32}$/'],
            'store_whatsapp' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (preg_match('/^\+?[0-9\s().-]{6,32}$/', $value)) {
                    return;
                }
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                if (! in_array($scheme, ['http', 'https'], true) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('أدخل رقم واتساب أو رابطاً آمناً يبدأ بـ http أو https.');
                }
            }],
            'store_instagram' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                if (! in_array($scheme, ['http', 'https'], true) || ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('أدخل رابطاً آمناً يبدأ بـ http أو https.');
                }
            }],
            'business_hours' => ['nullable', 'string', 'max:2000'],
            'business_hours_schedule' => ['nullable', 'array'],
            'business_hours_schedule.timezone' => ['nullable', 'timezone'],
            'business_hours_schedule.days' => ['required_with:business_hours_schedule', 'array:mon,tue,wed,thu,fri,sat,sun'],
            'business_hours_schedule.days.*' => ['array'],
            'business_hours_schedule.days.*.*.open' => ['required', 'date_format:H:i'],
            'business_hours_schedule.days.*.*.close' => ['required', 'date_format:H:i'],
            'shipping_policy' => ['nullable', 'string', 'max:4000'],
            'return_policy' => ['nullable', 'string', 'max:4000'],
            'commercial_register' => ['nullable', 'string', 'max:255'],
            'store_logo' => ImageUploadService::validationRules(false),
            'store_banner' => ImageUploadService::validationRules(false),
        ]);

        $vendor = $request->user()->sellerProfile;
        abort_unless($vendor, 404);

        $newPaths = [];
        foreach (['store_logo', 'store_banner'] as $field) {
            if (! $request->hasFile($field)) {
                unset($validated[$field]);

                continue;
            }

            $path = $this->imageUploadService->store($request->file($field), 'stores');
            if ($path === null) {
                foreach ($newPaths as $newPath) {
                    Storage::disk('public')->delete($newPath);
                }

                return back()
                    ->withErrors([$field => 'الملف المرفوع ليس صورة صالحة، جرّب صورة أخرى.'])
                    ->withInput();
            }

            $newPaths[] = $path;
            $validated[$field] = $path;
        }

        $oldPaths = array_filter([
            $request->hasFile('store_logo') ? $vendor->store_logo : null,
            $request->hasFile('store_banner') ? $vendor->store_banner : null,
        ]);
        $vendor->update($validated);
        foreach ($oldPaths as $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'تم تحديث بيانات المتجر بنجاح.');
    }
}
