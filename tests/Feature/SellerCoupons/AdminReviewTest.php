<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\Models\CustomerCoupon;
use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US5: إشراف المدير على كوبونات البائعين */
class AdminReviewTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_admin_lists_all_seller_coupons_with_filters(): void
    {
        $a = $this->seller();
        $b = $this->seller();
        $this->campaign($a, ['title' => 'كوبون أ']);
        $this->campaign($b, ['title' => 'كوبون ب', 'status' => 'pending_review', 'type' => 'percentage', 'value' => 60, 'max_discount' => 100]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.seller-coupons.index'))->assertOk()
            ->assertSee('كوبون أ')->assertSee('كوبون ب')->assertSee('بانتظار الموافقة (1)');
        $this->actingAs($admin)->get(route('admin.seller-coupons.index', ['store' => $a->id]))->assertSee('كوبون أ')->assertDontSee('كوبون ب');
        $this->actingAs($admin)->get(route('admin.seller-coupons.index', ['status' => 'pending_review']))->assertSee('كوبون ب')->assertDontSee('كوبون أ');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee(route('admin.seller-coupons.index'), false);
    }

    public function test_pending_coupon_is_hidden_until_approved_then_seller_is_notified(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['status' => 'pending_review', 'type' => 'percentage', 'value' => 60, 'max_discount' => 1_000_000, 'title' => 'ستون']);
        $u = $this->customer();

        $this->actingAs($u)->get(route('stores.show', $seller))->assertDontSee('ستون');
        $this->actingAs($u, 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertNotFound();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.seller-coupons.approve', $c))->assertRedirect();
        $c->refresh();
        $this->assertSame('active', $c->status);
        $this->assertSame($admin->id, $c->reviewed_by);
        $this->assertNotNull($c->reviewed_at);
        $this->assertTrue(AppNotification::where('user_id', $seller->user_id)->where('type', 'seller_coupon_review')->exists());

        $this->actingAs($u)->get(route('stores.show', $seller))->assertSee('ستون');
    }

    public function test_reject_requires_reason_and_notifies(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['status' => 'pending_review']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.seller-coupons.reject', $c), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('pending_review', $c->fresh()->status);

        $this->actingAs($admin)->post(route('admin.seller-coupons.reject', $c), ['reason' => 'خصم مبالغ فيه']);
        $this->assertSame('rejected', $c->fresh()->status);
        $this->assertSame('خصم مبالغ فيه', $c->fresh()->review_note);
        $this->assertTrue(AppNotification::where('user_id', $seller->user_id)->where('body', 'like', '%خصم مبالغ فيه%')->exists());
    }

    public function test_suspended_coupon_cannot_be_restarted_by_seller(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller);
        $u = $this->customer();
        $this->walletCoupon($c, $u);

        $this->actingAs($this->admin())->post(route('admin.seller-coupons.suspend', $c), ['reason' => 'مخالف للسياسة']);
        $this->assertSame('suspended', $c->fresh()->status);

        $this->actingAs($seller->user)->post(route('seller.coupons.toggle', $c))->assertSessionHas('error');
        $this->assertSame('suspended', $c->fresh()->status);
        $this->actingAs($seller->user)->get(route('seller.coupons.index'))->assertSee('مخالف للسياسة');
        $this->assertSame(1, CustomerCoupon::count(), 'النسخ تبقى لكنها غير صالحة');
    }

    public function test_non_admin_is_forbidden_and_low_percent_needs_no_review(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['status' => 'pending_review']);

        $this->actingAs($seller->user)->post(route('admin.seller-coupons.approve', $c))->assertForbidden();
        $this->actingAs($this->customer())->get(route('admin.seller-coupons.index'))->assertForbidden();

        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData(['value' => 30]));
        $this->assertSame('active', \App\Coupons\Models\CouponCampaign::latest('id')->first()->status);
    }
}
