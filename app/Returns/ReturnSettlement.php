<?php

namespace App\Returns;

use App\Coupons\CouponRedeemer;
use App\Coupons\SellerShippingCharge;
use App\Loyalty\LoyaltyLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Returns\Models\ReturnItem;
use App\Returns\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

/**
 * أثر استلام المرتجع (مرة واحدة لكل سطر): إعادة الكمية للمخزون، عكس أرباح البائع وقيوده،
 * وسحب نقاط الولاء بنسبة المبلغ المسترد، وإضافة أجرة التوصيل إن اكتمل به إرجاع الطلب بخطأ البائع.
 * إذا اكتمل إرجاع كل وحدات الطلب يعود الكوبون
 * وأجرة شحن كوبون البائع والنقاط المستبدلة في الطلب تماماً كما في الإلغاء (القرار 6).
 */
class ReturnSettlement
{
    public function __construct(
        private readonly LoyaltyLedger $loyalty,
        private readonly CouponRedeemer $coupons,
        private readonly SellerShippingCharge $sellerShipping,
        private readonly RefundCalculator $calculator,
    ) {}

    public const WALLET_MARKER_PREFIX = '(return_item:';

    /** علامة خصم المحفظة الخاص بسطر مرتجع (آخر ملاحظة الحركة)؛ بها يميّز العكس الكامل خصومات المرتجعات عن خصمه هو */
    public static function walletMarker(int $returnItemId): string
    {
        return self::WALLET_MARKER_PREFIX.$returnItemId.')';
    }

    /**
     * يُستدعى داخل معاملة تأكيد الاستلام. حارس التكرار: settled_at على سطر مقفول،
     * ويُضبط في نفس المعاملة مع الأثر.
     *
     * ترتيب الأقفال مطابق لإلغاء الطلب (Order::settleForStatusChange) حتى لا يتعطل استلام وإلغاء
     * متزامنان على طلبين يشتركان في بائع أو منتج:
     * الطلب ← سطور المرتجع ← محفظة البائع ← المنتج ← اللون/المقاس ← الكوبون/أجرة شحن كوبون البائع ← حساب النقاط.
     * عند اكتمال إرجاع كل وحدات الطلب: يعود الكوبون وأجرة الشحن والنقاط المستبدلة فيه، كما في الإلغاء.
     *
     * @throws \DomainException إن أُلغي الطلب (الإلغاء عكس كل شيء مسبقاً)
     */
    public function apply(ReturnRequest $returnRequest): void
    {
        DB::transaction(function () use ($returnRequest) {
            // صف الطلب أولاً كما في الإلغاء: يسلسل الاستلام مع الإلغاء ومع استلام مرتجع آخر لنفس الطلب
            $order = Order::whereKey($returnRequest->order_id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') {
                throw new \DomainException('لا يمكن تأكيد استلام مرتجع لطلب ملغى.');
            }

            $pending = ReturnItem::where('return_request_id', $returnRequest->id)->orderBy('id')->lockForUpdate()->get()
                ->whereNull('settled_at')->sortBy('order_item_id')->values();
            if ($pending->isEmpty()) {
                return;
            }

            // أجرة التوصيل تُحمَّل على المرتجع الذي يُكمل الآن إرجاع كل وحدات الطلب بخطأ البائع (القسم 10.3)؛
            // تُحسب هنا تحت قفل الطلب لأن المرتجعات المستلمة/المستردة وحدها نهائية
            $returnRequest->setRelation('order', $order);
            $shipping = $this->calculator->shippingFor($returnRequest);
            if ($shipping > 0 && (int) $returnRequest->shipping_refund === 0) {
                $returnRequest->update([
                    'shipping_refund' => $shipping,
                    'refund_amount' => (int) $returnRequest->refund_amount + $shipping,
                ]);
            }

            $items = OrderItem::with(['product', 'vendor'])->whereIn('id', $pending->pluck('order_item_id'))->get()->keyBy('id');
            foreach ($pending as $line) {
                $line->setRelation('returnRequest', $returnRequest);
                $items[$line->order_item_id]->setRelation('order', $order);
            }

            // 1) المحفظة والدفتر لكل السطور (الإلغاء يعكس الأرباح قبل إرجاع المخزون)
            foreach ($pending as $line) {
                $items[$line->order_item_id]->reverseSettlementQuantity($line);
            }

            // 2) المخزون: المنتج ثم اللون/المقاس
            foreach ($pending as $line) {
                $this->restock($items[$line->order_item_id], (int) $line->quantity, "إرجاع #{$returnRequest->number}");
            }

            // 3) اكتمال إرجاع الطلب كله: الكوبون وأجرة شحن كوبون البائع
            $fullReturn = $this->everyUnitReversed($order, (int) $pending->sum('quantity'));
            if ($fullReturn) {
                $this->coupons->reverse($order);
                $this->sellerShipping->refund($order);
            }

            // 4) النقاط أخيراً: سحب نقاط كل سطر ثم تعليمه منفَّذاً، وإعادة النقاط المستبدلة عند الإرجاع الكامل
            foreach ($pending as $line) {
                $this->loyalty->reverseEarnForReturn($line);
                $line->update(['settled_at' => now()]);
            }
            if ($fullReturn) {
                $this->loyalty->restoreRedeemedForOrder($order);
            }
        });
    }

    /** نفس ترتيب القفل في الإلغاء والدفع: المنتج ثم اللون/المقاس */
    private function restock(OrderItem $item, int $quantity, string $note): void
    {
        $item->product?->adjustStock($quantity, 'return', $note);
        if ($item->variant_id) {
            ProductVariant::whereKey($item->variant_id)->increment('stock_quantity', $quantity);
        }
    }

    /** هل تكتمل بإرجاع $pendingQuantity الآن (فوق المنفَّذ سابقاً) كل وحدات الطلب؟ */
    private function everyUnitReversed(Order $order, int $pendingQuantity): bool
    {
        $items = OrderItem::where('order_id', $order->id)->get(['id', 'quantity']);
        $units = (int) $items->sum('quantity');
        $reversed = (int) ReturnItem::whereIn('order_item_id', $items->pluck('id'))->whereNotNull('settled_at')->sum('quantity');

        return $units > 0 && $reversed + $pendingQuantity >= $units;
    }
}
