<?php

namespace Tests\Feature\Coupons;

use App\Coupons\Models\CouponRedemption;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** أرقام وثيقة التصميم: منتج 100,000 ل.س، عمولة 10%، خصم 20,000 ل.س. */
class CouponRedemptionTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    private const SHIPPING = 15000; // CheckoutController::SHIPPING_FEE

    private function checkout(User $customer, $product, $choice = null)
    {
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price]);

        $this->actingAs($customer)->withSession($choice === null ? [] : ['checkout_coupon' => $choice])
            ->post(route('customer.checkout.store'), [
                'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery',
            ]);

        return $customer->orders()->latest('id')->first();
    }

    private function deliver(Order $order): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.orders.updateStatus', $order), ['status' => 'delivered']);
    }

    public function test_platform_coupon_is_paid_by_platform_seller_gets_full_earnings(): void
    {
        $vendor = $this->vendor(10);
        $product = $this->product($vendor, 10_000_000);
        $customer = $this->customer();
        $this->walletCoupon($this->campaign(['value' => 2_000_000]), $customer);

        $order = $this->checkout($customer, $product); // بلا اختيار = الأفضل تلقائياً

        $this->assertSame(2_000_000, $order->discount_amount);
        $this->assertSame(8_000_000 + self::SHIPPING, $order->total);
        $this->assertSame('platform', $order->items->first()->discount_funder);
        $this->deliver($order);
        $this->assertSame(9_000_000, $vendor->wallet()->first()->balance);
    }

    public function test_seller_coupon_is_paid_by_seller_commission_on_discounted_price(): void
    {
        $vendor = $this->vendor(10);
        $product = $this->product($vendor, 10_000_000);
        $customer = $this->customer();
        $this->walletCoupon($this->campaign(['funder' => 'seller', 'seller_profile_id' => $vendor->id, 'audience' => 'collectable', 'value' => 2_000_000]), $customer);

        $order = $this->checkout($customer, $product);

        $this->assertSame('seller', $order->items->first()->discount_funder);
        $this->deliver($order);
        $this->assertSame(7_200_000, $vendor->wallet()->first()->balance);
    }

    public function test_free_shipping_zeroes_shipping_fee(): void
    {
        $product = $this->product($this->vendor(), 10_000_000);
        $customer = $this->customer();
        $this->walletCoupon($this->campaign(['type' => 'free_shipping', 'value' => 0]), $customer);

        $order = $this->checkout($customer, $product);

        $this->assertSame(0, $order->shipping_fee);
        $this->assertSame(10_000_000, $order->total);
        $this->assertSame(self::SHIPPING, CouponRedemption::where('order_id', $order->id)->value('shipping_discount'));
    }

    public function test_same_coupon_cannot_be_used_twice(): void
    {
        $product = $this->product($this->vendor(), 10_000_000);
        $customer = $this->customer();
        $coupon = $this->walletCoupon($this->campaign(['value' => 2_000_000]), $customer);

        $first = $this->checkout($customer, $product, $coupon->id);
        $this->checkout($customer, $product, $coupon->id);

        $this->assertSame(2_000_000, $first->discount_amount);
        $this->assertSame(1, $customer->orders()->count(), 'الكوبون استُخدم، فلا يُنفَّذ طلب ثانٍ بسعر لم يره العميل');
        $this->assertSame('used', $coupon->fresh()->status);
        $this->assertSame(1, $coupon->campaign->fresh()->redeemed_count);
    }

    public function test_choosing_no_coupon_keeps_full_price(): void
    {
        $product = $this->product($this->vendor(), 10_000_000);
        $customer = $this->customer();
        $coupon = $this->walletCoupon($this->campaign(), $customer);

        $order = $this->checkout($customer, $product, 'none');

        $this->assertSame(0, $order->discount_amount);
        $this->assertSame('available', $coupon->fresh()->status);
    }

    public function test_cannot_use_another_customers_coupon(): void
    {
        $product = $this->product($this->vendor(), 10_000_000);
        $owner = $this->customer();
        $thief = $this->customer();
        $coupon = $this->walletCoupon($this->campaign(), $owner);

        $order = $this->checkout($thief, $product, $coupon->id);

        $this->assertNull($order, 'كوبون من محفظة غيره: يُرفض الطلب');
        $this->assertSame('available', $coupon->fresh()->status);
    }

    public function test_cancel_returns_coupon_and_reverses_seller_funded_earnings(): void
    {
        $vendor = $this->vendor(10);
        $product = $this->product($vendor, 10_000_000);
        $customer = $this->customer();
        $coupon = $this->walletCoupon($this->campaign(['funder' => 'seller', 'seller_profile_id' => $vendor->id, 'audience' => 'collectable']), $customer);
        $order = $this->checkout($customer, $product);
        $this->deliver($order);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.orders.updateStatus', $order), ['status' => 'cancelled']);

        $this->assertSame('available', $coupon->fresh()->status, 'الكوبون يعود للمحفظة');
        $this->assertSame(0, $coupon->campaign->fresh()->redeemed_count);
        $this->assertSame('reversed', CouponRedemption::where('order_id', $order->id)->value('status'));
        $this->assertSame(0, $vendor->wallet()->first()->balance);
    }
}
