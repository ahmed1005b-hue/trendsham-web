<?php

namespace App\Assistant\Seller;

use App\Assistant\AssistantReply;
use App\Assistant\Cards;
use App\Assistant\Nlp\Period;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Services\PayoutService;
use App\Services\SellerOrderService;

/** أرقام متجر البائع — كل استعلام محصور بـ vendor_id الخاص به */
class StoreInsights
{
    public const CHIPS = ['مبيعاتي هالشهر', 'الطلبات اللي بانتظاري', 'المنتجات اللي قربت تخلص', 'نصائح لزيد مبيعاتي'];

    public function __construct(
        private readonly SellerOrderService $orders,
        private readonly PayoutService $payouts,
    ) {}

    public function sales(SellerProfile $vendor, Period $period): AssistantReply
    {
        // تجميع داخل قاعدة البيانات: لا نحمّل سطور سنة كاملة إلى الذاكرة
        $base = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.vendor_id', $vendor->id)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween('orders.created_at', [$period->from, $period->to]);

        // نفس OrderItem::settlementBase(): الخصم يُطرح فقط إذا موّله البائع
        $net = "(order_items.subtotal - CASE WHEN order_items.discount_funder = 'seller' THEN COALESCE(order_items.discount_amount, 0) ELSE 0 END)";
        $row = (clone $base)->selectRaw("COALESCE(SUM({$net}), 0) AS total,
                COALESCE(SUM(CASE WHEN orders.status = 'delivered' THEN {$net} ELSE 0 END), 0) AS delivered,
                COUNT(DISTINCT order_items.order_id) AS orders_count,
                COALESCE(SUM(order_items.quantity), 0) AS units")->toBase()->first();

        if ((int) $row->orders_count === 0) {
            return new AssistantReply("ما في مبيعات {$period->label} لسا. شو رأيك تشوف نصائح لزيادة مبيعاتك؟", 'sales', chips: ['نصائح لزيد مبيعاتي', 'مبيعاتي هالسنة']);
        }

        $total = (int) $row->total;
        $delivered = (int) $row->delivered;
        $top = (clone $base)->selectRaw('order_items.product_name, SUM(order_items.quantity) AS qty')
            ->groupBy('order_items.product_name')->orderByDesc('qty')->toBase()->first();

        $text = "مبيعاتك {$period->label}: ".$this->money($total).' من '.(int) $row->orders_count.' طلب ('.(int) $row->units.' قطعة).';
        if ($delivered > 0 && $delivered < $total) {
            $text .= "\nمنها ".$this->money($delivered).' بطلبات تم تسليمها، والباقي بطلبات لسا عم تتجهز أو بالطريق.';
        }
        $text .= "\n🏆 الأكثر مبيعاً: {$top->product_name} (".(int) $top->qty.' قطعة).';
        $text .= "\n\nالمبالغ قبل عمولة المنصة.";

        return new AssistantReply($text, 'sales', chips: ['مبيعات اليوم', 'مبيعات هالاسبوع', 'الطلبات اللي بانتظاري']);
    }

    public function pendingOrders(SellerProfile $vendor): AssistantReply
    {
        $waiting = $this->orders->query($vendor)->whereIn('status', ['pending', 'paid', 'processing']);
        $count = (clone $waiting)->count();
        $orders = $waiting->with(['items' => fn ($q) => $q->where('vendor_id', $vendor->id)])
            ->oldest()->limit(8)->get();

        $link = Cards::link('صفحة طلباتي', 'seller_orders', route('seller.orders.index'));

        if ($count === 0) {
            return new AssistantReply('ما في طلبات بانتظارك هلق 👌 كل شي ماشي.', 'pending_orders', cards: [$link], chips: ['مبيعاتي هالشهر']);
        }

        // المبالغ لبضاعة هذا البائع فقط حتى بالطلبات المشتركة مع متاجر أخرى
        $lines = $orders->map(fn (Order $o) => "• {$o->order_number} — {$o->statusLabel()} — "
            .$this->money($o->items->sum(fn ($i) => $i->settlementBase())).' — '.$o->created_at?->format('Y-m-d'))->implode("\n");

        $head = "عندك {$count} ".($count === 1 ? 'طلب' : 'طلبات').' بانتظارك'
            .($count > $orders->count() ? "، هي أقدم {$orders->count()}" : ' (الأقدم أولاً)');

        return new AssistantReply("{$head}:\n{$lines}", 'pending_orders', cards: [$link], chips: ['المنتجات اللي قربت تخلص']);
    }

    public function lowStock(SellerProfile $vendor): AssistantReply
    {
        $products = Product::where('vendor_id', $vendor->id)->where('status', 'active')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')->limit(10)->get(['id', 'name', 'stock_quantity']);

        $variants = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.vendor_id', $vendor->id)->where('products.status', 'active')->whereNull('products.deleted_at')
            ->where('product_variants.is_active', true)
            ->whereColumn('product_variants.stock_quantity', '<=', 'products.low_stock_threshold')
            ->orderBy('product_variants.stock_quantity')->limit(10)
            ->get(['product_variants.*', 'products.name as product_name']);

        $link = Cards::link('منتجاتي', 'seller_products', route('seller.products.index'));

        if ($products->isEmpty() && $variants->isEmpty()) {
            return new AssistantReply('مخزونك منيح، ما في منتج قرّب يخلص 👍', 'low_stock', cards: [$link]);
        }

        $lines = $products->map(fn ($p) => "• {$p->name} — ".($p->stock_quantity > 0 ? "باقي {$p->stock_quantity}" : 'نفد ❗'))
            ->merge($variants->map(fn ($v) => "• {$v->product_name} (".implode(' / ', array_filter([$v->color_name, $v->size])).') — '
                .($v->stock_quantity > 0 ? "باقي {$v->stock_quantity}" : 'نفد ❗')))
            ->implode("\n");

        return new AssistantReply("هدول قربوا يخلصوا، زوّد كمياتهن لحتى ما تخسر طلبات:\n{$lines}", 'low_stock', cards: [$link]);
    }

    public function wallet(SellerProfile $vendor): AssistantReply
    {
        $balance = (int) $vendor->walletOrCreate()->balance;
        $available = $this->payouts->available($vendor);   // نفس رقم صفحة المحفظة

        $text = 'المتاح للسحب: '.$this->money($available).'.';
        if ($balance > $available) {
            $text .= "\nرصيدك الكلي ".$this->money($balance).'، منه '.$this->money($balance - $available).' محجوز بطلبات سحب قيد المراجعة.';
        }
        $text .= "\nالأرباح بتنضاف للمحفظة بعد تسليم الطلب للعميل.";

        return new AssistantReply($text, 'wallet', cards: [Cards::link('محفظتي', 'seller_wallet', route('seller.wallet.index'))]);
    }

    public function tips(SellerProfile $vendor): AssistantReply
    {
        $products = Product::where('vendor_id', $vendor->id)->whereIn('status', ['active', 'draft'])
            ->withCount('images')->latest('id')->limit(100)->get();

        $notes = $products->map(function (Product $p) {
            $issues = [];
            if ($p->rejection_note && ! $p->admin_approved_at) {
                $issues[] = 'رفضته الإدارة: '.$p->rejection_note;
            }
            if ($p->images_count < 3) {
                $issues[] = "صور قليلة ({$p->images_count}) — المنتجات بـ 3 صور أو أكتر بتنباع أكتر";
            }
            if (mb_strlen(trim(strip_tags((string) $p->description))) < 60) {
                $issues[] = 'الوصف قصير — اكتبلي "اكتب وصف لمنتج '.$p->name.'"';
            }
            if ($p->status === 'active' && (int) $p->stock_quantity === 0) {
                $issues[] = 'نافد من المخزون';
            }

            return $issues ? "• {$p->name}: ".implode('، ', $issues) : null;
        })->filter()->take(8);

        if ($notes->isEmpty()) {
            return new AssistantReply('منتجاتك بحالة ممتازة 👏 استمر بإضافة منتجات جديدة وعروض اليوم لتزيد ظهورك.', 'tips');
        }

        return new AssistantReply(
            "هي أهم الأشياء اللي بتزيد مبيعاتك:\n{$notes->implode("\n")}",
            'tips', cards: [Cards::link('منتجاتي', 'seller_products', route('seller.products.index'))],
        );
    }

    private function money(int|float $minor): string
    {
        return number_format($minor / 100).' ل.س';
    }
}
