<?php

namespace Tests\Feature\Coupons;

use App\Coupons\CouponEligibility;
use App\Coupons\Models\CouponCampaignTarget;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponEligibilityTest extends TestCase
{
    use CouponTestHelpers, RefreshDatabase;

    private const SHIPPING = 1_500_000;

    private function check($coupon, array $lines)
    {
        return app(CouponEligibility::class)->check($coupon->fresh(['campaign.targets']), collect($lines), self::SHIPPING);
    }

    public function test_percentage_is_capped_by_max_discount(): void
    {
        $p = $this->product($this->vendor(), 100_000_000); // 1,000,000 ل.س
        $c = $this->walletCoupon($this->campaign(['type' => 'percentage', 'value' => 20, 'max_discount' => 5_000_000]), $this->customer());

        $r = $this->check($c, [$this->line($p)]);

        $this->assertTrue($r->ok);
        $this->assertSame(5_000_000, $r->discount);
    }

    public function test_fixed_discount_never_exceeds_eligible_amount(): void
    {
        $p = $this->product($this->vendor(), 1_000_000);
        $c = $this->walletCoupon($this->campaign(['type' => 'fixed', 'value' => 9_000_000]), $this->customer());

        $this->assertSame(1_000_000, $this->check($c, [$this->line($p)])->discount);
    }

    public function test_min_order_not_met_explains_how_much_to_add(): void
    {
        $p = $this->product($this->vendor(), 8_500_000);
        $c = $this->walletCoupon($this->campaign(['min_order_amount' => 10_000_000]), $this->customer());

        $r = $this->check($c, [$this->line($p)]);

        $this->assertFalse($r->ok);
        $this->assertSame('أضف 15,000 ل.س لتستفيد', $r->reason);
        $this->assertSame(0, $r->discount);
    }

    public function test_category_scope_discounts_only_matching_lines(): void
    {
        $vendor = $this->vendor();
        $phones = Category::create(['name' => 'هواتف', 'slug' => 'phones']);
        $inScope = $this->product($vendor, 10_000_000, $phones);
        $outScope = $this->product($vendor, 10_000_000);
        $campaign = $this->campaign(['type' => 'percentage', 'value' => 10, 'scope' => 'categories']);
        CouponCampaignTarget::create(['campaign_id' => $campaign->id, 'target_type' => 'category', 'target_id' => $phones->id]);
        $c = $this->walletCoupon($campaign, $this->customer());

        $r = $this->check($c, [$this->line($inScope), $this->line($outScope)]);

        $this->assertSame(1_000_000, $r->discount);
        $this->assertSame([0 => 1_000_000], $r->lineDiscounts, 'الخصم على البند المشمول فقط');
    }

    public function test_out_of_scope_cart_gets_clear_reason(): void
    {
        $campaign = $this->campaign(['scope' => 'stores']);
        $other = $this->vendor();
        CouponCampaignTarget::create(['campaign_id' => $campaign->id, 'target_type' => 'store', 'target_id' => $other->id]);
        $c = $this->walletCoupon($campaign, $this->customer());

        $r = $this->check($c, [$this->line($this->product($this->vendor()))]);

        $this->assertFalse($r->ok);
        $this->assertSame("يعمل على منتجات {$other->store_name} فقط", $r->reason);
    }

    public function test_free_shipping_discounts_shipping_only(): void
    {
        $c = $this->walletCoupon($this->campaign(['type' => 'free_shipping', 'value' => 0]), $this->customer());

        $r = $this->check($c, [$this->line($this->product($this->vendor()))]);

        $this->assertTrue($r->ok);
        $this->assertSame(0, $r->discount);
        $this->assertSame(self::SHIPPING, $r->shippingDiscount);
    }

    public function test_line_discounts_sum_to_total_discount(): void
    {
        $vendor = $this->vendor();
        $c = $this->walletCoupon($this->campaign(['type' => 'fixed', 'value' => 1_000_000]), $this->customer());

        $r = $this->check($c, [$this->line($this->product($vendor, 3_000_000)), $this->line($this->product($vendor, 3_000_000)), $this->line($this->product($vendor, 3_000_000))]);

        $this->assertSame(1_000_000, array_sum($r->lineDiscounts));
    }

    public function test_expired_or_not_started_coupon_has_reason(): void
    {
        $line = $this->line($this->product($this->vendor()));
        $expired = $this->walletCoupon($this->campaign(), $this->customer(), ['expires_at' => now()->subHour()]);
        $future = $this->walletCoupon($this->campaign(['starts_at' => now()->addDays(2)]), $this->customer());

        $this->assertSame('انتهت صلاحيته', $this->check($expired, [$line])->reason);
        $this->assertStringStartsWith('يبدأ في', $this->check($future, [$line])->reason);
    }
}
