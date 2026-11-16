<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\Models\CouponCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US1: البائع ينشئ كوبوناً لمتجره */
class CreateCouponTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_index_shows_add_button_and_create_stores_active_campaign(): void
    {
        $seller = $this->seller();
        $user = $seller->user;

        $this->actingAs($user)->get(route('seller.coupons.index'))->assertOk()
            ->assertSee('إضافة كوبون')->assertSee(route('seller.coupons.create'), false);
        $this->actingAs($user)->get(route('seller.coupons.create'))->assertOk();

        $this->actingAs($user)->post(route('seller.coupons.store'), $this->formData())
            ->assertRedirect(route('seller.coupons.index'));

        $c = CouponCampaign::sole();
        $this->assertSame('seller', $c->funder);
        $this->assertSame($seller->id, $c->seller_profile_id);
        $this->assertSame('active', $c->status);
        $this->assertSame(10, $c->value);
        $this->assertSame(2_000_000, $c->max_discount);
        $this->assertSame(5_000_000, $c->min_order_amount);
        $this->assertSame(100, $c->total_limit);
        $this->assertSame($user->id, $c->created_by);

        $this->actingAs($user)->get(route('seller.coupons.index'))->assertSee('جُمع 0 من 100')->assertSee('خصم الافتتاح');
    }

    public function test_invalid_values_are_rejected_with_arabic_messages(): void
    {
        $user = $this->seller()->user;
        $bad = [
            ['value' => 0], ['value' => 71], ['max_discount' => null],
            ['type' => 'fixed', 'value' => 0],
            ['ends_at' => now()->subDay()->format('Y-m-d')],
            ['ends_at' => now()->addYears(2)->format('Y-m-d')],
            ['total_limit' => null], ['per_customer_limit' => 9],
        ];
        foreach ($bad as $over) {
            $this->actingAs($user)->post(route('seller.coupons.store'), array_merge($this->formData(), $over))
                ->assertSessionHasErrors();
        }
        $this->assertSame(0, CouponCampaign::count());
    }

    public function test_products_scope_only_accepts_own_products(): void
    {
        $seller = $this->seller();
        $mine = $this->product($seller);
        $theirs = $this->product($this->seller());

        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData(['scope' => 'products', 'product_ids' => [$theirs->id]]))
            ->assertSessionHasErrors('product_ids.0');
        $this->actingAs($seller->user)->get(route('seller.coupons.create'))->assertSee($mine->name)->assertDontSee($theirs->name);

        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData(['scope' => 'products', 'product_ids' => [$mine->id]]))
            ->assertSessionHasNoErrors();
        $this->assertSame([$mine->id], CouponCampaign::sole()->load('targets')->targetIds('product'));
    }

    public function test_unverified_or_suspended_store_cannot_create(): void
    {
        foreach ([$this->seller(verified: false), $this->seller(status: 'suspended')] as $seller) {
            $this->actingAs($seller->user)->get(route('seller.coupons.create'))->assertOk()->assertSee('لا يمكنك إنشاء كوبونات');
            $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData())->assertForbidden();
        }
        $this->assertSame(0, CouponCampaign::count());
    }

    public function test_seller_cannot_touch_another_stores_campaign(): void
    {
        $other = $this->campaign($this->seller());
        $user = $this->seller()->user;

        $this->actingAs($user)->get(route('seller.coupons.show', $other))->assertNotFound();
        $this->actingAs($user)->get(route('seller.coupons.edit', $other))->assertNotFound();
        $this->actingAs($user)->post(route('seller.coupons.toggle', $other))->assertNotFound();
        $this->actingAs($user)->delete(route('seller.coupons.destroy', $other))->assertNotFound();
        $this->assertSame('active', $other->fresh()->status);
    }

    public function test_platform_campaigns_are_not_listed_for_seller(): void
    {
        $seller = $this->seller();
        CouponCampaign::create(['funder' => 'platform', 'title' => 'كوبون المنصة', 'type' => 'fixed', 'value' => 100,
            'scope' => 'all', 'audience' => 'all_customers', 'per_customer_limit' => 1, 'status' => 'active']);

        $this->actingAs($seller->user)->get(route('seller.coupons.index'))->assertDontSee('كوبون المنصة');
    }
}
