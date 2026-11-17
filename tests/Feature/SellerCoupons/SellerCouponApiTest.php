<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\Models\CouponCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** كوبونات البائع من التطبيق: نفس قواعد لوحة الموقع. */
class SellerCouponApiTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_seller_lists_own_coupons_with_form_data(): void
    {
        $seller = $this->seller();
        $mine = $this->product($seller);
        $this->campaign($seller, ['title' => 'كوبوني']);
        $this->campaign($this->seller(), ['title' => 'كوبون غيري']);

        $this->actingAs($seller->user, 'sanctum')->getJson('/api/v1/seller/coupons')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'كوبوني')
            ->assertJsonPath('data.0.value', 20000)
            ->assertJsonPath('block_reason', null)
            ->assertJsonPath('products.0.id', $mine->id);
    }

    public function test_seller_creates_coupon_and_high_percent_waits_for_review(): void
    {
        $seller = $this->seller();

        $this->actingAs($seller->user, 'sanctum')->postJson('/api/v1/seller/coupons', $this->formData())
            ->assertCreated()->assertJsonPath('data.status', 'active');

        $this->actingAs($seller->user, 'sanctum')->postJson('/api/v1/seller/coupons', $this->formData(['value' => 60]))
            ->assertCreated()->assertJsonPath('data.status', 'pending_review');

        $this->assertSame(2, CouponCampaign::ofSeller($seller->id)->count());
        $this->assertSame(5_000_000, CouponCampaign::ofSeller($seller->id)->oldest('id')->first()->min_order_amount);
    }

    public function test_invalid_values_and_unverified_store_are_rejected(): void
    {
        $seller = $this->seller();
        $this->actingAs($seller->user, 'sanctum')->postJson('/api/v1/seller/coupons', $this->formData(['value' => 71]))
            ->assertStatus(422)->assertJsonValidationErrors('value');

        $unverified = $this->seller(verified: false);
        $this->actingAs($unverified->user, 'sanctum')->postJson('/api/v1/seller/coupons', $this->formData())
            ->assertForbidden();
        $this->assertSame(0, CouponCampaign::count());
    }

    public function test_toggle_update_and_delete_only_own_coupon(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller);
        $other = $this->campaign($this->seller());
        $api = $this->actingAs($seller->user, 'sanctum');

        $api->postJson("/api/v1/seller/coupons/{$c->id}/toggle")->assertOk()->assertJsonPath('data.status', 'paused');
        $api->putJson("/api/v1/seller/coupons/{$c->id}", $this->formData(['title' => 'اسم جديد']))
            ->assertOk()->assertJsonPath('data.title', 'اسم جديد');
        $api->getJson("/api/v1/seller/coupons/{$c->id}")->assertOk()->assertJsonPath('stats.redeemed', 0);

        $api->postJson("/api/v1/seller/coupons/{$other->id}/toggle")->assertNotFound();
        $api->deleteJson("/api/v1/seller/coupons/{$other->id}")->assertNotFound();

        $api->deleteJson("/api/v1/seller/coupons/{$c->id}")->assertOk();
        $this->assertSoftDeleted($c);
        $this->assertModelExists($other);
    }

    public function test_pending_coupon_cannot_be_toggled_and_collected_coupon_cannot_be_deleted(): void
    {
        $seller = $this->seller();
        $pending = $this->campaign($seller, ['status' => 'pending_review']);
        $collected = $this->campaign($seller);
        $this->walletCoupon($collected, $this->customer());
        $api = $this->actingAs($seller->user, 'sanctum');

        $api->postJson("/api/v1/seller/coupons/{$pending->id}/toggle")->assertStatus(422);
        $this->assertSame('pending_review', $pending->fresh()->status);

        $api->deleteJson("/api/v1/seller/coupons/{$collected->id}")->assertStatus(422);
        $this->assertModelExists($collected);
        $api->getJson("/api/v1/seller/coupons/{$collected->id}")->assertJsonPath('data.is_locked', true);
    }
}
