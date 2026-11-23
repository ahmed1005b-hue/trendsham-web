<?php

namespace App\Http\Controllers\Api\Admin;

use App\GiftCards\Models\GiftCard;
use App\Http\Controllers\Admin\GiftCardController as WebGiftCardController;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** بطاقات الهدايا للمدير في التطبيق (نفس صفحة الموقع). المبالغ بالليرة. */
class GiftCardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q'));
        $page = WebGiftCardController::cards($status, $q)->paginate(25);
        $totals = WebGiftCardController::totals();

        return response()->json([
            'totals' => [
                'sold_count' => $totals['soldCount'],
                'sold_amount' => $totals['soldAmount'] / 100,
                'spent' => $totals['spent'] / 100,
                'unredeemed' => $totals['unredeemed'] / 100,
                'expired_unused' => $totals['expiredUnused'] / 100,
                'balances' => $totals['balances'] / 100,
                'owed' => $totals['owed'] / 100,
            ],
            'statuses' => collect([GiftCard::PENDING, GiftCard::ACTIVE, GiftCard::REDEEMED, GiftCard::FAILED])
                ->map(fn (string $s) => ['value' => $s, 'label' => (new GiftCard(['status' => $s]))->statusLabel()])->values(),
            'data' => $page->getCollection()->map(fn (GiftCard $c) => [
                'id' => $c->id,
                'amount' => $c->amount / 100,
                'status' => $c->status,
                'status_label' => $c->statusLabel(),
                'purchaser' => $c->purchaser?->name,
                'purchaser_email' => $c->purchaser?->email,
                'redeemer' => $c->redeemer?->name,
                'date' => ($c->paid_at ?? $c->created_at)?->toIso8601String(),
                'expires_at' => $c->expires_at?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }
}
