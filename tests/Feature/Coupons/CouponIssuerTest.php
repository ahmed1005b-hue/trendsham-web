<?php

namespace Tests\Feature\Coupons;

use App\Coupons\CouponIssuer;
use App\Coupons\CouponSelector;
use App\Coupons\Models\CustomerCoupon;
use App\Models\AppNotification;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CouponIssuerTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    private function issuer(): CouponIssuer
    {
        return app(CouponIssuer::class);
    }

    public function test_all_customers_coupon_is_issued_once_with_notification(): void
    {
        $user = $this->customer();
        $campaign = $this->campaign(['audience' => 'all_customers', 'valid_days' => 7]);

        $this->issuer()->syncFor($user);
        $this->issuer()->syncFor($user);

        $copies = CustomerCoupon::where('user_id', $user->id)->get();
        $this->assertCount(1, $copies);
        $this->assertTrue($copies->first()->expires_at->between(now()->addDays(6), now()->addDays(8)));
        $this->assertSame(1, $campaign->fresh()->issued_count);
        $this->assertSame(1, AppNotification::where('user_id', $user->id)->where('type', 'coupon_received')->count());
    }

    public function test_new_customers_coupon_skips_existing_buyers_and_unverified_phones(): void
    {
        $this->campaign(['audience' => 'new_customers']);
        $newbie = $this->customer();
        $buyer = $this->customer();
        Order::create(['order_number' => 'TS-'.Str::random(6), 'user_id' => $buyer->id, 'status' => 'delivered', 'payment_status' => 'paid',
            'subtotal' => 1, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 1, 'shipping_name' => 'a', 'shipping_phone' => '0', 'shipping_address' => 'b']);
        $unverified = $this->customer(['phone_verified_at' => null]);

        foreach ([$newbie, $buyer, $unverified] as $u) {
            $this->issuer()->syncFor($u);
        }

        $this->assertSame(1, CustomerCoupon::where('user_id', $newbie->id)->count());
        $this->assertSame(0, CustomerCoupon::where('user_id', $buyer->id)->count());
        $this->assertSame(0, CustomerCoupon::where('user_id', $unverified->id)->count());
    }

    public function test_specific_coupon_reaches_only_target_customer(): void
    {
        $target = $this->customer();
        $other = $this->customer();
        $campaign = $this->campaign(['audience' => 'specific']);

        $this->issuer()->issueToSpecific($campaign, [$target->id]);
        $this->issuer()->syncFor($other);

        $this->assertSame(1, CustomerCoupon::where('user_id', $target->id)->count());
        $this->assertSame(0, CustomerCoupon::where('user_id', $other->id)->count());
    }

    public function test_paused_or_seller_campaigns_are_not_synced(): void
    {
        $user = $this->customer();
        $this->campaign(['status' => 'paused']);
        $this->campaign(['funder' => 'seller', 'seller_profile_id' => $this->vendor()->id]);

        $this->issuer()->syncFor($user);

        $this->assertSame(0, CustomerCoupon::where('user_id', $user->id)->count());
    }

    public function test_used_coupon_is_reissued_only_within_per_customer_limit(): void
    {
        $user = $this->customer();
        $this->campaign(['per_customer_limit' => 2]);

        $this->issuer()->syncFor($user);
        CustomerCoupon::where('user_id', $user->id)->update(['status' => 'used']);
        $this->issuer()->syncFor($user);
        CustomerCoupon::where('user_id', $user->id)->update(['status' => 'used']);
        $this->issuer()->syncFor($user);

        $this->assertSame(2, CustomerCoupon::where('user_id', $user->id)->count());
    }

    public function test_selector_picks_highest_saving_and_lists_reasons(): void
    {
        $user = $this->customer();
        $line = $this->line($this->product($this->vendor(), 10_000_000));
        $small = $this->walletCoupon($this->campaign(['value' => 500_000]), $user);
        $big = $this->walletCoupon($this->campaign(['value' => 2_000_000]), $user);
        $blocked = $this->walletCoupon($this->campaign(['value' => 9_000_000, 'min_order_amount' => 50_000_000]), $user);

        $pick = app(CouponSelector::class)->best($user, collect([$line]), 1_500_000);

        $this->assertSame($big->id, $pick['chosen']->id);
        $this->assertSame(2_000_000, $pick['result']->discount);
        $byId = collect($pick['options'])->keyBy(fn ($o) => $o['coupon']->id);
        $this->assertTrue($byId[$small->id]['result']->ok);
        $this->assertFalse($byId[$blocked->id]['result']->ok);
        $this->assertStringStartsWith('أضف', $byId[$blocked->id]['result']->reason);
    }
}
