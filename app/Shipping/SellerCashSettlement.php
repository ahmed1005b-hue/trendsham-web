<?php

namespace App\Shipping;

use App\Accounting\LedgerType;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Services\LedgerService;

/**
 * طرد سلّمه المتجر بنفسه (توصيل خاص أو استلام من محله):
 *  - أجرة الشحن الكاملة للبائع لأنه هو من وصّل (كوبون شحن البائع يُخصم منه كالمعتاد).
 *  - ما حصّله نقداً من الزبون يُخصم من محفظته، لأن المال صار في يده لا في يد المنصة.
 * الأرباح تُضاف كالمعتاد (المبيعات ناقص العمولة)، فالنتيجة: إن حصّل البائع كل شيء نقداً
 * يبقى عليه عمولة المنصة فقط، وتصبح ديناً إن لم يكن في محفظته رصيد يكفيها.
 * المبالغ بالوحدة الصغرى ×100. كل قيد مرة واحدة (cash_settled_at + مصدر القيد).
 */
class SellerCashSettlement
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function settle(Order $order, Shipment $parcel): void
    {
        if ($parcel->delivery_mode === 'platform' || $parcel->cash_settled_at) {
            return;
        }
        $seller = SellerProfile::find($parcel->seller_profile_id);
        if (! $seller) {
            return;
        }
        $wallet = $seller->walletOrCreate();

        if ((int) $parcel->shipping_cost > 0) {
            $wallet->applyTransaction('credit', (int) $parcel->shipping_cost, 'adjustment', [
                'note' => "أجرة توصيل طردك: طلب #{$order->order_number}",
            ]);
            $this->ledger->post($seller->user_id, LedgerType::SELF_SHIPPING, 0, $parcel->shipping_cost / 100,
                "أجرة توصيل: طلب #{$order->order_number}", 'shipment', $parcel->id);
        }
        if ((int) $parcel->cod_amount > 0) {
            $wallet->applyTransaction('debit', (int) $parcel->cod_amount, 'adjustment', [
                'note' => "مبلغ حصّلته نقداً: طلب #{$order->order_number}",
            ]);
            $this->ledger->post($seller->user_id, LedgerType::CASH_COLLECTED, $parcel->cod_amount / 100, 0,
                "تحصيل نقدي: طلب #{$order->order_number}", 'shipment', $parcel->id);
        }

        $parcel->forceFill(['cash_settled_at' => now()])->save();
    }

    /** إلغاء طرد بعد تسليمه: يُعكس ما سُجّل له. */
    public function reverse(Order $order, Shipment $parcel): void
    {
        if (! $parcel->cash_settled_at) {
            return;
        }
        $seller = SellerProfile::find($parcel->seller_profile_id);
        if (! $seller) {
            return;
        }
        $wallet = $seller->walletOrCreate();

        if ((int) $parcel->shipping_cost > 0) {
            $wallet->applyTransaction('debit', (int) $parcel->shipping_cost, 'adjustment', [
                'note' => "إلغاء أجرة توصيل: طلب #{$order->order_number}",
            ]);
            $this->ledger->post($seller->user_id, LedgerType::SELF_SHIPPING_REVERSAL, $parcel->shipping_cost / 100, 0,
                "إلغاء أجرة توصيل: طلب #{$order->order_number}", 'shipment', $parcel->id);
        }
        if ((int) $parcel->cod_amount > 0) {
            $wallet->applyTransaction('credit', (int) $parcel->cod_amount, 'adjustment', [
                'note' => "إلغاء مبلغ محصّل: طلب #{$order->order_number}",
            ]);
            $this->ledger->post($seller->user_id, LedgerType::CASH_COLLECTED_REVERSAL, 0, $parcel->cod_amount / 100,
                "إلغاء تحصيل نقدي: طلب #{$order->order_number}", 'shipment', $parcel->id);
        }

        $parcel->forceFill(['cash_settled_at' => null])->save();
    }
}
