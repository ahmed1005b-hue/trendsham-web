<?php

namespace App\Http\Controllers\Api\Admin;

use App\Accounting\Audit;
use App\Accounting\LedgerType;
use App\Http\Controllers\Admin\Accounting\AccountingController as WebAccountingController;
use App\Models\LedgerEntry;
use App\Models\SellerAccount;
use App\Models\SellerProfile;
use App\Models\SellerStatement;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\Request;

/**
 * المحاسبة للتطبيق. نفس استعلامات الويب ونفس التدقيق (Audit)؛ exportCsv موروث كما هو.
 * المعرّف {seller} هنا هو معرّف المستخدم (users.id) وليس ملف البائع.
 */
class AccountingController extends WebAccountingController
{
    public function dashboard(Request $r)
    {
        [$from, $to] = $this->range($r);

        $byType = LedgerEntry::whereBetween('posted_at', [$from, $to])
            ->selectRaw('entry_type, SUM(debit) as debit, SUM(credit) as credit, COUNT(*) as n')
            ->groupBy('entry_type')->get()->keyBy('entry_type');

        Audit::log($r, 'view_dashboard');

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'sales' => (float) ($byType[LedgerType::SALE]->credit ?? 0),
                'commissions' => (float) ($byType[LedgerType::COMMISSION]->debit ?? 0) - (float) ($byType[LedgerType::COMM_REVERSAL]->credit ?? 0),
                'refunds' => (float) ($byType[LedgerType::REFUND]->debit ?? 0),
                'payouts' => (float) ($byType[LedgerType::PAYOUT]->debit ?? 0),
                'showcase' => (float) ($byType[LedgerType::SHOWCASE_FEE]->debit ?? 0) - (float) ($byType[LedgerType::SHOWCASE_REFUND]->credit ?? 0),
                'owed_to_sellers' => (float) SellerAccount::sum('balance'),
            ],
            'by_type' => $this->summarize($byType),
        ]);
    }

    public function sellers(Request $r)
    {
        $sellers = User::where('role', 'seller')
            ->leftJoin('seller_accounts', 'seller_accounts.seller_id', '=', 'users.id')
            ->leftJoin('seller_profiles', 'seller_profiles.user_id', '=', 'users.id')
            ->when($r->filled('q'), fn ($q) => $q->where(function ($w) use ($r) {
                $w->where('users.first_name', 'like', '%'.$r->q.'%')
                    ->orWhere('users.last_name', 'like', '%'.$r->q.'%')
                    ->orWhere('users.phone', 'like', '%'.$r->q.'%')
                    ->orWhere('users.email', 'like', '%'.$r->q.'%')
                    ->orWhere('seller_profiles.store_name', 'like', '%'.$r->q.'%');
            }))
            ->select('users.*', 'seller_accounts.balance as account_balance', 'seller_profiles.store_name')
            ->orderBy('users.first_name')->paginate(25);

        return response()->json([
            'data' => collect($sellers->items())->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'store_name' => $u->store_name,
                'balance' => (float) ($u->account_balance ?? 0),
            ]),
            'current_page' => $sellers->currentPage(),
            'last_page' => $sellers->lastPage(),
        ]);
    }

    public function show(Request $r, User $seller)
    {
        abort_unless($seller->role === 'seller', 404, 'المستخدم ليس بائعاً.');
        [$from, $to] = $this->range($r);

        $base = LedgerEntry::forSeller($seller->id)->whereBetween('posted_at', [$from, $to]);
        $summary = (clone $base)->selectRaw('entry_type, SUM(debit) as debit, SUM(credit) as credit, COUNT(*) as n')
            ->groupBy('entry_type')->get()->keyBy('entry_type');
        $entries = (clone $base)
            ->when($r->filled('type'), fn ($q) => $q->where('entry_type', $r->type))
            ->orderByDesc('id')->paginate(50);

        Audit::log($r, 'view_ledger', $seller->id);

        return response()->json([
            'seller' => [
                'id' => $seller->id,
                'name' => $seller->name,
                'phone' => $seller->phone,
                'store_name' => SellerProfile::where('user_id', $seller->id)->value('store_name'),
            ],
            'balance' => (float) (SellerAccount::where('seller_id', $seller->id)->value('balance') ?? 0),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => $this->summarize($summary),
            'entries' => collect($entries->items())->map(fn ($e) => [
                'id' => $e->id,
                'posted_at' => $e->posted_at?->toISOString(),
                'type' => $e->entry_type,
                'type_label' => LedgerType::label($e->entry_type),
                'description' => $e->description,
                'debit' => (float) $e->debit,
                'credit' => (float) $e->credit,
                'balance_after' => (float) $e->balance_after,
            ]),
            'entries_last_page' => $entries->lastPage(),
            'statements' => SellerStatement::where('seller_id', $seller->id)->latest()->limit(50)->get()
                ->map(fn ($s) => StatementController::present($s)),
            'types' => LedgerType::labels(),
        ]);
    }

    public function adjust(Request $r, User $seller, LedgerService $ledger)
    {
        parent::adjust($r, $seller, $ledger);

        return response()->json([
            'message' => 'تم تسجيل التسوية.',
            'balance' => (float) (SellerAccount::where('seller_id', $seller->id)->value('balance') ?? 0),
        ]);
    }

    private function range(Request $r): array
    {
        return [
            ($r->date('from') ?? now()->startOfMonth())->startOfDay(),
            ($r->date('to') ?? now())->endOfDay(),
        ];
    }

    private function summarize($byType): array
    {
        return $byType->map(fn ($row, $type) => [
            'type' => $type,
            'label' => LedgerType::label($type),
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
            'count' => (int) $row->n,
        ])->values()->all();
    }
}
