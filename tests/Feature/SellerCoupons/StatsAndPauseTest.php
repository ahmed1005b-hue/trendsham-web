<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\CouponEligibility;
use App\Coupons\Models\CouponCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US4: إحصائيات الكوبون وإيقافه، وقفل القيمة بعد الجمع */
class StatsAndPauseTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_stats_count_only_applied_redemptions_and_sellers_sales(): void
    {
        $seller = $this->seller();
        $p = $this->product($seller, 10_000_000);
        $c = $this->campaign($seller, ['per_customer_limit' => 1]);

        $orders = [];
        foreach (range(1, 3) as $i) {
            $u = $this->customer();
            $this->walletCoupon($c, $u);
            $this->cartLine($u, $p);
            $orders[] = $this->checkout($u);
        }
        $this->deliver($orders[0]);
        $this->deliver($orders[1]);
        $orders[2]->changeStatus('cancelled');

        $this->actingAs($seller->user)->get(route('seller.coupons.show', $c))->assertOk()
            ->assertSeeInOrder(['3', 'جُمع من 100'])
            ->assertSee('40,000 ل.س')      // خصم 20,000 × طلبين
            ->assertSee('200,000 ل.س')     // مبيعات 100,000 × طلبين
            ->assertSee($orders[0]->order_number);

        $stats = app(\App\Coupons\SellerCouponStats::class)->for($c->fresh());
        $this->assertSame(2, $stats['redeemed']);
    }

    public function test_pause_hides_coupon_and_marks_wallet_but_keeps_existing_orders(): void
    {
        $seller = $this->seller();
        $p = $this->product($seller, 10_000_000);
        $c = $this->campaign($seller, ['title' => 'كوبون الإيقاف', 'per_customer_limit' => 2]);
        $u = $this->customer();
        $this->walletCoupon($c, $u);
        $this->cartLine($u, $p);
        $order = $this->checkout($u);
        $order->changeStatus('processing');
        $waiting = $this->walletCoupon($c, $this->customer());

        $this->actingAs($seller->user)->post(route('seller.coupons.toggle', $c))->assertSessionHas('success');
        $this->assertSame('paused', $c->fresh()->status);

        $this->get(route('stores.show', $seller))->assertDontSee('كوبون الإيقاف');
        $res = app(CouponEligibility::class)->check($waiting->fresh('campaign.seller'), collect([(object) ['product' => $p, 'subtotal' => $p->price]]), $this->shipping);
        $this->assertFalse($res->ok);
        $this->assertSame('أوقفه المتجر مؤقتاً', $res->reason);
        $this->actingAs($waiting->user)->get(route('customer.coupons.index', ['tab' => 'expired']))->assertSee('أوقفه المتجر مؤقتاً');
        $this->actingAs($waiting->user, 'sanctum')->getJson('/api/v1/coupons?tab=expired')->assertJsonPath('data.0.campaign_state', 'paused')->assertJsonPath('data.0.campaign_state_label', 'أوقفه المتجر مؤقتاً');

        $this->assertSame(2_000_000, $order->fresh()->discount_amount, 'طلب قائم لا يتغير');

        $this->actingAs($seller->user)->post(route('seller.coupons.toggle', $c));
        $this->assertSame('active', $c->fresh()->status);
    }

    public function test_locked_after_collect_only_extend_and_increase(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller, ['total_limit' => 50]);
        $this->walletCoupon($c, $this->customer());
        $user = $seller->user;
        $origEnd = $c->ends_at->copy();

        // تغيير القيمة يُتجاهل (الحقول المقفلة لا تُرسل أصلاً)، والتقصير مرفوض
        $this->actingAs($user)->put(route('seller.coupons.update', $c), ['ends_at' => now()->addDays(3)->format('Y-m-d'), 'total_limit' => 50, 'value' => 99])
            ->assertSessionHasErrors('ends_at');
        $this->actingAs($user)->put(route('seller.coupons.update', $c), ['ends_at' => now()->addDays(30)->format('Y-m-d'), 'total_limit' => 40])
            ->assertSessionHasErrors('total_limit');

        $this->actingAs($user)->put(route('seller.coupons.update', $c), ['ends_at' => now()->addDays(30)->format('Y-m-d'), 'total_limit' => 80, 'value' => 99, 'type' => 'percentage'])
            ->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame(80, $c->total_limit);
        $this->assertTrue($c->ends_at->gt($origEnd));
        $this->assertSame(2_000_000, $c->value);
        $this->assertSame('fixed', $c->type);

        $this->actingAs($user)->delete(route('seller.coupons.destroy', $c))->assertSessionHas('error');
        $this->assertNotNull($c->fresh());
    }

    public function test_unlocked_coupon_can_be_fully_edited_and_deleted(): void
    {
        $seller = $this->seller();
        $c = $this->campaign($seller);
        $user = $seller->user;

        $this->actingAs($user)->get(route('seller.coupons.edit', $c))->assertOk();
        $this->actingAs($user)->put(route('seller.coupons.update', $c), $this->formData(['type' => 'fixed', 'value' => 5000, 'max_discount' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame(500_000, $c->fresh()->value);

        $this->actingAs($user)->delete(route('seller.coupons.destroy', $c))->assertRedirect(route('seller.coupons.index'));
        $this->assertNull(CouponCampaign::find($c->id));
    }
}
