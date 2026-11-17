<?php

namespace App\Http\Controllers\Admin;

use App\GiftCards\Models\GiftBalanceTransaction as Tx;
use App\GiftCards\Models\GiftCard;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * بطاقات الهدايا للمدير: ما بيع، ما استُخدم، وما تبقّى مستحقاً على المنصة للعملاء.
 * المستحق = بطاقات مدفوعة لم تُدخل بعد + أرصدة العملاء الحالية.
 */
class GiftCardController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q'));

        $paid = GiftCard::whereIn('status', [GiftCard::ACTIVE, GiftCard::REDEEMED]);
        $unredeemed = (int) GiftCard::where('status', GiftCard::ACTIVE)->where('expires_at', '>', now())->sum('amount');
        $expiredUnused = (int) GiftCard::where('status', GiftCard::ACTIVE)->where('expires_at', '<=', now())->sum('amount');
        $balances = (int) DB::table('gift_balance_transactions as t')
            ->whereRaw('t.id = (select max(id) from gift_balance_transactions where user_id = t.user_id)')
            ->sum('t.balance_after');

        $cards = GiftCard::with(['purchaser:id,first_name,last_name,email', 'redeemer:id,first_name,last_name,email'])
            ->when(in_array($status, [GiftCard::PENDING, GiftCard::ACTIVE, GiftCard::REDEEMED, GiftCard::FAILED], true),
                fn ($w) => $w->where('status', $status))
            ->when($q !== '', fn ($w) => $w->whereHas('purchaser', fn ($u) => $u->where('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")->orWhere('first_name', 'like', "%{$q}%")))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.gift-cards.index', [
            'soldCount' => (clone $paid)->count(),
            'soldAmount' => (int) (clone $paid)->sum('amount'),
            'spent' => (int) abs(Tx::where('type', Tx::SPEND)->sum('amount')) - (int) Tx::where('type', Tx::REFUND)->sum('amount'),
            'unredeemed' => $unredeemed,
            'expiredUnused' => $expiredUnused,
            'balances' => $balances,
            'owed' => $unredeemed + $balances,
            'cards' => $cards,
            'status' => $status,
            'q' => $q,
        ]);
    }
}
