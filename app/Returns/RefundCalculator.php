<?php

namespace App\Returns;

use App\Models\OrderItem;
use App\Returns\Models\ReturnItem;
use App\Returns\Models\ReturnRequest;

/**
 * حساب مبالغ الإرجاع بالوحدة الصغرى (×100): ما يستعيده الزبون، وما يُعكس على البائع.
 * الوحدات الأخيرة من البند تأخذ الباقي ليكون مجموع عدة طلبات جزئية مطابقًا لقيمة البند كاملة.
 */
class RefundCalculator
{
    /**
     * @return array{refund: int, seller_reversal: int, commission_reversal: int}
     */
    public function forLine(OrderItem $item, int $quantity, ?int $excludeReturnItemId = null): array
    {
        if ($item->commission_settled_at === null) {
            throw new \DomainException('لا يمكن إرجاع بند لم تتم تسويته بعد.');
        }
        if ($quantity < 1 || $quantity > (int) $item->quantity) {
            throw new \DomainException('كمية الإرجاع غير صالحة.');
        }

        $full = [
            'refund' => $this->fullRefund($item),
            'seller_reversal' => $item->settlementBase(),
            'commission_reversal' => (int) $item->commission_amount,
        ];

        $others = ReturnItem::query()
            ->where('order_item_id', $item->id)
            ->when($excludeReturnItemId, fn ($q) => $q->where('id', '!=', $excludeReturnItemId))
            ->whereHas('returnRequest', fn ($q) => $q->where('status', '!=', 'closed'))
            ->get(['quantity', 'refund_amount', 'seller_reversal', 'commission_reversal']);

        if ((int) $others->sum('quantity') + $quantity === (int) $item->quantity) {
            return [
                'refund' => $full['refund'] - (int) $others->sum('refund_amount'),
                'seller_reversal' => $full['seller_reversal'] - (int) $others->sum('seller_reversal'),
                'commission_reversal' => $full['commission_reversal'] - (int) $others->sum('commission_reversal'),
            ];
        }

        return [
            'refund' => intdiv($full['refund'] * $quantity, (int) $item->quantity),
            'seller_reversal' => intdiv($full['seller_reversal'] * $quantity, (int) $item->quantity),
            'commission_reversal' => intdiv($full['commission_reversal'] * $quantity, (int) $item->quantity),
        ];
    }

    /**
     * أجرة التوصيل التي يحملها $request إن اكتمل به الآن إرجاع كل وحدات الطلب (القسم 5 و10.3):
     * طلب من متجر واحد، وكل وحداته مُرجعة بسبب خطأ البائع في مرتجعات مستلمة/مستردة إضافة إلى $request نفسه،
     * ولم يُسترد شحن الطلب سابقاً (تلقائياً أو إضافة من المدير).
     *
     * تُحسب عند الاستلام تحت قفل صف الطلب (ReturnSettlement::apply)، فالمرتجعات المفتوحة لا تُحتسب.
     */
    public function shippingFor(ReturnRequest $request): int
    {
        if (! in_array($request->reason, ReturnRequest::SELLER_FAULT, true)) {
            return 0;
        }

        $order = $request->order;
        $items = $order->items()->get(['id', 'vendor_id', 'quantity']);
        if ($items->pluck('vendor_id')->unique()->count() !== 1 || $this->orderShippingRefunded($order->id)) {
            return 0;
        }

        $covered = ReturnItem::query()
            ->whereIn('order_item_id', $items->pluck('id'))
            ->whereHas('returnRequest', fn ($q) => $q->where(function ($w) use ($request) {
                $w->where(fn ($settled) => $settled->whereIn('status', ReturnRequest::SETTLED)->whereIn('reason', ReturnRequest::SELLER_FAULT))
                    ->orWhere('id', $request->id);
            }))
            ->sum('quantity');

        return (int) $covered >= (int) $items->sum('quantity') ? (int) $order->shipping_fee : 0;
    }

    /**
     * هل استُرد شحن هذا الطلب سابقاً عبر أحد مرتجعاته المستلمة/المستردة: تلقائياً (shipping_refund)
     * أو إضافةً من المدير (مبلغ الاسترداد المسجَّل أكبر من المستحق). المرتجعات المفتوحة أو المغلقة لا تُحتسب.
     */
    public function orderShippingRefunded(int $orderId): bool
    {
        return ReturnRequest::where('order_id', $orderId)
            ->whereIn('status', ReturnRequest::SETTLED)
            ->with('refund')
            ->get()
            ->contains(fn (ReturnRequest $r) => $r->shipping_refund > 0
                || ($r->refund !== null && $r->refund->amount > $r->refund_amount));
    }

    /** ما دفعه الزبون فعلًا عن البند كاملًا: قيمة البند ناقص حصته من خصم الطلب والنقاط. */
    private function fullRefund(OrderItem $item): int
    {
        $order = $item->order;
        $lines = $order->items()->orderBy('id')->get(['id', 'subtotal', 'discount_amount']);

        $values = $lines->mapWithKeys(fn ($l) => [$l->id => max(0, (int) $l->subtotal - (int) $l->discount_amount)]);
        $orderLevel = max(0, (int) $order->discount_amount - (int) $lines->sum('discount_amount'))
            + (int) $order->points_discount;
        $totalValue = (int) $values->sum();

        $shares = [];
        if ($orderLevel > 0 && $totalValue > 0) {
            foreach ($values as $id => $value) {
                $shares[$id] = intdiv($orderLevel * $value, $totalValue);
            }
            $largest = $values->sortDesc()->keys()->first();
            $shares[$largest] += $orderLevel - array_sum($shares);
        }

        return max(0, $values[$item->id] - ($shares[$item->id] ?? 0));
    }
}
