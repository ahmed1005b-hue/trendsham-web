<?php

namespace Tests\Feature\Coupons;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponModelsTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    public function test_wallet_coupon_of_running_campaign_is_usable(): void
    {
        $coupon = $this->walletCoupon($this->campaign(), $this->customer());

        $this->assertTrue($coupon->campaign->isRunning());
        $this->assertTrue($coupon->isUsable());
    }

    public function test_coupon_is_not_usable_after_campaign_end_or_pause(): void
    {
        $ended = $this->walletCoupon($this->campaign(['ends_at' => now()->subMinute()]), $this->customer());
        $paused = $this->walletCoupon($this->campaign(['status' => 'paused']), $this->customer());

        $this->assertFalse($ended->isUsable());
        $this->assertFalse($paused->isUsable());
    }

    public function test_own_expiry_and_used_status_make_coupon_unusable(): void
    {
        $campaign = $this->campaign();
        $expired = $this->walletCoupon($campaign, $this->customer(), ['expires_at' => now()->subMinute()]);
        $used = $this->walletCoupon($campaign, $this->customer(), ['status' => 'used']);

        $this->assertFalse($expired->isUsable());
        $this->assertFalse($used->isUsable());
    }

    public function test_campaign_stops_running_when_total_limit_reached(): void
    {
        $campaign = $this->campaign(['total_limit' => 2, 'redeemed_count' => 2]);

        $this->assertFalse($campaign->isRunning());
    }
}
