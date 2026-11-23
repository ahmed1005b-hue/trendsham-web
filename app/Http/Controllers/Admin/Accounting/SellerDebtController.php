<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Accounting\SellerDebtPayments;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function pay(Request $request, SellerProfile $sellerProfile, SellerDebtPayments $payments): RedirectResponse
    {
        $data = $request->validate(SellerDebtPayments::rules());
        $payments->record($request, $request->user(), $sellerProfile, (float) $data['amount'], $data['note'] ?? null);

        return back()->with('success', __('تم تسجيل التسديد وإضافته لرصيد المتجر.'));
    }
}
