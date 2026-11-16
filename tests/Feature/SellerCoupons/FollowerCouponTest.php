<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\FollowerIssuer;
use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\AppNotification;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US3: كوبون تلقائي لمتابعي المتجر */
class FollowerCouponTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    private function follow(SellerProfile $store, User $u): void
    {
        $store->followers()->create(['user_id' => $u->id]);
    }

    public function test_creating_followers_coupon_reaches_all_current_followers_once(): void
    {
        $seller = $this->seller();
        $fans = collect(range(1, 3))->map(fn () => $this->customer())->each(fn ($u) => $this->follow($seller, $u));

        $this->actingAs($seller->user)->get(route('seller.coupons.create'))->assertSee('سيصل إلى 3 متابعاً');
        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData(['audience' => 'followers']))
            ->assertRedirect();

        $c = CouponCampaign::sole();
        $this->assertSame(3, CustomerCoupon::where('campaign_id', $c->id)->where('source', 'followers')->count());
        $this->assertSame(3, AppNotification::where('type', 'coupon_received')->count());
        $this->assertNotNull($c->fresh()->followers_issued_at);

        // تشغيل ثانٍ لا يكرر
        $this->assertSame(0, app(FollowerIssuer::class)->issueToAll($c->fresh()));
        $this->assertSame(3, CustomerCoupon::count());
    }

    public function test_new_follower_gets_it_once_even_after_refollow(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['audience' => 'followers']);
        $u = $this->customer();

        $this->actingAs($u)->post(route('stores.follow', $seller));
        $this->assertSame(1, CustomerCoupon::where('campaign_id', $c->id)->where('user_id', $u->id)->count());

        $this->actingAs($u)->post(route('stores.follow', $seller)); // إلغاء
        $this->actingAs($u)->post(route('stores.follow', $seller)); // متابعة من جديد
        $this->assertSame(1, CustomerCoupon::where('user_id', $u->id)->count());

        $v = $this->customer();
        $this->actingAs($v, 'sanctum')->postJson("/api/v1/stores/{$seller->id}/follow")->assertOk()->assertJsonPath('coupons_received', 1);
    }

    public function test_total_limit_caps_follower_issuance(): void
    {
        $seller = $this->seller();
        collect(range(1, 3))->each(fn () => $this->follow($seller, $this->customer()));
        $c = $this->campaign($seller, ['audience' => 'followers', 'total_limit' => 2]);

        $this->assertSame(2, app(FollowerIssuer::class)->issueToAll($c));
        $this->assertSame(2, CustomerCoupon::count());
        $this->assertSame(2, $c->fresh()->issued_count);
    }

    public function test_pending_review_coupon_is_not_sent_until_approved(): void
    {
        $seller = $this->seller();
        $this->follow($seller, $this->customer());

        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData(['audience' => 'followers', 'value' => 60]));
        $c = CouponCampaign::sole();
        $this->assertSame('pending_review', $c->status);
        $this->assertSame(0, CustomerCoupon::count());

        $this->actingAs($this->admin())->post(route('admin.seller-coupons.approve', $c));
        $this->assertSame(1, CustomerCoupon::count());
    }

    public function test_collectable_with_auto_followers_also_reaches_followers(): void
    {
        $seller = $this->seller();
        $this->follow($seller, $this->customer());

        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $this->formData(['auto_followers' => 1]));

        $c = CouponCampaign::sole();
        $this->assertTrue($c->auto_followers);
        $this->assertSame('collectable', $c->audience);
        $this->assertSame(1, CustomerCoupon::count());
    }
}
