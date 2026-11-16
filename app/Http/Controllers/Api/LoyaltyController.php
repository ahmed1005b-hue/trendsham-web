<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltyException;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Loyalty\PointsCouponIssuer;
use Illuminate\Http\Request;

/** نقاط الولاء للتطبيق. المبالغ بالليرة. */
class LoyaltyController extends Controller
{
    public function show(Request $request, LoyaltyLedger $ledger, LoyaltyCalculator $calc)
    {
        $user = $request->user();
        $s = $calc->settings();
        $account = $ledger->account($user->id);
        $expiring = Tx::where('user_id', $user->id)->whereIn('type', Tx::LOT_TYPES)->where('remaining', '>', 0)
            ->whereBetween('expires_at', [now(), now()->addDays(30)])->get(['remaining', 'expires_at']);

        return response()->json([
            'enabled' => $s->enabled(),
            'balance' => $account->balance,
            'balance_value' => $calc->valueOf($account->balance) / 100,
            'pending' => $calc->pending($user),
            'debt' => $account->debt,
            'point_value' => $s->pointValue() / 100,
            'spend_per_point' => $s->spendPerPoint() / 100,
            'min_redeem' => $s->minRedeem(),
            'coupon_valid_days' => $s->couponValidDays(),
            'expiring_soon' => $expiring->isEmpty() ? null : [
                'points' => (int) $expiring->sum('remaining'),
                'expires_at' => $expiring->min('expires_at')->toDateString(),
            ],
        ]);
    }

    public function transactions(Request $request)
    {
        $page = Tx::where('user_id', $request->user()->id)->with('order:id,order_number')
            ->latest('id')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Tx $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'label' => $t->label(),
                'points' => $t->points,
                'order_number' => $t->order?->order_number,
                'expires_at' => in_array($t->type, Tx::LOT_TYPES, true) && $t->remaining > 0 ? $t->expires_at?->toDateString() : null,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }

    public function convert(Request $request, PointsCouponIssuer $issuer, LoyaltyLedger $ledger)
    {
        $data = $request->validate(['points' => ['required', 'integer', 'min:1']]);

        try {
            $coupon = $issuer->convert($request->user(), (int) $data['points']);
        } catch (LoyaltyException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['points' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'coupon_id' => $coupon->id,
            'value' => $coupon->campaign->value / 100,
            'expires_at' => $coupon->expires_at->toDateString(),
            'balance' => $ledger->account($request->user()->id)->balance,
        ], 201);
    }
}
