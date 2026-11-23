<?php

namespace App\Http\Controllers\Api\Admin;

use App\Accounting\SellerDebtPayments;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SellerProfile;
use App\Models\Wallet;
use App\Shipping\SelfDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ديون المتاجر للمنصة في التطبيق (نفس صفحة «ديون المتاجر» في الموقع). المبالغ بالليرة. */
class SellerDebtController extends Controller
{
    public function index(): JsonResponse
    {
        $wallets = Wallet::with('vendor.user', 'vendor.shippingSetting')
            ->where('balance', '<', 0)
            ->orderBy('balance')
            ->paginate(30);

        return response()->json([
            'total' => -Wallet::where('balance', '<', 0)->sum('balance') / 100,
            'data' => $wallets->getCollection()->filter(fn (Wallet $w) => $w->vendor)->map(fn (Wallet $w) => [
                'seller_profile_id' => $w->vendor->id,
                'store_name' => $w->vendor->store_name,
                'owner_name' => $w->vendor->user?->name,
                'phone' => $w->vendor->store_contact_phone ?: $w->vendor->user?->phone,
                'debt' => -$w->balance / 100,
                'self_delivery' => ($w->vendor->shippingSetting?->mode ?? 'platform') === 'self',
            ])->values(),
            'limit' => (int) (PlatformSetting::current()->seller_debt_limit ?? 10000000) / 100,
            'meta' => ['current_page' => $wallets->currentPage(), 'last_page' => $wallets->lastPage()],
        ]);
    }

    public function pay(Request $request, SellerProfile $sellerProfile, SellerDebtPayments $payments): JsonResponse
    {
        $data = $request->validate(SellerDebtPayments::rules());
        $payments->record($request, $request->user(), $sellerProfile, (float) $data['amount'], $data['note'] ?? null);

        return response()->json([
            'message' => __('تم تسجيل التسديد وإضافته لرصيد المتجر.'),
            'debt' => SelfDelivery::debt($sellerProfile->fresh('wallet')) / 100,
        ]);
    }
}
