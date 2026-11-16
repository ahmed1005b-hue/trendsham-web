<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WalletController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        $wallet = $vendor->walletOrCreate();

        return view('seller.wallet.index', [
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->latest()->paginate(15, ['*'], 'tx_page'),
            'pendingWithdrawals' => $vendor->withdrawalRequests()->where('status', 'pending')->sum('amount'),
            'available' => $this->payouts->available($vendor),
            'withdrawals' => $vendor->withdrawalRequests()->latest()->limit(10)->get(),
            'paymentMethods' => $vendor->paymentMethods()->latest()->get(),
            'defaultMethod' => $vendor->paymentMethods()->where('is_default', true)->first(),
            'minAmount' => (int) config('payment.payout.min_amount'),
        ]);
    }

    public function paymentMethods(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        return view('seller.wallet.payment-methods', [
            'vendor' => $vendor,
            'paymentMethods' => $vendor->paymentMethods()->latest()->get(),
            'types' => PaymentMethod::TYPES,
            'providers' => config('payment.payout.providers'),
        ]);
    }

    public function addPaymentMethod(Request $request)
    {
        $validated = $request->validate(PayoutService::methodRules());
        $this->payouts->addMethod($request->user()->sellerProfile, $validated);

        return back()->with('success', 'تمت إضافة طريقة الاستلام بنجاح.');
    }

    public function removePaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        Gate::authorize('manage', $paymentMethod);
        $this->payouts->removeMethod($paymentMethod);

        return back()->with('success', 'تم حذف طريقة الاستلام.');
    }

    public function setDefaultPaymentMethod(Request $request, PaymentMethod $paymentMethod)
    {
        Gate::authorize('manage', $paymentMethod);
        $this->payouts->setDefaultMethod($paymentMethod);

        return back()->with('success', 'تم تعيين طريقة الاستلام الافتراضية.');
    }

    public function requestWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->payouts->request($request->user()->sellerProfile, (float) $validated['amount'], $validated['note'] ?? null);

        return back()->with('success', 'تم إرسال طلب السحب، بانتظار موافقة الإدارة.');
    }
}
