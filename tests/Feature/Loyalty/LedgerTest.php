<?php

namespace Tests\Feature\Loyalty;

use App\Loyalty\LoyaltyException;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private function ledger(): LoyaltyLedger
    {
        return app(LoyaltyLedger::class);
    }

    public function test_debit_consumes_oldest_expiring_lots_first_and_records_allocation(): void
    {
        $u = $this->customer();
        $this->travel(-6)->months();
        $old = $this->ledger()->credit($u->id, Tx::ADMIN_ADD, 10, reason: 'قديمة');
        $this->travelBack();
        $new = $this->ledger()->credit($u->id, Tx::ADMIN_ADD, 10, reason: 'جديدة');

        $debit = $this->ledger()->debit($u->id, Tx::ADMIN_DEDUCT, 12, reason: 'x');

        $this->assertSame(0, $old->fresh()->remaining);
        $this->assertSame(8, $new->fresh()->remaining);
        $this->assertSame([10, 2], $debit->allocations()->orderBy('id')->pluck('points')->all());
        $this->assertSame(8, $this->balance($u));
        $this->assertLedgerConsistent($u);
    }

    public function test_restore_returns_points_to_the_same_lots_and_expires_dead_ones(): void
    {
        $u = $this->customer();
        $this->travel(-11)->months();
        $old = $this->ledger()->credit($u->id, Tx::ADMIN_ADD, 10, reason: 'تنتهي قريباً');
        $this->travelBack();
        $new = $this->ledger()->credit($u->id, Tx::ADMIN_ADD, 10, reason: 'حديثة');
        $debit = $this->ledger()->debit($u->id, Tx::ADMIN_DEDUCT, 15, reason: 'x');

        $this->travel(2)->months();   // الدفعة القديمة انتهت الآن
        $live = $this->ledger()->restoreAllocations($debit);

        $this->assertSame(5, $live);
        $this->assertSame(10, $new->fresh()->remaining);
        $this->assertSame(10, $this->balance($u));
        $this->assertSame(0, $this->ledger()->restoreAllocations($debit), 'restore twice must be a no-op');
        $this->assertLedgerConsistent($u);
    }

    public function test_debit_beyond_balance_is_refused_unless_debt_is_allowed(): void
    {
        $u = $this->customer();
        $this->grant($u, 5);

        try {
            $this->ledger()->debit($u->id, Tx::ADMIN_DEDUCT, 6, reason: 'x');
            $this->fail('expected refusal');
        } catch (LoyaltyException) {
        }
        $this->assertSame(5, $this->balance($u));

        $this->ledger()->debit($u->id, Tx::REVERSE_EARN, 8, allowDebt: true);
        $this->assertSame(0, $this->balance($u));
        $this->assertSame(3, $this->debt($u));
        $this->assertLedgerConsistent($u);
    }

    public function test_debt_is_settled_first_from_the_next_credit(): void
    {
        $u = $this->customer();
        $this->ledger()->debit($u->id, Tx::REVERSE_EARN, 3, allowDebt: true);

        $this->ledger()->credit($u->id, Tx::EARN, 5);

        $this->assertSame(2, $this->balance($u));
        $this->assertSame(0, $this->debt($u));
        $this->assertLedgerConsistent($u);
    }

    public function test_expired_lots_are_expired_and_never_spent(): void
    {
        $u = $this->customer();
        $this->travel(-13)->months();
        $this->grant($u, 7);
        $this->travelBack();
        $this->grant($u, 4);

        $this->assertSame(7, $this->ledger()->expireDue());
        $this->assertSame(4, $this->balance($u));
        $this->assertSame(1, Tx::where('user_id', $u->id)->where('type', Tx::EXPIRE)->count());
        $this->assertLedgerConsistent($u);
    }

    public function test_once_key_makes_operations_idempotent(): void
    {
        $u = $this->customer();
        $this->assertNotNull($this->ledger()->credit($u->id, Tx::EARN, 4, onceKey: 'earn:1'));
        $this->assertNull($this->ledger()->credit($u->id, Tx::EARN, 4, onceKey: 'earn:1'));
        $this->assertSame(4, $this->balance($u));
    }
}
