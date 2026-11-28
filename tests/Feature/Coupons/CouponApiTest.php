<?php

namespace Tests\Feature\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponApiTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    private function cart(User $customer, int $price = 10_000_000): void
    {
        $product = $this->product($this->vendor(), $price);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price]);
    }

    private function payload(array $extra = []): array
    {
        return ['shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true] + $extra;
    }

    public function test_wallet_lists_available_coupons(): void
    {
        $customer = $this->customer();
        $this->campaign(['title' => 'للجميع', 'type' => 'percentage', 'value' => 10]);

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/coupons')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'للجميع')
            ->assertJsonPath('data.0.value_label', 'خصم 10%')
            ->assertJsonPath('data.0.status', 'available');
    }

    public function test_summary_returns_best_coupon_and_reasons(): void
    {
        $customer = $this->customer();
        $this->cart($customer);
        $best = $this->walletCoupon($this->campaign(['value' => 2_000_000]), $customer);
        $blocked = $this->walletCoupon($this->campaign(['min_order_amount' => 50_000_000]), $customer);

        $res = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/checkout/summary')->assertOk();

        $res->assertJsonPath('coupon.id', $best->id)->assertJsonPath('discount', 20000);
        $options = collect($res->json('coupon_options'))->keyBy('id');
        $this->assertTrue($options[$best->id]['ok']);
        $this->assertSame(20000, $options[$best->id]['saving']);
        $this->assertFalse($options[$blocked->id]['ok']);
        $this->assertStringStartsWith('أضف', $options[$blocked->id]['reason']);
    }

    public function test_summary_without_coupon_when_customer_opts_out(): void
    {
        $customer = $this->customer();
        $this->cart($customer);
        $this->walletCoupon($this->campaign(), $customer);

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/checkout/summary?customer_coupon_id=0')
            ->assertOk()->assertJsonPath('coupon', null)->assertJsonPath('discount', 0);
    }

    public function test_checkout_applies_best_coupon_when_not_specified(): void
    {
        $customer = $this->customer();
        $this->cart($customer);
        $coupon = $this->walletCoupon($this->campaign(['value' => 2_000_000]), $customer);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertCreated();

        $this->assertSame('used', $coupon->fresh()->status);
    }

    public function test_checkout_with_foreign_coupon_is_rejected(): void
    {
        $customer = $this->customer();
        $this->cart($customer);
        $foreign = $this->walletCoupon($this->campaign(), $this->customer());

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/checkout', $this->payload(['customer_coupon_id' => $foreign->id]))
            ->assertStatus(422)->assertJsonPath('code', 'coupon_invalid');

        $this->assertSame(0, $customer->orders()->count());
        $this->assertSame('available', $foreign->fresh()->status);
    }

    public function test_admin_api_creates_and_pauses_campaign(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $id = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/coupon-campaigns', [
            'title' => 'للجدد', 'type' => 'percentage', 'value' => 15, 'max_discount' => 50000,
            'scope' => 'all', 'audience' => 'new_customers', 'per_customer_limit' => 1,
        ])->assertCreated()->json('data.id');

        $this->assertSame(5_000_000, CouponCampaign::find($id)->max_discount);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/coupon-campaigns')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/coupon-campaigns/{$id}/toggle")->assertOk()->assertJsonPath('data.status', 'paused');
    }

    public function test_customer_cannot_use_admin_campaign_api(): void
    {
        $this->actingAs($this->customer(), 'sanctum')->getJson('/api/v1/admin/coupon-campaigns')->assertForbidden();
        $this->assertSame(0, CustomerCoupon::count());
    }
}
