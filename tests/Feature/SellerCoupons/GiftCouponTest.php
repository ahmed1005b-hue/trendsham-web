<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\CouponEligibility;
use App\Coupons\Models\CouponCampaign;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US6: كوبون منتج هدية */
class GiftCouponTest extends TestCase
{
    use RefreshDatabase, SellerCouponTestHelpers;

    private function giftCampaign($seller, $buy, $gift): CouponCampaign
    {
        return $this->campaign($seller, ['type' => 'gift', 'value' => 0, 'buy_product_id' => $buy->id, 'gift_product_id' => $gift->id]);
    }

    public function test_seller_creates_gift_coupon_from_own_simple_products_only(): void
    {
        $seller = $this->seller();
        $buy = $this->product($seller);
        $gift = $this->product($seller, 500_000);
        $foreign = $this->product($this->seller());
        $withVariants = $this->product($seller);
        ProductVariant::create(['product_id' => $withVariants->id, 'color' => '#000000', 'size' => 'M', 'stock_quantity' => 3, 'is_active' => true]);
        $base = $this->formData(['type' => 'gift', 'value' => null, 'max_discount' => null]);

        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $base + ['buy_product_id' => $buy->id, 'gift_product_id' => $foreign->id])
            ->assertSessionHasErrors('gift_product_id');
        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $base + ['buy_product_id' => $buy->id, 'gift_product_id' => $withVariants->id])
            ->assertSessionHasErrors('gift_product_id');
        $this->actingAs($seller->user)->post(route('seller.coupons.store'), $base + ['buy_product_id' => $buy->id, 'gift_product_id' => $gift->id])
            ->assertSessionHasNoErrors();

        $c = CouponCampaign::sole();
        $this->assertSame('gift', $c->type);
        $this->assertSame($gift->id, $c->gift_product_id);
        $this->assertStringContainsString($gift->name, $c->valueLabel());
    }

    public function test_eligibility_needs_buy_product_and_gift_stock(): void
    {
        $seller = $this->seller();
        $buy = $this->product($seller, 10_000_000);
        $gift = $this->product($seller, 500_000, stock: 1);
        $other = $this->product($seller);
        $u = $this->customer();
        $coupon = $this->walletCoupon($this->giftCampaign($seller, $buy, $gift), $u)->fresh('campaign.seller');
        $line = fn ($p) => (object) ['product' => $p, 'subtotal' => $p->price];
        $elig = app(CouponEligibility::class);

        $ok = $elig->check($coupon, collect([$line($buy)]), $this->shipping);
        $this->assertTrue($ok->ok);
        $this->assertSame(500_000, $ok->saving());
        $this->assertSame($gift->id, $ok->giftProductId);

        $no = $elig->check($coupon, collect([$line($other)]), $this->shipping);
        $this->assertFalse($no->ok);
        $this->assertSame('اشترِ '.$buy->name.' لتحصل على الهدية', $no->reason);

        $gift->update(['stock_quantity' => 0]);
        $this->assertSame('الهدية نفدت', $elig->check($coupon->fresh('campaign.seller'), collect([$line($buy)]), $this->shipping)->reason);
    }

    public function test_checkout_adds_free_gift_line_and_cancel_restocks(): void
    {
        $seller = $this->seller();
        $buy = $this->product($seller, 10_000_000);
        $gift = $this->product($seller, 500_000, stock: 5);
        $u = $this->customer();
        $this->walletCoupon($this->giftCampaign($seller, $buy, $gift), $u);
        $this->cartLine($u, $buy);

        $order = $this->checkout($u);

        $giftLine = $order->items->firstWhere('is_gift', true);
        $this->assertNotNull($giftLine);
        $this->assertSame($gift->id, $giftLine->product_id);
        $this->assertSame(0, (int) $giftLine->unit_price);
        $this->assertSame(0, (int) $giftLine->subtotal);
        $this->assertSame(4, $gift->fresh()->stock_quantity);
        $this->assertSame(10_000_000 + $this->shipping, $order->total, 'الهدية لا تغيّر المبلغ');
        $this->actingAs($u)->get(route('customer.orders.show', $order))->assertSee('هدية');

        $this->deliver($order);
        $this->assertSame(0, (int) $giftLine->fresh()->vendor_earning);
        $this->assertSame(9_000_000, (int) $seller->wallet()->first()->balance);

        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(5, $gift->fresh()->stock_quantity);
    }

    public function test_api_checkout_adds_gift_line(): void
    {
        $seller = $this->seller();
        $buy = $this->product($seller, 10_000_000);
        $gift = $this->product($seller, 500_000, stock: 2);
        $u = $this->customer();
        $this->walletCoupon($this->giftCampaign($seller, $buy, $gift), $u);
        $this->cartLine($u, $buy);

        $this->actingAs($u, 'sanctum')->postJson('/api/v1/checkout', [
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ])->assertSuccessful();

        $order = $u->orders()->latest('id')->first();
        $this->assertTrue($order->items->contains(fn ($i) => $i->is_gift && $i->product_id === $gift->id));
        $this->assertSame(1, $gift->fresh()->stock_quantity);
    }

    public function test_item_fully_covered_by_seller_coupon_settles_without_error(): void
    {
        $seller = $this->seller();
        $p = $this->product($seller, 1_000_000);
        $u = $this->customer();
        $this->walletCoupon($this->campaign($seller, ['value' => 1_000_000]), $u);
        $this->cartLine($u, $p);

        $order = $this->checkout($u);
        $this->assertSame(1_000_000, $order->discount_amount);

        $this->deliver($order);
        $this->assertSame('delivered', $order->fresh()->status);
        $order->fresh()->changeStatus('cancelled');
        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
