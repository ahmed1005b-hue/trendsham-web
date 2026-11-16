<?php

namespace Tests\Feature\Loyalty;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US3: صفحة "نقاطي" — نفس الأرقام في الموقع والتطبيق */
class MyPointsTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_web_page_and_api_show_the_same_balance_pending_and_history(): void
    {
        $u = $this->customer();
        $this->deliver($this->order($u, 4_750_000));        // +4
        $this->order($u, 3_000_000, ['status' => 'shipped']);   // معلّقة 3
        $this->grant($u, 10);

        $this->actingAs($u)->get(route('customer.loyalty.index'))->assertOk()
            ->assertSee('14')->assertSee('معلّقة')->assertSee('من الطلب')->assertSee('2,800 ل.س', false);

        $api = $this->actingAs($u, 'sanctum')->getJson('/api/v1/loyalty')->assertOk();
        $api->assertJsonPath('balance', 14)->assertJsonPath('pending', 3)
            ->assertJsonPath('point_value', 200)->assertJsonPath('spend_per_point', 10000)->assertJsonPath('min_redeem', 10);

        $tx = $this->getJson('/api/v1/loyalty/transactions')->assertOk()->json('data');
        $this->assertCount(2, $tx);
        $this->assertSame(14, array_sum(array_column($tx, 'points')));
    }

    public function test_expiring_soon_warning_is_shown(): void
    {
        $u = $this->customer();
        $this->travel(-345)->days();
        $this->grant($u, 12);
        $this->travelBack();

        $this->actingAs($u)->get(route('customer.loyalty.index'))->assertSee('تنتهي خلال 30 يوماً');
        $this->actingAs($u, 'sanctum')->getJson('/api/v1/loyalty')->assertJsonPath('expiring_soon.points', 12);
    }

    public function test_customer_sees_only_own_history_and_menu_link(): void
    {
        $a = $this->customer();
        $b = $this->customer();
        $this->grant($b, 50);

        $this->actingAs($a)->get(route('customer.loyalty.index'))->assertOk()
            ->assertSee('لا توجد حركات بعد')->assertSee(route('customer.loyalty.index'), false);
        $this->actingAs($a, 'sanctum')->getJson('/api/v1/loyalty/transactions')->assertJsonCount(0, 'data');
    }

    public function test_order_page_shows_points_discount_and_earned(): void
    {
        $u = $this->customer();
        $order = $this->deliver($this->order($u, 5_000_000, ['points_redeemed' => 10, 'points_discount' => 200_000]));

        $this->actingAs($u)->get(route('customer.orders.show', $order))->assertOk()
            ->assertSee('خصم النقاط')->assertSee('النقاط المكتسبة')
            ->assertDontSee('@php', false)->assertDontSee('@endif', false)->assertDontSee('{{', false);
    }
}
