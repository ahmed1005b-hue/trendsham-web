<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Services\PayoutService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function show(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        $wallet = $vendor->walletOrCreate();
        $transactions = $wallet->transactions()->latest()->paginate(20);

        return response()->json([
            'balance' => $wallet->balance / 100,
            'available' => $this->payouts->available($vendor) / 100,
            'pending_withdrawals' => $vendor->withdrawalRequests()->where('status', 'pending')->sum('amount') / 100,
            'min_withdrawal' => (int) config('payment.payout.min_amount'),
            'default_method' => $vendor->paymentMethods()->where('is_default', true)->first()?->toApiArray(),
            'recent_withdrawals' => $vendor->withdrawalRequests()->latest()->limit(5)->get()->map->toSellerArray()->values(),
            'transactions' => WalletTransactionResource::collection($transactions)->response()->getData(true),
        ]);
    }

    /** GET /seller/wallet/withdrawals — كل طلبات السحب مع حالتها ورقم الحوالة */
    public function withdrawals(Request $request)
    {
        $page = $request->user()->sellerProfile->withdrawalRequests()->latest()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map->toSellerArray()->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $withdrawal = $this->payouts->request($request->user()->sellerProfile, (float) $validated['amount'], $validated['note'] ?? null);

        return response()->json([
            'message' => 'تم إرسال طلب السحب، بانتظار موافقة الإدارة.',
            'id' => $withdrawal->id,
            'withdrawal' => $withdrawal->toSellerArray(),
        ], 201);
    }
}
