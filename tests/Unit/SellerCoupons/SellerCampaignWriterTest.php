<?php

namespace Tests\Unit\SellerCoupons;

use App\Coupons\SellerCampaignWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\SellerCoupons\SellerCouponTestHelpers;
use Tests\TestCase;

class SellerCampaignWriterTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_percent_above_fifty_waits_for_review(): void
    {
        $seller = $this->seller();
        $writer = app(SellerCampaignWriter::class);

        $this->assertSame('active', $writer->create($this->formData(['value' => 50]), $seller, $seller->user)->status);
        $this->assertSame('pending_review', $writer->create($this->formData(['value' => 51]), $seller, $seller->user)->status);
        $this->assertSame('active', $writer->create($this->formData(['type' => 'fixed', 'value' => 900000, 'max_discount' => null]), $seller, $seller->user)->status);
    }

    public function test_lira_inputs_are_stored_times_hundred(): void
    {
        $seller = $this->seller();
        $c = app(SellerCampaignWriter::class)->create($this->formData(['type' => 'fixed', 'value' => 25000, 'max_discount' => null]), $seller, $seller->user);

        $this->assertSame(2_500_000, $c->value);
        $this->assertNull($c->max_discount);
        $this->assertSame(5_000_000, $c->min_order_amount);
    }

    public function test_preview_amounts(): void
    {
        // 10% حتى 20,000 على طلب 300,000 بعمولة 10%: خصم 20,000، العميل 280,000، البائع 252,000
        $p = SellerCampaignWriter::previewAmounts('percentage', 10, 2_000_000, 30_000_000, 10);

        $this->assertSame(['discount' => 2_000_000, 'customer_pays' => 28_000_000, 'seller_gets' => 25_200_000], $p);
    }
}
