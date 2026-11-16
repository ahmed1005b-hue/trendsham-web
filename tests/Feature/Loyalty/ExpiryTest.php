<?php

namespace Tests\Feature\Loyalty;

use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** US3: انتهاء الصلاحية بعد 12 شهراً، والتنبيه قبل 30 يوماً مرة واحدة */
class ExpiryTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_daily_job_expires_points_older_than_12_months(): void
    {
        $u = $this->customer();
        $this->travel(-13)->months();
        $this->deliver($this->order($u, 5_000_000));   // +5 قديمة
        $this->travelBack();
        $this->deliver($this->order($u, 3_000_000));   // +3 حديثة

        $this->artisan('loyalty:daily')->assertSuccessful();

        $this->assertSame(3, $this->balance($u));
        $this->assertSame(-5, (int) Tx::where('user_id', $u->id)->where('type', Tx::EXPIRE)->sum('points'));
        $this->assertLedgerConsistent($u);
    }

    public function test_redemption_uses_oldest_points_first(): void
    {
        $u = $this->customer();
        $this->travel(-6)->months();
        $old = app(LoyaltyLedger::class)->credit($u->id, Tx::ADMIN_ADD, 10, reason: 'أقدم');
        $this->travelBack();
        $new = app(LoyaltyLedger::class)->credit($u->id, Tx::ADMIN_ADD, 10, reason: 'أحدث');

        app(\App\Loyalty\PointsCouponIssuer::class)->convert($u, 10);

        $this->assertSame(0, $old->fresh()->remaining);
        $this->assertSame(10, $new->fresh()->remaining);
    }

    public function test_expiry_warning_is_sent_once(): void
    {
        $u = $this->customer();
        $this->travel(-340)->days();
        $this->grant($u, 12);
        $this->travelBack();

        $this->artisan('loyalty:daily');
        $this->artisan('loyalty:daily');

        $this->assertSame(1, AppNotification::where('user_id', $u->id)->where('type', 'loyalty_expiring')->count());
        $this->assertSame(12, $this->balance($u));   // لم تنتهِ بعد
    }
}
