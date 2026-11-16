<?php

namespace Tests\Feature\Loyalty;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US2: استبدال النقاط بخصم عند الدفع (الموقع والتطبيق). المنصة تتحمّل الخصم. */
class CheckoutRedemptionTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private function cart(User $u, Product $p): void
    {
        CartItem::create(['user_id' => $u->id, 'product_id' => $p->id, 'quantity' => 1, 'unit_price' => $p->price]);
    }

    private function webCheckout(User $u, Product $p, ?int $points, ?string $coupon = 'none')
    {
        $this->cart($u, $p);

        return $this->actingAs($u)->withSession($coupon === null ? [] : ['checkout_coupon' => $coupon])
            ->post(route('customer.checkout.store'), array_filter([
                'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
                'payment_method' => 'cash_on_delivery', 'use_points' => $points,
            ], fn ($v) => $v !== null));
    }

    private function apiCheckout(User $u, Product $p, ?int $points)
    {
        $this->cart($u, $p);

        return $this->actingAs($u, 'sanctum')->postJson('/api/v1/checkout', array_filter([
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
            'payment_method' => 'cash_on_delivery', 'customer_coupon_id' => 0, 'use_points' => $points,
        ], fn ($v) => $v !== null));
    }

    public function test_web_redeem_20_points_gives_4000_discount_and_seller_earnings_are_untouched(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);
        $p = $this->product(price: 5_000_000);   // 50,000 ل.س

        $this->webCheckout($u, $p, 20)->assertSessionHasNoErrors();

        $order = $u->orders()->latest('id')->firstOrFail();
        $this->assertSame(20, (int) $order->points_redeemed);
        $this->assertSame(400_000, (int) $order->points_discount);
        $this->assertSame(5_000_000 + (int) $order->shipping_fee - 400_000, (int) $order->total);
        $this->assertSame(10, $this->balance($u));

        $this->deliver($order);
        $item = $order->items()->first();
        $this->assertSame(4_500_000, (int) $item->vendor_earning);   // 50,000 − عمولة 10% — بلا أي خصم للنقاط
        // يكسب على المدفوع فعلاً: 50,000 − 4,000 = 46,000 → 4 نقاط
        $this->assertSame(14, $this->balance($u));
        $this->assertLedgerConsistent($u);
    }

    public function test_api_redeem_works_the_same_and_reports_the_discount(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);

        $res = $this->apiCheckout($u, $this->product(price: 5_000_000), 20)->assertCreated();

        $this->assertSame(10, $this->balance($u));
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(400_000, (int) $order->points_discount);
        $this->assertEquals(20, $res->json('data.points_redeemed') ?? $res->json('points_redeemed'));
    }

    public function test_below_minimum_is_refused_without_creating_an_order(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);

        $this->webCheckout($u, $this->product(price: 5_000_000), 5)->assertSessionHasErrors('use_points');
        $this->assertSame(0, Order::count());
        $this->assertSame(30, $this->balance($u));

        $this->apiCheckout($u, $this->product(price: 5_000_000), 5)->assertStatus(422)->assertJsonValidationErrors('use_points');
        $this->assertSame(0, Order::count());
    }

    public function test_more_than_balance_is_refused(): void
    {
        $u = $this->customer();
        $this->grant($u, 12);

        $this->webCheckout($u, $this->product(price: 5_000_000), 20)->assertSessionHasErrors('use_points');
        $this->assertSame(0, Order::count());
    }

    public function test_points_worth_more_than_the_cart_are_capped_and_total_never_negative(): void
    {
        $u = $this->customer();
        $this->grant($u, 100);
        $p = $this->product(price: 300_000);   // 3,000 ل.س = 15 نقطة كحد أقصى

        $this->webCheckout($u, $p, 100)->assertSessionHasNoErrors();

        $order = $u->orders()->latest('id')->firstOrFail();
        $this->assertSame(15, (int) $order->points_redeemed);
        $this->assertSame((int) $order->shipping_fee, (int) $order->total);   // الشحن لا يُدفع بالنقاط
        $this->assertSame(85, $this->balance($u));
    }

    public function test_points_apply_on_what_is_left_after_a_coupon(): void
    {
        $u = $this->customer();
        $this->grant($u, 100);
        $campaign = CouponCampaign::create([
            'funder' => 'platform', 'title' => 'خصم', 'type' => 'fixed', 'value' => 200_000,
            'scope' => 'all', 'audience' => 'specific', 'starts_at' => now()->subDay(), 'per_customer_limit' => 1, 'status' => 'active',
        ]);
        $coupon = CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $u->id, 'status' => 'available', 'source' => 'test']);
        $p = $this->product(price: 500_000);   // 5,000 − كوبون 2,000 = 3,000 → 15 نقطة كحد أقصى

        $this->webCheckout($u, $p, 100, (string) $coupon->id)->assertSessionHasNoErrors();

        $order = $u->orders()->latest('id')->firstOrFail();
        $this->assertSame(200_000, (int) $order->discount_amount);
        $this->assertSame(15, (int) $order->points_redeemed);
    }

    public function test_cancel_before_delivery_returns_redeemed_points(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);
        $this->webCheckout($u, $this->product(price: 5_000_000), 20);
        $order = $u->orders()->latest('id')->firstOrFail();

        $order->changeStatus('cancelled');

        $this->assertSame(30, $this->balance($u));
        $this->assertSame(1, Tx::where('type', Tx::RESTORE)->where('order_id', $order->id)->count());
        $this->assertLedgerConsistent($u);
    }

    public function test_suspended_customer_cannot_redeem(): void
    {
        $u = $this->customer(['status' => 'suspended']);
        $this->grant($u, 30);

        $this->apiCheckout($u, $this->product(price: 5_000_000), 20);
        $this->assertSame(30, $this->balance($u));
    }

    public function test_no_points_requested_keeps_old_behaviour(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);
        $this->webCheckout($u, $this->product(price: 5_000_000), null)->assertSessionHasNoErrors();

        $order = $u->orders()->latest('id')->firstOrFail();
        $this->assertSame(0, (int) $order->points_discount);
        $this->assertSame(30, $this->balance($u));
    }
}
