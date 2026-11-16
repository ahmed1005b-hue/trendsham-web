<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltyException;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Loyalty\PointsCouponIssuer;
use Illuminate\Http\Request;

/** صفحة "نقاطي" للعميل + تحويل النقاط إلى كوبون */
class LoyaltyController extends Controller
{
    public function index(Request $request, LoyaltyLedger $ledger, LoyaltyCalculator $calc)
    {
        $user = $request->user();
        $settings = $calc->settings();
        $account = $ledger->account($user->id);

        $expiring = Tx::where('user_id', $user->id)->whereIn('type', Tx::LOT_TYPES)->where('remaining', '>', 0)
            ->whereBetween('expires_at', [now(), now()->addDays(30)])->get(['remaining', 'expires_at']);

        return view('customer.loyalty.index', [
            'account' => $account,
            'pending' => $calc->pending($user),
            'settings' => $settings,
            'balanceValue' => $calc->valueOf($account->balance),
            'expiringPoints' => (int) $expiring->sum('remaining'),
            'expiringAt' => $expiring->min('expires_at'),
            'transactions' => Tx::where('user_id', $user->id)->with('order:id,order_number')
                ->latest('id')->paginate(20),
        ]);
    }

    public function convert(Request $request, PointsCouponIssuer $issuer)
    {
        $data = $request->validate(['points' => ['required', 'integer', 'min:1']]);

        try {
            $coupon = $issuer->convert($request->user(), (int) $data['points']);
        } catch (LoyaltyException $e) {
            return back()->withErrors(['points' => $e->getMessage()])->withInput();
        }

        return redirect()->route('customer.loyalty.index')->with('success',
            'تم تحويل '.LoyaltyCalculator::pointsLabel((int) $data['points']).' إلى كوبون بقيمة '
            .number_format($coupon->campaign->value / 100).' ل.س في محفظة كوبوناتك، صالح حتى '.$coupon->expires_at->format('Y-m-d').'.');
    }
}
