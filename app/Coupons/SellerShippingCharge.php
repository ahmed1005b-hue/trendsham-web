<?php

namespace App\Coupons;

use App\Accounting\LedgerType;
use App\Coupons\Models\CouponRedemption;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * الشحن المجاني من كوبون البائع: أجرة الشحن تذهب للمنصة عادةً، فحين يلغيها كوبون البائع
 * يتحمّلها البائع. تُخصم من محفظته عند أول تسليم وتعود عند الإلغاء — مرة واحدة لكلٍّ منهما.
 */
class SellerShippingCharge
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function charge(Order $order): void
    {
        $this->each($order, function (CouponRedemption $r, SellerProfile $seller) use ($order) {
            if ($this->posted($seller, LedgerType::COUPON_SHIPPING, $r->id)) {
                return;
            }
            $seller->walletOrCreate()->applyTransaction('debit', (int) $r->shipping_discount, 'adjustment', [
                'note' => "شحن مجاني من كوبونك: طلب #{$order->order_number}",
            ]);
            $this->ledger->couponShipping($seller->user_id, $r->shipping_discount / 100, "شحن مجاني من كوبون: طلب #{$order->order_number}", $r->id);
        });
    }

    public function refund(Order $order): void
    {
        $this->each($order, function (CouponRedemption $r, SellerProfile $seller) use ($order) {
            if (! $this->posted($seller, LedgerType::COUPON_SHIPPING, $r->id)
                || $this->posted($seller, LedgerType::COUPON_SHIPPING_REVERSAL, $r->id)) {
                return;
            }
            $seller->walletOrCreate()->applyTransaction('credit', (int) $r->shipping_discount, 'adjustment', [
                'note' => "إرجاع شحن كوبون: طلب #{$order->order_number}",
            ]);
            $this->ledger->couponShippingReversal($seller->user_id, $r->shipping_discount / 100, "إرجاع شحن كوبون: طلب #{$order->order_number}", $r->id);
        });
    }

    private function each(Order $order, callable $fn): void
    {
        // لا نشترط status=applied: الإلغاء يعكس الاستخدام قبل أن نعيد أجرة الشحن
        CouponRedemption::where('order_id', $order->id)->where('funder', 'seller')->where('shipping_discount', '>', 0)
            ->get()
            ->each(function (CouponRedemption $r) use ($fn) {
                $seller = SellerProfile::find($r->seller_profile_id);
                if ($seller) {
                    DB::transaction(fn () => $fn($r, $seller));
                }
            });
    }

    private function posted(SellerProfile $seller, string $type, int $redemptionId): bool
    {
        return LedgerEntry::forSeller($seller->user_id)->where('entry_type', $type)
            ->where('source_type', 'coupon_redemption')->where('source_id', $redemptionId)->exists();
    }
}
