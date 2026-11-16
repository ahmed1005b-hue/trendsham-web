<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\CouponEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** إيقاف المتجر وحذف منتج مشمول */
class StoreSuspensionTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    public function test_suspended_store_coupons_are_invalid_and_not_collectable(): void
    {
        $seller = $this->seller();
        $p = $this->product($seller);
        $c = $this->campaign($seller);
        $u = $this->customer();
        $copy = $this->walletCoupon($c, $u);

        $seller->update(['status' => 'suspended']);

        $res = app(CouponEligibility::class)->check($copy->fresh('campaign.seller'), collect([(object) ['product' => $p, 'subtotal' => $p->price]]), $this->shipping);
        $this->assertFalse($res->ok);
        $this->actingAs($this->customer(), 'sanctum')->postJson("/api/v1/coupons/{$c->id}/collect")->assertNotFound();
        $this->assertTrue(app(\App\Coupons\StoreCoupons::class)->forStore($seller->fresh(), $u)->isEmpty());
    }

    public function test_deleted_product_drops_out_of_scope(): void
    {
        $seller = $this->seller();
        $a = $this->product($seller);
        $b = $this->product($seller);
        $c = $this->campaign($seller, ['scope' => 'products']);
        $c->targets()->createMany([['target_type' => 'product', 'target_id' => $a->id], ['target_type' => 'product', 'target_id' => $b->id]]);

        $a->delete();

        $rows = app(\App\Coupons\StoreCoupons::class)->forProduct($b->fresh(), null);
        $this->assertSame([$c->id], $rows->map(fn ($r) => $r['campaign']->id)->all());
        $this->getJson("/api/v1/products/{$b->slug}")->assertJsonPath('data.store_coupons.0.id', $c->id);
    }
}
