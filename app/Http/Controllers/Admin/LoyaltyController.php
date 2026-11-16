<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltyException;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\LoyaltySettings;
use App\Loyalty\Models\LoyaltyAccount;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Request;

/** إدارة نقاط الولاء: الملخص المالي، رصيد العملاء، التعديل اليدوي، والإعدادات */
class LoyaltyController extends Controller
{
    public function index(Request $request, LoyaltyCalculator $calc)
    {
        $outstanding = (int) LoyaltyAccount::sum('balance');
        $monthStart = now()->startOfMonth();
        $month = fn (array $types) => (int) abs(Tx::whereIn('type', $types)->where('created_at', '>=', $monthStart)->sum('points'));

        $q = trim((string) $request->query('q'));
        $customers = LoyaltyAccount::with('user:id,first_name,last_name,email,phone')
            ->when($q !== '', fn ($w) => $w->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")))
            ->orderByDesc('balance')->paginate(25)->withQueryString();

        return view('admin.loyalty.index', [
            'outstanding' => $outstanding,
            'outstandingValue' => $calc->valueOf($outstanding),
            'earnedThisMonth' => $month([Tx::EARN]),
            'redeemedThisMonth' => $month([Tx::REDEEM_CHECKOUT, Tx::REDEEM_COUPON]),
            'expiredThisMonth' => $month([Tx::EXPIRE]),
            'customers' => $customers,
            'settings' => $calc->settings(),
            'q' => $q,
        ]);
    }

    public function updateSettings(Request $request)
    {
        // المبالغ تُدخل بالليرة وتُخزَّن ×100
        $data = $request->validate([
            'loyalty_enabled' => ['nullable', 'boolean'],
            'loyalty_spend_per_point' => ['required', 'integer', 'min:1', 'max:100000000'],
            'loyalty_point_value' => ['required', 'integer', 'min:1', 'max:100000000'],
            'loyalty_min_redeem' => ['required', 'integer', 'min:1', 'max:100000'],
            'loyalty_expiry_months' => ['required', 'integer', 'min:1', 'max:120'],
            'loyalty_coupon_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        PlatformSetting::current()->update([
            'loyalty_enabled' => $request->boolean('loyalty_enabled'),
            'loyalty_spend_per_point' => $data['loyalty_spend_per_point'] * 100,
            'loyalty_point_value' => $data['loyalty_point_value'] * 100,
            'loyalty_min_redeem' => $data['loyalty_min_redeem'],
            'loyalty_expiry_months' => $data['loyalty_expiry_months'],
            'loyalty_coupon_valid_days' => $data['loyalty_coupon_valid_days'],
        ]);
        app()->forgetInstance(LoyaltySettings::class);

        return back()->with('success', 'تم حفظ إعدادات النقاط.');
    }

    public function show(User $user, LoyaltyLedger $ledger, LoyaltyCalculator $calc)
    {
        abort_unless($user->role === 'customer', 404);

        return view('admin.loyalty.show', [
            'customer' => $user,
            'account' => $ledger->account($user->id),
            'pending' => $calc->pending($user),
            'transactions' => Tx::where('user_id', $user->id)->with('order:id,order_number', 'admin:id,first_name,last_name')
                ->latest('id')->paginate(30),
        ]);
    }

    public function adjust(Request $request, User $user, LoyaltyLedger $ledger)
    {
        abort_unless($user->role === 'customer', 404);
        $data = $request->validate([
            'direction' => ['required', 'in:add,deduct'],
            'points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        try {
            $ledger->adjust($user, $data['direction'], (int) $data['points'], $data['reason'], $request->user()->id);
        } catch (LoyaltyException $e) {
            return back()->withErrors(['points' => $e->getMessage()])->withInput();
        }

        return back()->with('success', ($data['direction'] === 'add' ? 'أُضيفت ' : 'خُصمت ')
            .LoyaltyCalculator::pointsLabel((int) $data['points']).'.');
    }
}
