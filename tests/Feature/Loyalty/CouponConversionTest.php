<?php

namespace Tests\Feature\Loyalty;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Loyalty\PointsCouponIssuer;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US2: تحويل النقاط إلى كوبون في محفظة الكوبونات */
class CouponConversionTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_web_convert_20_points_creates_a_4000_platform_coupon_valid_30_days(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);

        $this->actingAs($u)->post(route('customer.loyalty.convert'), ['points' => 20])
            ->assertRedirect()->assertSessionHasNoErrors();

        $coupon = CustomerCoupon::where('user_id', $u->id)->where('source', 'loyalty')->firstOrFail();
        $this->assertSame('available', $coupon->status);
        $this->assertTrue($coupon->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
        $campaign = $coupon->campaign;
        $this->assertSame('platform', $campaign->funder);
        $this->assertSame('fixed', $campaign->type);
        $this->assertSame(400_000, $campaign->value);
        $this->assertSame('loyalty_points', $campaign->trigger);
        $this->assertSame(10, $this->balance($u));
        $this->assertSame(1, Tx::where('type', Tx::REDEEM_COUPON)->where('customer_coupon_id', $coupon->id)->count());
    }

    public function test_api_convert_and_validation(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);

        $this->actingAs($u, 'sanctum')->postJson('/api/v1/loyalty/convert', ['points' => 20])
            ->assertCreated()->assertJsonPath('value', 4000)->assertJsonPath('balance', 10);

        $this->postJson('/api/v1/loyalty/convert', ['points' => 5])->assertStatus(422)->assertJsonValidationErrors('points');
        $this->postJson('/api/v1/loyalty/convert', ['points' => 50])->assertStatus(422)->assertJsonValidationErrors('points');
        $this->assertSame(10, $this->balance($u));
    }

    public function test_same_value_conversions_reuse_one_campaign(): void
    {
        $u = $this->customer();
        $this->grant($u, 40);
        $issuer = app(PointsCouponIssuer::class);
        $issuer->convert($u, 20);
        $issuer->convert($u, 20);

        $this->assertSame(1, CouponCampaign::where('trigger', 'loyalty_points')->count());
        $this->assertSame(2, CustomerCoupon::where('user_id', $u->id)->where('status', 'available')->count());
    }

    public function test_points_coupon_is_used_at_checkout_and_platform_pays(): void
    {
        $u = $this->customer();
        $this->grant($u, 20);
        $coupon = app(PointsCouponIssuer::class)->convert($u, 20);
        $p = $this->product(price: 5_000_000);
        CartItem::create(['user_id' => $u->id, 'product_id' => $p->id, 'quantity' => 1, 'unit_price' => $p->price]);

        $this->actingAs($u)->withSession(['checkout_coupon' => $coupon->id])->post(route('customer.checkout.store'), [
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ])->assertSessionHasNoErrors();

        $order = $u->orders()->latest('id')->firstOrFail();
        $this->assertSame(400_000, (int) $order->discount_amount);
        $this->assertSame('platform', $order->items()->first()->discount_funder);
        $this->assertSame('used', $coupon->fresh()->status);
    }

    public function test_expired_unused_points_coupon_returns_its_points(): void
    {
        $u = $this->customer();
        $this->grant($u, 30);
        $coupon = app(PointsCouponIssuer::class)->convert($u, 20);

        $this->travel(31)->days();
        $this->artisan('loyalty:daily')->assertSuccessful();

        $this->assertSame('expired', $coupon->fresh()->status);
        $this->assertSame(30, $this->balance($u));
        $this->assertLedgerConsistent($u);
    }

    public function test_points_coupons_are_hidden_from_admin_campaign_list(): void
    {
        $u = $this->customer();
        $this->grant($u, 20);
        app(PointsCouponIssuer::class)->convert($u, 20);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.coupon-campaigns.index'))->assertOk()->assertDontSee('خصم نقاط الولاء');
    }
}
