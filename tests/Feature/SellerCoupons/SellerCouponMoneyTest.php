<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\Models\CouponRedemption;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US2: كوبون البائع يُخصم من أرباحه لا من المنصة (أرقام وثيقة التصميم) */
class SellerCouponMoneyTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    private function walletBalance($seller): int
    {
        return (int) ($seller->wallet()->first()?->balance ?? 0);
    }

    public function test_design_example_100k_product_20k_seller_coupon(): void
    {
        $seller = $this->seller(commission: 10);
        $p = $this->product($seller, 10_000_000);
        $u = $this->customer();
        $coupon = $this->walletCoupon($this->campaign($seller), $u);
        $this->cartLine($u, $p);

        $order = $this->checkout($u);

        $this->assertSame(2_000_000, $order->discount_amount);
        $this->assertSame(8_000_000 + $this->shipping, $order->total);
        $this->assertSame('seller', $order->items->first()->discount_funder);

        $this->deliver($order);
        $this->assertSame(7_200_000, $this->walletBalance($seller));
        $this->assertSame(800_000, (int) $order->items->first()->fresh()->commission_amount);

        // إرجاع: الأرباح تُعكس والكوبون يعود للمحفظة
        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(0, $this->walletBalance($seller));
        $this->assertSame('available', $coupon->fresh()->status);
    }

    public function test_discount_applies_only_to_sellers_items_in_mixed_cart(): void
    {
        $seller = $this->seller();
        $mine = $this->product($seller, 5_000_000);
        $other = $this->product($this->seller(), 5_000_000);
        $u = $this->customer();
        $this->walletCoupon($this->campaign($seller, ['type' => 'percentage', 'value' => 50, 'max_discount' => 10_000_000]), $u);
        $this->cartLine($u, $mine);
        $this->cartLine($u, $other);

        $order = $this->checkout($u);

        $this->assertSame(2_500_000, $order->discount_amount);
        $items = $order->items->keyBy('product_id');
        $this->assertSame(2_500_000, (int) $items[$mine->id]->discount_amount);
        $this->assertSame('seller', $items[$mine->id]->discount_funder);
        $this->assertSame(0, (int) $items[$other->id]->discount_amount);
    }

    public function test_loyalty_points_with_seller_coupon_do_not_touch_seller_earnings(): void
    {
        $seller = $this->seller(commission: 10);
        $p = $this->product($seller, 10_000_000);
        $u = $this->customer();
        app(LoyaltyLedger::class)->credit($u->id, LoyaltyTransaction::ADMIN_ADD, 20, reason: 'اختبار');
        $this->walletCoupon($this->campaign($seller), $u);
        $this->cartLine($u, $p);

        $this->actingAs($u)->post(route('customer.checkout.store'), [
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
            'payment_method' => 'cash_on_delivery', 'use_points' => 20,
        ]);
        $order = $u->orders()->latest('id')->first();

        $this->assertSame(400_000, (int) $order->points_discount);
        $this->deliver($order);
        $this->assertSame(7_200_000, $this->walletBalance($seller), 'البائع يتحمّل كوبونه فقط، والمنصة تتحمّل النقاط');
    }

    public function test_seller_free_shipping_needs_whole_cart_from_store(): void
    {
        $seller = $this->seller();
        $mine = $this->product($seller, 5_000_000);
        $other = $this->product($this->seller(), 5_000_000);
        $u = $this->customer();
        $this->walletCoupon($this->campaign($seller, ['type' => 'free_shipping', 'value' => 0]), $u);
        $this->cartLine($u, $mine);
        $this->cartLine($u, $other);

        $res = app(\App\Coupons\CouponSelector::class)->best($u, collect([
            (object) ['product' => $mine, 'product_id' => $mine->id, 'subtotal' => 5_000_000],
            (object) ['product' => $other, 'product_id' => $other->id, 'subtotal' => 5_000_000],
        ]), $this->shipping);

        $this->assertNull($res['chosen']);
        $this->assertStringContainsString('كل السلة من '.$seller->store_name, collect($res['unusable'] ?? [])->pluck('reason')->implode(' | ').json_encode($res, JSON_UNESCAPED_UNICODE));

        $order = $this->checkout($u);
        $this->assertSame($this->shipping, (int) $order->shipping_fee);
    }

    public function test_seller_free_shipping_is_charged_to_seller_once_and_refunded(): void
    {
        $seller = $this->seller(commission: 10);
        $p = $this->product($seller, 10_000_000);
        $u = $this->customer();
        $this->walletCoupon($this->campaign($seller, ['type' => 'free_shipping', 'value' => 0]), $u);
        $this->cartLine($u, $p);

        $order = $this->checkout($u);
        $this->assertSame(0, (int) $order->shipping_fee);
        $this->assertSame($this->shipping, (int) CouponRedemption::where('order_id', $order->id)->value('shipping_discount'));

        $this->deliver($order);
        $this->assertSame(9_000_000 - $this->shipping, $this->walletBalance($seller));

        // تسليم مكرر (رجوع ثم تسليم) لا يخصم الشحن مرتين
        $order->fresh()->changeStatus('shipped');
        $order->fresh()->changeStatus('delivered');
        $this->assertSame(9_000_000 - $this->shipping, $this->walletBalance($seller));

        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(0, $this->walletBalance($seller));
    }
}
