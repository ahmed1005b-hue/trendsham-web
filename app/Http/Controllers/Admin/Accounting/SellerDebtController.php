<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Accounting\Audit;
use App\Accounting\LedgerType;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\SellerProfile;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * ديون البائعين للمنصة: متاجر وصّلت بنفسها وحصّلت المال نقداً فبقيت عليها العمولة.
 * عندما يسدّد البائع (تحويل أو نقداً) يسجّل المدير المبلغ هنا فيعود رصيده ويعمل توصيله الخاص.
 */
class SellerDebtController extends Controller
{
    public function index(): View
    {
        $wallets = Wallet::with('vendor.user', 'vendor.shippingSetting')
            ->where('balance', '<', 0)
            ->orderBy('balance')
            ->paginate(30);

        return view('admin.accounting.debts', [
            'wallets' => $wallets,
            'total' => (int) -Wallet::where('balance', '<', 0)->sum('balance'),
        ]);
    }

    public function pay(Request $request, SellerProfile $sellerProfile, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000000'],
            'note' => ['nullable', 'string', 'max:150'],
        ]);
        $minor = (int) round($data['amount'] * 100);
        $note = trim((string) ($data['note'] ?? ''));

        DB::transaction(function () use ($sellerProfile, $minor, $note, $request, $ledger) {
            $tx = $sellerProfile->walletOrCreate()->applyTransaction('credit', $minor, 'adjustment', [
                'note' => 'تسديد دين للمنصة'.($note !== '' ? ': '.$note : ''),
            ]);
            $ledger->post($sellerProfile->user_id, LedgerType::DEBT_PAYMENT, 0, $minor / 100,
                'تسديد دين للمنصة'.($note !== '' ? ': '.$note : ''), 'wallet_transaction', $tx->id, $request->user()->id);
        });
        Audit::log($request, 'debt_payment', $sellerProfile->user_id);

        AppNotification::send($sellerProfile->user_id, 'debt_paid', __('استلمنا تسديدك'),
            __('سجّلنا :amount ل.س تسديداً لدينك للمنصة. شكراً لك.', ['amount' => number_format($minor / 100)]),
            route('seller.wallet.index'));

        return back()->with('success', __('تم تسجيل التسديد وإضافته لرصيد المتجر.'));
    }
}
