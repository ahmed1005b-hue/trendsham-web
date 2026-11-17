<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\DailyDeal;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Collection;

/**
 * يحجز من كمية «عرض اليوم» داخل معاملة الشراء. صف العرض مقفل، فلا يأخذ
 * مشتريان متزامنان آخر القطع معاً ولا تُباع أكثر من الكمية المحددة.
 * يُستدعى داخل DB::transaction بعد قفل المنتجات. إلغاء الطلب يعيد الكمية (release).
 */
class DailyDealQuota
{
    public const SOLD_OUT = 'deal_sold_out';

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, int> رقم بند السلة ← رقم العرض الذي حُجز منه
     */
    public function claim(Collection $items): array
    {
        $claims = [];
        $byItem = [];
        foreach ($items as $item) {
            // البند بسعر العرض فقط إن كان أرخص من السعر الأصلي المحفوظ في السلة
            if ($item->original_unit_price === null || (int) $item->unit_price >= (int) $item->original_unit_price) {
                continue;
            }
            $deal = DailyDeal::where('product_id', $item->product_id)->active()->lockForUpdate()->first();
            if (! $deal) {
                continue;
            }
            $claims[$deal->id] ??= ['deal' => $deal, 'quantity' => 0];
            $claims[$deal->id]['quantity'] += (int) $item->quantity;
            $byItem[$item->id] = $deal->id;
        }

        foreach ($claims as ['deal' => $deal, 'quantity' => $quantity]) {
            if ($deal->max_quantity > 0 && $deal->sold_quantity + $quantity > $deal->max_quantity) {
                throw new DomainException(self::SOLD_OUT);
            }
            $deal->increment('sold_quantity', $quantity);
        }

        return $byItem;
    }

    /** أول إلغاء للطلب: تعود قطع العرض المحجوزة إلى كميته (يُستدعى داخل معاملة تغيير الحالة). */
    public function release(Order $order): void
    {
        $order->items()->whereNotNull('daily_deal_id')->get(['daily_deal_id', 'quantity'])
            ->groupBy('daily_deal_id')
            ->each(function ($lines, $dealId) {
                $deal = DailyDeal::whereKey($dealId)->lockForUpdate()->first();
                if ($deal) {
                    $deal->update(['sold_quantity' => max(0, $deal->sold_quantity - (int) $lines->sum('quantity'))]);
                }
            });
    }
}
