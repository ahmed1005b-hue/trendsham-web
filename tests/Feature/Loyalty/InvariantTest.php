<?php

namespace Tests\Feature\Loyalty;

use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyAccount;
use App\Loyalty\PointsCouponIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SC-004: بعد سيناريو مختلط لعدة عملاء، رصيد كل عميل يطابق مجموع سجله */
class InvariantTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    public function test_balance_minus_debt_equals_sum_of_history_for_every_customer(): void
    {
        $ledger = app(LoyaltyLedger::class);
        $a = $this->customer();
        $b = $this->customer();

        $this->travel(-13)->months();
        $this->deliver($this->order($a, 6_000_000));             // +6 ستنتهي
        $this->travelBack();
        $returned = $this->deliver($this->order($a, 9_000_000)); // +9
        $this->grant($a, 15);
        app(PointsCouponIssuer::class)->convert($a, 10);
        $returned->changeStatus('cancelled');                      // سحب 9 (قد يصبح ديناً)
        $this->deliver($this->order($b, 25_000_000));            // +25
        $ledger->adjust($b, 'deduct', 5, 'تصحيح', $a->id);
        $this->artisan('loyalty:daily');

        foreach (LoyaltyAccount::pluck('user_id') as $id) {
            $this->assertLedgerConsistent(\App\Models\User::find($id));
        }
        $this->assertSame(20, $this->balance($b));
    }
}
