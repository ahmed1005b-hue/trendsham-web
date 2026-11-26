<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مؤشرات بيانية للبائع: مبيعات، دخل صافٍ، حالات الطلبات، رصيد المحفظة.
 * يستخدمه الويب والتطبيق (API) — نفس الأرقام في المكانين.
 * كل المبالغ تُعاد بالوحدة الكبرى (ل.س) جاهزة للعرض.
 */
class SellerAnalyticsService
{
    /** @param  int  $days  نافذة التحليل الزمنية (افتراضي 30 يوماً) */
    public function summary(SellerProfile $vendor, int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        // نافذة سابقة بنفس الطول (لحساب التغيّر %)
        $prevStart = $start->copy()->subDays($days);
        $prevEnd = $start->copy()->subSecond();

        $window = fn (Carbon $from, Carbon $to) => OrderItem::query()->releasedToSellers()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendor->id)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('order_items.created_at', [$from, $to]);

        $current = $window($start, $end);
        $previous = $window($prevStart, $prevEnd);

        $sales = (int) $current->clone()->sum('order_items.subtotal');
        $commission = (int) $current->clone()->sum('order_items.commission_amount');
        $prevSales = (int) $previous->clone()->sum('order_items.subtotal');
        $prevCommission = (int) $previous->clone()->sum('order_items.commission_amount');

        return [
            'days' => $days,
            'totals' => [
                'sales' => $sales / 100,
                'net_profit' => ($sales - $commission) / 100,
                'commission' => $commission / 100,
                'orders' => (int) $current->clone()->distinct('order_id')->count('order_id'),
                'items_sold' => (int) $current->clone()->sum('order_items.quantity'),
                // متوسط قيمة الطلب
                'avg_order_value' => (function () use ($current) {
                    $orders = (int) $current->clone()->distinct('order_id')->count('order_id');

                    return $orders > 0 ? round(((int) $current->clone()->sum('order_items.subtotal')) / $orders / 100) : 0;
                })(),
            ],
            // التغيّر مقارنة بالنافذة السابقة (%)، null إن لم يكن للسابق مبيعات
            'changes' => [
                'sales' => $prevSales > 0 ? round((($sales - $prevSales) / $prevSales) * 100) : null,
                'net_profit' => $prevSales - $prevCommission > 0
                    ? round(((($sales - $commission) - ($prevSales - $prevCommission)) / ($prevSales - $prevCommission)) * 100)
                    : null,
            ],
            'series' => $this->dailySeries($vendor->id, $start, $end),
            'status_breakdown' => $this->statusBreakdown($vendor->id),
            'wallet' => $this->walletSeries($vendor),
            'top_products' => $this->topProducts($vendor->id, $start, $end),
        ];
    }

    /** سلسلة يومية: المبيعات وصافي الدخل لكل يوم. */
    private function dailySeries(int $vendorId, Carbon $start, Carbon $end): array
    {
        $rows = OrderItem::query()->releasedToSellers()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendorId)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('order_items.created_at', [$start, $end])
            ->selectRaw('DATE(order_items.created_at) as bucket,
                COALESCE(SUM(order_items.subtotal), 0) as sales,
                COALESCE(SUM(order_items.commission_amount), 0) as commission')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $series = [];
        $cursor = $start->copy()->startOfDay();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);
            $sales = $row ? (int) $row->sales : 0;
            $series[] = [
                'date' => $key,
                'sales' => $sales / 100,
                'net_profit' => $row ? ($sales - (int) $row->commission) / 100 : 0,
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /** توزيع حالات الطلبات (عدد الطلبات التي فيها منتجات البائع). */
    private function statusBreakdown(int $vendorId): array
    {
        $counts = Order::releasedToSellers()
            ->whereHas('items', fn ($q) => $q->where('order_items.vendor_id', $vendorId))
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $breakdown = [];
        foreach (Order::STATUS_LABELS as $status => $label) {
            $breakdown[] = [
                'status' => $status,
                'label' => __($label),
                'count' => (int) ($counts[$status] ?? 0),
            ];
        }

        return $breakdown;
    }

    /** رصيد المحفظة الآن + آخر 8 حركات (منحنى رصيد عبر الزمن). */
    private function walletSeries(SellerProfile $vendor): array
    {
        $wallet = $vendor->walletOrCreate();

        $transactions = $wallet->transactions()
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn ($t) => [
                'type' => $t->type,
                'amount' => $t->amount / 100,
                'balance_after' => $t->balance_after / 100,
                'reason' => $t->reason,
                'date' => optional($t->created_at)->format('Y-m-d H:i'),
            ])
            ->reverse()
            ->values();

        return [
            'balance' => $wallet->balance / 100,
            'transactions' => $transactions,
        ];
    }

    /** أفضل المنتجات داخل النافذة (بعدد المبيعات ثم الإيراد). */
    private function topProducts(int $vendorId, Carbon $start, Carbon $end): array
    {
        return OrderItem::query()->releasedToSellers()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendorId)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('order_items.created_at', [$start, $end])
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
}
