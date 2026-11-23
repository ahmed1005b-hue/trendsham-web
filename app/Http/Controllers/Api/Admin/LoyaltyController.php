<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\LoyaltyController as WebLoyaltyController;
use App\Http\Controllers\Controller;
use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltyException;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyAccount;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** نقاط الولاء للمدير في التطبيق (نفس صفحة الموقع): الملخص، العملاء، التعديل اليدوي، والإعدادات. */
class LoyaltyController extends Controller
{
    public function index(Request $request, LoyaltyCalculator $calc): JsonResponse
    {
        $outstanding = (int) LoyaltyAccount::sum('balance');
        $monthStart = now()->startOfMonth();
        $month = fn (array $types) => (int) abs(Tx::whereIn('type', $types)->where('created_at', '>=', $monthStart)->sum('points'));
        $q = trim((string) $request->query('q'));

        $page = LoyaltyAccount::with('user:id,first_name,last_name,email,phone')
            ->when($q !== '', fn ($w) => $w->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")))
            ->orderByDesc('balance')->paginate(25);

        return response()->json([
            'summary' => [
                'outstanding' => $outstanding,
                'outstanding_value' => $calc->valueOf($outstanding) / 100,
                'earned_this_month' => $month([Tx::EARN]),
                'redeemed_this_month' => $month([Tx::REDEEM_CHECKOUT, Tx::REDEEM_COUPON]),
                'expired_this_month' => $month([Tx::EXPIRE]),
            ],
            'settings' => $this->settings($calc),
            'data' => $page->getCollection()->filter(fn (LoyaltyAccount $a) => $a->user)->map(fn (LoyaltyAccount $a) => [
                'user_id' => $a->user_id,
                'name' => $a->user->name,
                'email' => $a->user->email,
                'phone' => $a->user->phone,
                'balance' => (int) $a->balance,
                'debt' => (int) $a->debt,
                'lifetime_earned' => (int) $a->lifetime_earned,
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function updateSettings(Request $request, LoyaltyCalculator $calc): JsonResponse
    {
        WebLoyaltyController::saveSettings($request->validate(WebLoyaltyController::settingsRules()), $request->boolean('loyalty_enabled'));

        return response()->json(['message' => __('تم حفظ إعدادات النقاط.'), 'settings' => $this->settings(app(LoyaltyCalculator::class))]);
    }

    public function show(User $user, LoyaltyLedger $ledger, LoyaltyCalculator $calc): JsonResponse
    {
        abort_unless($user->role === 'customer', 404);
        $account = $ledger->account($user->id);
        $transactions = Tx::where('user_id', $user->id)->with('order:id,order_number')->latest('id')->limit(50)->get();

        return response()->json([
            'customer' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
            'balance' => (int) $account->balance,
            'debt' => (int) $account->debt,
            'lifetime_earned' => (int) $account->lifetime_earned,
            'pending' => $calc->pending($user),
            'transactions' => $transactions->map(fn (Tx $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'label' => $t->label(),
                'points' => (int) $t->points,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function adjust(Request $request, User $user, LoyaltyLedger $ledger): JsonResponse
    {
        abort_unless($user->role === 'customer', 404);
        $data = $request->validate(WebLoyaltyController::adjustRules());

        try {
            $ledger->adjust($user, $data['direction'], (int) $data['points'], $data['reason'], $request->user()->id);
        } catch (LoyaltyException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['points' => [$e->getMessage()]]], 422);
        }

        return response()->json([
            'message' => ($data['direction'] === 'add' ? __('أُضيفت ') : __('خُصمت ')).LoyaltyCalculator::pointsLabel((int) $data['points']).'.',
            'balance' => (int) $ledger->account($user->id)->balance,
        ]);
    }

    /** الإعدادات بالليرة (كما تُكتب في الموقع). @return array<string, mixed> */
    private function settings(LoyaltyCalculator $calc): array
    {
        $s = $calc->settings();

        return [
            'loyalty_enabled' => $s->enabled(),
            'loyalty_spend_per_point' => intdiv($s->spendPerPoint(), 100),
            'loyalty_point_value' => intdiv($s->pointValue(), 100),
            'loyalty_min_redeem' => $s->minRedeem(),
            'loyalty_expiry_months' => $s->expiryMonths(),
            'loyalty_coupon_valid_days' => $s->couponValidDays(),
        ];
    }
}
