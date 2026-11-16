<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات المنصة الشاملة للمدير: المبيعات، المستخدمون، المنتجات، المرتجعات، المال، التسويق، الدعم.
 * يستخدمه الويب والتطبيق (API) — نفس الأرقام في المكانين.
 * كل المبالغ تُعاد بالوحدة الكبرى (ل.س) جاهزة للعرض.
 */
class AdminAnalyticsService
{
    /** حالات الطلب التي تُحسب مبيعات فعلية. */
    public const SALE_STATUSES = ['paid', 'processing', 'shipped', 'delivered'];

    /** النوافذ الزمنية المسموحة بالأيام. */
    public const WINDOWS = [7, 30, 90, 365];

    public static function normalizeDays(mixed $days): int
    {
        return in_array((int) $days, self::WINDOWS, true) ? (int) $days : 30;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $days = 30): array
    {
        $days = self::normalizeDays($days);
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();
        $prevStart = $start->copy()->subDays($days);
        $prevEnd = $start->copy()->subSecond();

        $current = $this->salesTotals($start, $end);
        $previous = $this->salesTotals($prevStart, $prevEnd);

        $newCustomers = User::customers()->whereBetween('created_at', [$start, $end])->count();
        $prevNewCustomers = User::customers()->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $allOrders = Order::whereBetween('created_at', [$start, $end])->count();
        $cancelled = Order::whereBetween('created_at', [$start, $end])->where('status', 'cancelled')->count();
        $refunded = (int) DB::table('return_refunds')->whereBetween('created_at', [$start, $end])->sum('amount');

        return [
            'days' => $days,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'kpis' => [
                'gmv' => $current['gmv'] / 100,
                'orders' => $current['orders'],
                'avg_order_value' => $current['orders'] > 0 ? round($current['gmv'] / $current['orders'] / 100) : 0,
                'commission' => $current['commission'] / 100,
                'new_customers' => $newCustomers,
                'refunded' => $refunded / 100,
                'cancel_rate' => $allOrders > 0 ? round($cancelled / $allOrders * 100, 1) : 0,
            ],
            'changes' => [
                'gmv' => $this->change($current['gmv'], $previous['gmv']),
                'orders' => $this->change($current['orders'], $previous['orders']),
                'commission' => $this->change($current['commission'], $previous['commission']),
                'new_customers' => $this->change($newCustomers, $prevNewCustomers),
            ],
            'series' => $this->dailySeries($start, $end, $days),
            'order_status' => $this->orderStatus($start, $end),
            'payment_methods' => $this->paymentMethods($start, $end),
            'top_sellers' => $this->topSellers($start, $end),
            'top_products' => $this->topProducts($start, $end),
            'top_categories' => $this->topCategories($start, $end),
            'users' => $this->users($start, $end),
            'catalog' => $this->catalog(),
            'returns' => $this->returns($start, $end),
            'finance' => $this->finance($start, $end),
            'marketing' => $this->marketing($start, $end),
            'support' => $this->support(),
        ];
    }

    /** @return array{gmv:int, orders:int, commission:int} */
    private function salesTotals(Carbon $from, Carbon $to): array
    {
        $orders = Order::whereIn('status', self::SALE_STATUSES)->whereBetween('created_at', [$from, $to]);

        return [
            'gmv' => (int) $orders->clone()->sum('total'),
            'orders' => (int) $orders->clone()->count(),
            'commission' => (int) OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.status', self::SALE_STATUSES)
                ->whereBetween('orders.created_at', [$from, $to])
                ->sum('order_items.commission_amount'),
        ];
    }

