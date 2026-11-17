<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Referrals\ReferralCode;
use App\Referrals\ReferralCookie;
use App\Referrals\ReferralException;
use App\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** صفحة «دعوة صديق» في الموقع ورابط الدعوة العام. */
class ReferralController extends Controller
{
    public function index(Request $request, ReferralService $referrals): View
    {
        return view('customer.referrals.index', ['summary' => $referrals->summary($request->user())]);
    }

    public function apply(Request $request, ReferralService $referrals): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $referrals->apply($request->user(), $data['code']);
        } catch (ReferralException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('customer.referrals.index')
            ->with('success', 'تم تسجيل كود الدعوة. ستحصل على النقاط بعد أن يصلك أول طلب.');
    }

    /** رابط الدعوة العام: الزائر يسجّل والكود محفوظ، والزبون المسجّل يُدخله من صفحته. */
    public function invite(Request $request, string $code): RedirectResponse
    {
        if (ReferralCode::decode($code) === null) {
            return redirect()->route('home');
        }

        $user = $request->user();
        if ($user?->role === 'customer') {
            return redirect()->route('customer.referrals.index')->withInput(['code' => strtoupper($code)]);
        }
        if ($user) {
            return redirect()->route('home');
        }

        ReferralCookie::remember(strtoupper($code));

        return redirect()->route('register')
            ->with('info', 'دعاك صديق إلى TrendSham. أنشئ حسابك وستحصلان معاً على نقاط بعد أول طلب.');
    }
}
