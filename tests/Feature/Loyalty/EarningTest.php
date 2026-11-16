<?php

namespace Tests\Feature\Loyalty;

use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\AppNotification;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US1: كسب النقاط عند التسليم وسحبها عند الإرجاع */
class EarningTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_delivered_order_earns_one_point_per_10000_paid_excluding_shipping(): void
    {
        $u = $this->customer();
        $order = $this->deliver($this->order($u, 4_750_000));   // 47,500 ل.س + شحن

        $this->assertSame(4, $this->balance($u));
        $lot = Tx::where('type', Tx::EARN)->where('order_id', $order->id)->firstOrFail();
        $this->assertTrue($lot->expires_at->between(now()->addMonths(12)->subMinute(), now()->addMonths(12)->addMinute()));
        $this->assertTrue(AppNotification::where('user_id', $u->id)->where('type', 'loyalty_earned')->exists());
    }

    public function test_delivering_twice_earns_once(): void
    {
        $u = $this->customer();
        $order = $this->deliver($this->order($u, 4_750_000));
        $order->changeStatus('shipped');
        $order->changeStatus('delivered');

        $this->assertSame(4, $this->balance($u));
        $this->assertSame(1, Tx::where('type', Tx::EARN)->count());
    }

    public function test_points_are_on_the_amount_paid_after_coupon(): void
    {
        $u = $this->customer();
        $this->deliver($this->order($u, 4_750_000, ['discount_amount' => 500_000]));   // 42,500 بعد الكوبون

        $this->assertSame(4, $this->balance($u));
    }

    public function test_cancelled_before_delivery_earns_nothing(): void
    {
        $u = $this->customer();
        $this->order($u, 9_000_000)->changeStatus('cancelled');

        $this->assertSame(0, $this->balance($u));
        $this->assertSame(0, Tx::count());
    }

    public function test_returned_order_takes_points_back_as_debt_if_spent_then_next_earn_pays_it(): void
    {
        $u = $this->customer();
        $order = $this->deliver($this->order($u, 5_000_000));   // +5
        app(\App\Loyalty\LoyaltyLedger::class)->debit($u->id, Tx::ADMIN_DEDUCT, 4, reason: 'صرفها');

        $order->changeStatus('cancelled');   // إرجاع بعد التسليم

        $this->assertSame(0, $this->balance($u));
        $this->assertSame(4, $this->debt($u));
        $this->assertTrue(AppNotification::where('user_id', $u->id)->where('type', 'loyalty_reversed')->exists());

        $this->deliver($this->order($u, 6_000_000));   // +6، منها 4 سداد
        $this->assertSame(2, $this->balance($u));
        $this->assertSame(0, $this->debt($u));
        $this->assertLedgerConsistent($u);
    }

    public function test_sellers_and_admins_do_not_earn(): void
    {
        foreach (['seller', 'admin'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->deliver($this->order($u, 9_000_000));
            $this->assertSame(0, $this->balance($u), $role);
        }
    }

    public function test_disabled_program_earns_nothing(): void
    {
        PlatformSetting::current()->update(['loyalty_enabled' => false]);
        $u = $this->customer();
        $this->deliver($this->order($u, 9_000_000));

        $this->assertSame(0, $this->balance($u));
    }
}
