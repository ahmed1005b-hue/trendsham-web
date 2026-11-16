<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\Models\CustomerCoupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US2: العميل يجمع كوبون المتجر من صفحة المتجر/المنتج (الموقع والتطبيق) */
class CollectCouponTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_store_page_shows_coupon_and_collect_puts_it_in_wallet(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['title' => 'كوبون الافتتاح']);
        $u = $this->customer();

        $this->actingAs($u)->get(route('stores.show', $seller))->assertOk()
            ->assertSee('كوبون الافتتاح')->assertSee(route('coupons.collect', $c), false);

        $this->actingAs($u)->post(route('coupons.collect', $c))->assertRedirect()->assertSessionHas('success');
        $copy = CustomerCoupon::sole();
        $this->assertSame($u->id, $copy->user_id);
        $this->assertSame('collect', $copy->source);
        $this->assertSame(1, $c->fresh()->issued_count);

        $this->actingAs($u)->get(route('stores.show', $seller))->assertSee('محفوظ');
        $this->actingAs($u)->get(route('customer.coupons.index'))->assertSee($seller->store_name);
    }

    public function test_second_collect_does_not_duplicate(): void
    {
        $c = $this->campaign($this->seller());
        $u = $this->customer();

        $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertCreated()->assertJsonStructure(['coupon_id', 'message']);
        $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertStatus(409);
        $this->actingAs($u)->post(route('coupons.collect', $c))->assertSessionHas('error');

        $this->assertSame(1, CustomerCoupon::count());
        $this->assertSame(1, $c->fresh()->issued_count);
    }

    public function test_sold_out_after_total_limit(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['total_limit' => 1]);
        $a = $this->customer();
        $b = $this->customer();

        $this->actingAs($a, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertCreated();
        $this->actingAs($b, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertStatus(410)->assertJsonPath('message', 'نفدت الكوبونات');
        $this->actingAs($b)->get(route('stores.show', $seller))->assertSee('نفدت');
        $this->assertSame(1, CustomerCoupon::count());
    }

    public function test_unavailable_campaigns_are_hidden_and_not_collectable(): void
    {
        $seller = $this->seller();
        $u = $this->customer();
        foreach (['paused', 'pending_review', 'suspended', 'rejected'] as $status) {
            $c = $this->campaign($seller, ['status' => $status, 'title' => "حالة $status"]);
            $this->actingAs($u)->get(route('stores.show', $seller))->assertDontSee("حالة $status");
            $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertNotFound();
        }
        $followersOnly = $this->campaign($seller, ['audience' => 'followers']);
        $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$followersOnly->id}/collect")->assertNotFound();

        $suspended = $this->seller(status: 'suspended');
        $c = $this->campaign($suspended);
        $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertNotFound();
        $this->assertSame(0, CustomerCoupon::count());
    }

    public function test_guest_is_sent_to_login(): void
    {
        $c = $this->campaign($this->seller());

        $this->post(route('coupons.collect', $c))->assertRedirect(route('login'));
    }

    public function test_api_store_and_product_include_coupons_with_state(): void
    {
        $seller = $this->seller();
        $p = $this->product($seller);
        $other = $this->product($seller);
        $c = $this->campaign($seller, ['scope' => 'products']);
        $c->targets()->create(['target_type' => 'product', 'target_id' => $p->id]);
        $u = $this->customer();

        $this->getJson("/api/v1/stores/{$seller->id}")->assertOk()->assertJsonPath('coupons.0.id', $c->id)->assertJsonPath('coupons.0.state', 'login_required');
        $this->actingAs($u, 'sanctum')->getJson("/api/v1/stores/{$seller->id}")->assertJsonPath('coupons.0.state', 'collectable')
            ->assertJsonPath('coupons.0.value_label', 'خصم 20,000 ل.س');

        $this->getJson("/api/v1/products/{$p->slug}")->assertJsonPath('data.store_coupons.0.id', $c->id);
        $this->getJson("/api/v1/products/{$other->slug}")->assertJsonCount(0, 'data.store_coupons');

        $this->get(route('products.show', $p))->assertSee($c->title);
        $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect");
        $this->actingAs($u, 'sanctum')->getJson("/api/v1/stores/{$seller->id}")->assertJsonPath('coupons.0.state', 'collected');
    }
}
