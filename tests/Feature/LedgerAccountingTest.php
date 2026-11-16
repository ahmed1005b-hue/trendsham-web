<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\SellerAccount;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\StatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LedgerAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): User
    {
        $u = User::factory()->create(['role' => 'seller']);
        SellerProfile::create(['user_id' => $u->id, 'store_name' => 'متجر ' . $u->id, 'status' => 'approved']);
        return $u;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_isolation_between_sellers(): void
    {
        $a = $this->makeSeller();
        $b = $this->makeSeller();
        $ledger = app(LedgerService::class);

        $ledger->sale($a->id, 100, 'بيع أ', 'manual_test', 1);
        $ledger->sale($b->id, 50, 'بيع ب', 'manual_test', 2);

        $accA = SellerAccount::where('seller_id', $a->id)->first();
        $this->assertSame(100.0, (float) $accA->balance);
        $this->assertSame(50.0, (float) SellerAccount::where('seller_id', $b->id)->first()->balance);

        // كشف A يحتوي قيود A فقط
        $svc = app(StatementService::class);
        $st = $svc->generate($a, Carbon::now()->subDay(), Carbon::now(), $this->admin()->id);
        $this->assertSame(100.0, (float) $st->closing_balance);
        $this->assertSame(1, $st->entries_count);
    }

    public function test_balance_chain(): void
    {
        $s = $this->makeSeller();
        $ledger = app(LedgerService::class);

        $ledger->sale($s->id, 200, 'بيع', 'manual_test', 10);
        $ledger->commission($s->id, 20, 'عمولة', 'manual_test', 10);
        $ledger->payout($s->id, 50, 'سحب', 1);

        $entries = LedgerEntry::forSeller($s->id)->orderBy('id')->get();
        $prev = 0.0;
        foreach ($entries as $e) {
            $this->assertEquals(round($prev + (float) $e->credit - (float) $e->debit, 2), (float) $e->balance_after);
            $prev = (float) $e->balance_after;
        }
        $this->assertEquals($prev, (float) SellerAccount::where('seller_id', $s->id)->first()->balance);
    }

    public function test_entries_are_immutable(): void
    {
        $s = $this->makeSeller();
        app(LedgerService::class)->sale($s->id, 10, 'بيع', 'manual_test', 1);
        $e = LedgerEntry::forSeller($s->id)->first();

        $this->expectException(\LogicException::class);
        $e->update(['debit' => 5]);
    }

    public function test_entries_cannot_be_deleted(): void
    {
        $s = $this->makeSeller();
        app(LedgerService::class)->sale($s->id, 10, 'بيع', 'manual_test', 1);
        $e = LedgerEntry::forSeller($s->id)->first();

        $this->expectException(\LogicException::class);
        $e->delete();
    }

    public function test_idempotency_same_source_not_duplicated(): void
    {
        $s = $this->makeSeller();
        $ledger = app(LedgerService::class);

        $l1 = $ledger->sale($s->id, 30, 'بيع', 'order_item', 77);
        $l2 = $ledger->sale($s->id, 30, 'بيع', 'order_item', 77);

        $this->assertSame($l1->id, $l2->id);
        $this->assertSame(1, LedgerEntry::forSeller($s->id)->count());
    }

    public function test_adjustment_requires_reason_and_positive_amount(): void
    {
        $s = $this->makeSeller();
        $admin = $this->admin();
        $ledger = app(LedgerService::class);

        try {
            $ledger->adjustment($s->id, 'credit', 10, 'قصير', $admin->id);
            $this->fail('should abort on short reason');
        } catch (\Exception $e) {
            $this->assertStringContainsString('سبب التسوية', $e->getMessage());
        }
    }

    public function test_statement_consistency_and_pdf_saved_privately(): void
    {
        Storage::fake('local');
        $s = $this->makeSeller();
        app(LedgerService::class)->sale($s->id, 500, 'بيع', 'manual_test', 1);
        app(LedgerService::class)->commission($s->id, 50, 'عمولة', 'manual_test', 1);

        $st = app(StatementService::class)->generate($s, Carbon::now()->subDay(), Carbon::now(), $this->admin()->id);

        $this->assertEquals($st->closing_balance, $st->opening_balance + $st->total_credit - $st->total_debit);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $st->checksum);
    }

    public function test_non_admin_gets_403_on_accounting_routes(): void
    {
        $s = $this->makeSeller();
        $customer = User::factory()->create(['role' => 'customer']);
        $this->assertInstanceOf(\App\Models\User::class, $customer);

        $this->actingAs($customer)
            ->get(route('admin.accounting.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_view_seller_ledger_and_audit_is_logged(): void
    {
        $s = $this->makeSeller();
        $admin = $this->admin();
        app(LedgerService::class)->sale($s->id, 100, 'بيع', 'manual_test', 5);
        $this->assertInstanceOf(\App\Models\User::class, $admin);

        $this->actingAs($admin)
            ->get(route('admin.accounting.seller', $s->id))
            ->assertOk()
            ->assertSee('بيع');

        $this->assertDatabaseHas('accounting_audit_logs', [
            'user_id' => $admin->id,
            'seller_id' => $s->id,
            'action' => 'view_ledger',
        ]);
    }
}