    private function change(int|float $current, int|float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100) : null;
    }

    /**
     * سلسلة زمنية: يومية حتى 90 يوماً، وأسبوعية لنافذة السنة.
     *
     * @return list<array{date:string, gmv:float, orders:int, commission:float, new_customers:int}>
     */
    private function dailySeries(Carbon $start, Carbon $end, int $days): array
    {
        $weekly = $days > 90;
        $bucketOf = fn (Carbon $date): string => $weekly ? $date->copy()->startOfWeek()->toDateString() : $date->toDateString();

        $sales = Order::whereIn('status', self::SALE_STATUSES)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(total) as gmv, COUNT(*) as orders')
            ->groupBy('day')
            ->get();
        $commission = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::SALE_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('DATE(orders.created_at) as day, SUM(order_items.commission_amount) as commission')
            ->groupBy('day')
            ->pluck('commission', 'day');
        $customers = User::customers()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $buckets = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $buckets[$bucketOf($cursor)] ??= ['date' => $bucketOf($cursor), 'gmv' => 0, 'orders' => 0, 'commission' => 0, 'new_customers' => 0];
            $cursor->addDay();
        }
        foreach ($sales as $row) {
            $key = $bucketOf(Carbon::parse($row->day));
            $buckets[$key]['gmv'] += (int) $row->gmv;
            $buckets[$key]['orders'] += (int) $row->orders;
        }
        foreach ($commission as $day => $amount) {
            $buckets[$bucketOf(Carbon::parse($day))]['commission'] += (int) $amount;
        }
        foreach ($customers as $day => $count) {
            $buckets[$bucketOf(Carbon::parse($day))]['new_customers'] += (int) $count;
        }

        return array_values(array_map(fn (array $b) => [
            'date' => $b['date'],
            'gmv' => $b['gmv'] / 100,
            'orders' => $b['orders'],
            'commission' => $b['commission'] / 100,
            'new_customers' => $b['new_customers'],
        ], $buckets));
    }

    /** @return list<array{status:string, label:string, count:int}> */
    private function orderStatus(Carbon $start, Carbon $end): array
    {
        $counts = Order::whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $rows = [];
        foreach (Order::STATUS_LABELS as $status => $label) {
            $rows[] = ['status' => $status, 'label' => __($label), 'count' => (int) ($counts[$status] ?? 0)];
        }

        return $rows;
    }

    /** @return list<array{method:string, label:string, count:int, amount:float}> */
    private function paymentMethods(Carbon $start, Carbon $end): array
    {
        $labels = ['cash_on_delivery' => 'الدفع عند الاستلام', 'card' => 'بطاقة', 'wallet' => 'محفظة إلكترونية'];
        $rows = DB::table('payments')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->select('method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as amount'))
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        $result = [];
        foreach ($labels as $method => $label) {
            $row = $rows->get($method);
            $result[] = [
                'method' => $method,
                'label' => __($label),
                'count' => (int) ($row->count ?? 0),
                'amount' => ((int) ($row->amount ?? 0)) / 100,
            ];
        }

        return $result;
    }

    /** @return list<array{id:int, name:string, sales:float, orders:int, commission:float}> */
    private function topSellers(Carbon $start, Carbon $end): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('seller_profiles', 'seller_profiles.id', '=', 'order_items.vendor_id')
            ->whereIn('orders.status', self::SALE_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('seller_profiles.id', 'seller_profiles.store_name')
            ->orderByDesc(DB::raw('SUM(order_items.subtotal)'))
            ->limit(5)
            ->get([
                'seller_profiles.id',
                'seller_profiles.store_name',
                DB::raw('SUM(order_items.subtotal) as sales'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders'),
                DB::raw('SUM(order_items.commission_amount) as commission'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->store_name,
                'sales' => ((int) $row->sales) / 100,
                'orders' => (int) $row->orders,
                'commission' => ((int) $row->commission) / 100,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{product_id:int, name:string, sold_count:int, revenue:float}> */
    private function topProducts(Carbon $start, Carbon $end): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::SALE_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit(5)
            ->get([
                'order_items.product_id',
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as sold_count'),
                DB::raw('SUM(order_items.subtotal) as revenue'),
            ])
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->product_name,
                'sold_count' => (int) $row->sold_count,
                'revenue' => ((int) $row->revenue) / 100,
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id:int, name:string, revenue:float, sold_count:int}> */
    private function topCategories(Carbon $start, Carbon $end): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('orders.status', self::SALE_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(order_items.subtotal)'))
            ->limit(6)
            ->get([
                'categories.id',
                'categories.name',
                DB::raw('SUM(order_items.subtotal) as revenue'),
                DB::raw('SUM(order_items.quantity) as sold_count'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'revenue' => ((int) $row->revenue) / 100,
                'sold_count' => (int) $row->sold_count,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    private function users(Carbon $start, Carbon $end): array
    {
        $buyers = Order::whereIn('status', self::SALE_STATUSES)
            ->whereBetween('created_at', [$start, $end])
            ->select('user_id', DB::raw('COUNT(*) as orders'))
            ->groupBy('user_id')
            ->pluck('orders');

        return [
            'customers' => User::customers()->count(),
            'sellers' => SellerProfile::where('status', 'approved')->count(),
            'pending_sellers' => SellerProfile::where('status', 'pending')->count(),
            'suspended_sellers' => SellerProfile::where('status', 'suspended')->count(),
            'new_sellers' => SellerProfile::whereBetween('created_at', [$start, $end])->count(),
            'active_buyers' => $buyers->count(),
            'repeat_buyers' => $buyers->filter(fn ($orders) => (int) $orders > 1)->count(),
            'active_sellers' => (int) OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.status', self::SALE_STATUSES)
                ->whereBetween('orders.created_at', [$start, $end])
                ->distinct()
                ->count('order_items.vendor_id'),
        ];
    }

    /** @return array<string, int> */
    private function catalog(): array
    {
        $active = Product::where('status', 'active')->whereNotNull('admin_approved_at');

        return [
            'active_products' => $active->clone()->count(),
            'pending_review' => Product::pendingReview()->count(),
            'out_of_stock' => $active->clone()->where('stock_quantity', 0)->count(),
            'low_stock' => $active->clone()->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
            'categories' => (int) DB::table('categories')->where('is_active', true)->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function returns(Carbon $start, Carbon $end): array
    {
        $requests = ReturnRequest::whereBetween('created_at', [$start, $end]);
        $byStatus = $requests->clone()->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status');
        $byReason = $requests->clone()->select('reason', DB::raw('COUNT(*) as count'))->groupBy('reason')->pluck('count', 'reason');

        $soldQty = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::SALE_STATUSES)
            ->whereBetween('orders.created_at', [$start, $end])
            ->sum('order_items.quantity');
        $returnedQty = (int) DB::table('return_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_items.return_request_id')
            ->whereBetween('return_requests.created_at', [$start, $end])
            ->where('return_requests.status', '!=', 'closed')
            ->sum('return_items.quantity');

        return [
            'total' => (int) $byStatus->sum(),
            'needs_action' => ReturnRequest::whereIn('status', ['escalated', 'received'])->count(),
            'return_rate' => $soldQty > 0 ? round($returnedQty / $soldQty * 100, 1) : 0,
            'by_status' => collect(ReturnRequest::STATUS_LABELS)
                ->map(fn ($label, $status) => ['status' => $status, 'label' => __($label), 'count' => (int) ($byStatus[$status] ?? 0)])
                ->values()
                ->all(),
            'by_reason' => collect(ReturnRequest::REASON_LABELS)
                ->map(fn ($label, $reason) => ['reason' => $reason, 'label' => __($label), 'count' => (int) ($byReason[$reason] ?? 0)])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, float|int> */
    private function finance(Carbon $start, Carbon $end): array
    {
        $pending = DB::table('withdrawal_requests')->whereIn('status', ['pending', 'approved', 'processing']);

        return [
            'wallets_balance' => ((int) DB::table('wallets')->sum('balance')) / 100,
            'pending_withdrawals' => (int) $pending->clone()->count(),
            'pending_withdrawals_amount' => ((int) $pending->clone()->sum('amount')) / 100,
            'paid_withdrawals_amount' => ((int) DB::table('withdrawal_requests')->where('status', 'paid')->whereBetween('paid_at', [$start, $end])->sum('amount')) / 100,
            'settled_commission' => ((int) OrderItem::whereBetween('commission_settled_at', [$start, $end])->sum('commission_amount')) / 100,
            'showcase_revenue' => (float) DB::table('showcases')->where('status', 'approved')->whereBetween('created_at', [$start, $end])->sum('amount'),
            'story_revenue' => ((int) DB::table('stories')->whereBetween('created_at', [$start, $end])->sum('amount_paid')) / 100,
        ];
    }

    /** @return array<string, float|int> */
    private function marketing(Carbon $start, Carbon $end): array
    {
        $redemptions = DB::table('coupon_redemptions')->where('status', 'applied')->whereBetween('created_at', [$start, $end]);
        $points = DB::table('loyalty_transactions')->whereBetween('created_at', [$start, $end]);

        return [
            'active_campaigns' => (int) DB::table('coupon_campaigns')->where('status', 'active')->count(),
            'coupon_redemptions' => (int) $redemptions->clone()->count(),
            'coupon_discount' => ((int) $redemptions->clone()->sum(DB::raw('discount_amount + shipping_discount'))) / 100,
            'platform_funded_discount' => ((int) $redemptions->clone()->where('funder', 'platform')->sum(DB::raw('discount_amount + shipping_discount'))) / 100,
            'points_earned' => (int) $points->clone()->where('type', 'earn')->sum('points'),
            'points_redeemed' => (int) abs((int) $points->clone()->whereIn('type', ['redeem_checkout', 'redeem_coupon'])->sum('points')),
            'store_followers' => (int) DB::table('store_followers')->whereBetween('created_at', [$start, $end])->count(),
            'favorites' => (int) DB::table('favorites')->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /** @return array<string, int> */
    private function support(): array
    {
        return [
            'open_seller_tickets' => (int) DB::table('staff_conversations')->where('status', 'open')->count(),
            'escalated_chats' => (int) DB::table('chat_conversations')->where('status', 'escalated')->count(),
            'unanswered_questions' => (int) DB::table('product_questions')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('product_question_answers')->whereColumn('product_question_answers.product_question_id', 'product_questions.id'))
                ->count(),
        ];
    }
}
