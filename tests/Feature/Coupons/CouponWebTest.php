<?php

namespace Tests\Feature\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponWebTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    public function test_my_coupons_page_shows_platform_coupon_for_everyone(): void
    {
        $customer = $this->customer();
        $this->campaign(['title' => 'هدية الأسبوع', 'type' => 'percentage', 'value' => 15]);

        $this->actingAs($customer)->get(route('customer.coupons.index'))
            ->assertOk()->assertSee('هدية الأسبوع')->assertSee('خصم 15%');
    }

    public function test_checkout_page_applies_best_coupon_and_offers_change(): void
    {
        $customer = $this->customer();
        $product = $this->product($this->vendor(), 10_000_000);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price]);
        $this->walletCoupon($this->campaign(['title' => 'كوبون كبير', 'value' => 2_000_000]), $customer);
        $this->walletCoupon($this->campaign(['title' => 'كوبون مشروط', 'min_order_amount' => 90_000_000]), $customer);

        $this->actingAs($customer)->get(route('customer.checkout.show'))
            ->assertOk()
            ->assertSee('طبّقنا لك أفضل كوبون')
            ->assertSee('كوبون كبير')
            ->assertSee('أضف 800,000 ل.س لتستفيد');
    }

    public function test_customer_can_choose_no_coupon(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('customer.checkout.coupon'), ['choice' => 'none'])
            ->assertRedirect()->assertSessionHas('checkout_coupon', 'none');
    }

    public function test_customer_cannot_choose_someone_elses_coupon(): void
    {
        $other = $this->walletCoupon($this->campaign(), $this->customer());

        $this->actingAs($this->customer())->post(route('customer.checkout.coupon'), ['choice' => $other->id])
            ->assertSessionHas('error')->assertSessionMissing('checkout_coupon');
    }

    public function test_admin_creates_coupon_for_specific_customer_by_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = $this->customer(['phone' => '0933111222']);
        $other = $this->customer();

        $this->actingAs($admin)->post(route('admin.coupon-campaigns.store'), [
            'title' => 'اعتذار عن التأخير', 'type' => 'fixed', 'value' => 25000, 'min_order_amount' => 0,
            'scope' => 'all', 'audience' => 'specific', 'customer_contact' => '0933111222',
            'per_customer_limit' => 1, 'valid_days' => 30,
        ])->assertRedirect(route('admin.coupon-campaigns.index'))->assertSessionHas('success');

        $campaign = CouponCampaign::firstOrFail();
        $this->assertSame('platform', $campaign->funder);
        $this->assertSame(2_500_000, $campaign->value, 'المدير يُدخل بالليرة، ويُحفظ بالوحدة الصغرى');
        $this->assertSame(1, CustomerCoupon::where('user_id', $target->id)->count());
        $this->assertSame(0, CustomerCoupon::where('user_id', $other->id)->count());
    }

    public function test_admin_percentage_must_be_between_1_and_100(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.coupon-campaigns.store'), [
            'title' => 'x', 'type' => 'percentage', 'value' => 150, 'scope' => 'all', 'audience' => 'all_customers', 'per_customer_limit' => 1,
        ])->assertSessionHasErrors('value');
    }

    public function test_admin_can_pause_campaign(): void
    {
        $campaign = $this->campaign();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.coupon-campaigns.toggle', $campaign));

        $this->assertSame('paused', $campaign->fresh()->status);
    }

    public function test_non_admin_cannot_create_campaigns(): void
    {
        $this->actingAs($this->customer())->post(route('admin.coupon-campaigns.store'), [])->assertForbidden();
    }
}
