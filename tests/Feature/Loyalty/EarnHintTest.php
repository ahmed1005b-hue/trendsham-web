<?php

namespace Tests\Feature\Loyalty;

use App\Models\CartItem;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US4: "ستكسب X نقاط" في صفحة المنتج والسلة (الموقع والتطبيق) */
class EarnHintTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_cart_shows_points_or_what_is_missing(): void
    {
        $u = $this->customer();
        $p = $this->product(price: 1_300_000);   // 13,000
        CartItem::create(['user_id' => $u->id, 'product_id' => $p->id, 'quantity' => 2, 'unit_price' => $p->price]);   // 26,000

        $this->actingAs($u)->get(route('customer.cart.index'))->assertOk()->assertSee('ستكسب نقطتين');
        $this->actingAs($u, 'sanctum')->getJson('/api/v1/cart')->assertJsonPath('will_earn_points', 2);

        $u2 = $this->customer();
        $cheap = $this->product(price: 800_000);   // 8,000
        CartItem::create(['user_id' => $u2->id, 'product_id' => $cheap->id, 'quantity' => 1, 'unit_price' => $cheap->price]);
        $this->actingAs($u2)->get(route('customer.cart.index'))->assertSee('أضف 2,000 ل.س لتكسب أول نقطة');
        $this->actingAs($u2, 'sanctum')->getJson('/api/v1/cart')
            ->assertJsonPath('will_earn_points', 0)->assertJsonPath('points_hint', 'أضف 2,000 ل.س لتكسب أول نقطة');
    }

    public function test_product_page_and_api_show_points(): void
    {
        $p = $this->product(price: 3_500_000);   // 35,000 → 3

        $this->get(route('products.show', $p))->assertOk()->assertSee('ستكسب 3 نقاط');
        $this->getJson('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.will_earn_points', 3);
    }

    public function test_nothing_when_program_is_disabled(): void
    {
        PlatformSetting::current()->update(['loyalty_enabled' => false]);
        $p = $this->product(price: 3_500_000);

        $this->get(route('products.show', $p))->assertOk()->assertDontSee('ستكسب');
    }
}
