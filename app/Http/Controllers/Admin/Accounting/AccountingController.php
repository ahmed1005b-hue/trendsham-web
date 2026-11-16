<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Accounting\Audit;
use App\Accounting\LedgerType;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\SellerAccount;
use App\Models\SellerProfile;
use App\Models\SellerStatement;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    private function ensureSeller(User $seller): void
    {
        abort_unless($seller->role === 'seller', 404, 'المستخدم ليس بائعاً.');
    }

    /** إجماليات عامة للمنصة (أرقام مجمّعة فقط، بدون تفاصيل بائع). */
    public function dashboard(Request $r)
    {
        $from = ($r->date('from') ?? now()->startOfMonth())->startOfDay();
        $to   = ($r->date('to') ?? now())->endOfDay();

        $byType = LedgerEntry::whereBetween('posted_at', [$from, $to])
            ->selectRaw('entry_type, SUM(debit) as debit, SUM(credit) as credit, COUNT(*) as n')
            ->groupBy('entry_type')->get()->keyBy('entry_type');

        $totals = [
            'sales'       => (float) ($byType[LedgerType::SALE]->credit ?? 0),
            'commissions' => (float) ($byType[LedgerType::COMMISSION]->debit ?? 0) - (float) ($byType[LedgerType::COMM_REVERSAL]->credit ?? 0),
            'refunds'     => (float) ($byType[LedgerType::REFUND]->debit ?? 0),
            'payouts'     => (float) ($byType[LedgerType::PAYOUT]->debit ?? 0),
            'showcase'    => (float) ($byType[LedgerType::SHOWCASE_FEE]->debit ?? 0) - (float) ($byType[LedgerType::SHOWCASE_REFUND]->credit ?? 0),
            'owed_to_sellers' => (float) SellerAccount::sum('balance'),
        ];

        Audit::log($r, 'view_dashboard');

        return view('admin.accounting.dashboard', compact('totals', 'byType', 'from', 'to'));
    }

    public function sellers(Request $r)
    {
        $sellers = User::where('role', 'seller')
            ->leftJoin('seller_accounts', 'seller_accounts.seller_id', '=', 'users.id')
            ->when($r->filled('q'), fn($q) => $q->where(function ($w) use ($r) {
                $w->where('users.first_name', 'like', '%' . $r->q . '%')
                    ->orWhere('users.last_name', 'like', '%' . $r->q . '%')
                    ->orWhere('users.phone', 'like', '%' . $r->q . '%')
                    ->orWhere('users.email', 'like', '%' . $r->q . '%');
            }))
            ->select('users.*', 'seller_accounts.balance as account_balance')
            ->orderBy('users.first_name')->paginate(25)->withQueryString();

        return view('admin.accounting.sellers', compact('sellers'));
    }

    /** ملف البائع: ملخص + حركات + كشوف. كل الاستعلامات مقيّدة بهذا البائع. */
    public function show(Request $r, User $seller)
    {
        $this->ensureSeller($seller);

        $from = ($r->date('from') ?? now()->startOfMonth())->startOfDay();
        $to   = ($r->date('to') ?? now())->endOfDay();

        $base = LedgerEntry::forSeller($seller->id)->whereBetween('posted_at', [$from, $to]);

        $summary = (clone $base)->selectRaw('entry_type, SUM(debit) as debit, SUM(credit) as credit, COUNT(*) as n')
            ->groupBy('entry_type')->get()->keyBy('entry_type');

        $entries = (clone $base)
            ->when($r->filled('type'), fn($q) => $q->where('entry_type', $r->type))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        $account    = SellerAccount::where('seller_id', $seller->id)->first();
        $statements = SellerStatement::where('seller_id', $seller->id)->latest()->limit(50)->get();
        $storeName  = SellerProfile::where('user_id', $seller->id)->value('store_name');

        Audit::log($r, 'view_ledger', $seller->id);

        return view('admin.accounting.seller', [
            'seller' => $seller,
            'account' => $account,
            'summary' => $summary,
            'entries' => $entries,
            'statements' => $statements,
            'from' => $from,
            'to' => $to,
            'labels' => LedgerType::labels(),
            'storeName' => $storeName,
        ]);
    }

    /** تصدير CSV لقيود بائع واحد (مع BOM ليفتح العربي سليماً في Excel). */
    public function exportCsv(Request $r, User $seller)
    {
        $this->ensureSeller($seller);

        $from = ($r->date('from') ?? now()->startOfMonth())->startOfDay();
        $to   = ($r->date('to') ?? now())->endOfDay();

        Audit::log($r, 'export_csv', $seller->id);

        return response()->streamDownload(function () use ($seller, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['التاريخ', 'النوع', 'البيان', 'مدين', 'دائن', 'الرصيد']);

            LedgerEntry::forSeller($seller->id)->whereBetween('posted_at', [$from, $to])
                ->orderBy('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $e) {
                        fputcsv($out, [
                            $e->posted_at->format('Y-m-d H:i'),
                            LedgerType::label($e->entry_type),
                            $e->description,
                            $e->debit,
                            $e->credit,
                            $e->balance_after,
                        ]);
                    }
                });
            fclose($out);
        }, "ledger-seller-{$seller->id}-" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** تسوية يدوية: سبب إلزامي وتُسجَّل باسم المدير وتُدقَّق. */
    public function adjust(Request $r, User $seller, LedgerService $ledger)
    {
        $this->ensureSeller($seller);

        $data = $r->validate([
            'direction' => 'required|in:credit,debit',
            'amount'    => 'required|numeric|min:0.01|max:1000000000',
            'reason'    => 'required|string|min:5|max:200',
        ]);

        $ledger->adjustment($seller->id, $data['direction'], (float) $data['amount'], $data['reason'], $r->user()->id);
        Audit::log($r, 'adjustment', $seller->id);

        return back()->with('success', 'تم تسجيل التسوية.');
    }
}
