<?php

namespace App\Http\Controllers\Customer;

use App\GiftCards\GiftCardException;
use App\GiftCards\GiftCardService;
use App\GiftCards\Models\GiftBalanceTransaction;
use App\GiftCards\Models\GiftCard;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** صفحة «بطاقات الهدايا»: الرصيد، إدخال كود، شراء بطاقة، والبطاقات التي اشتريتها. */
class GiftCardController extends Controller
{
    public function index(Request $request, GiftCardService $gifts): View
    {
        $user = $request->user();

        return view('customer.gift-cards.index', [
            'balance' => $gifts->balance($user->id),
            'onlineEnabled' => $gifts->onlineEnabled(),
            'presets' => GiftCardService::PRESETS,
            'cards' => GiftCard::where('purchaser_id', $user->id)->whereIn('status', [GiftCard::ACTIVE, GiftCard::REDEEMED])
                ->with('redeemer:id,first_name')->latest('id')->limit(50)->get(),
            'transactions' => GiftBalanceTransaction::where('user_id', $user->id)->with('order:id,order_number')
                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function purchase(Request $request, GiftCardService $gifts): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],         // بالليرة
            'recipient_name' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $result = $gifts->purchase($request->user(), (int) $data['amount'] * 100, $data['recipient_name'] ?? null, $data['message'] ?? null);
        } catch (GiftCardException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->away($result['url']);
    }

    public function redeem(Request $request, GiftCardService $gifts): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30']]);

        try {
            $card = $gifts->redeem($request->user(), $data['code']);
        } catch (GiftCardException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('customer.gift-cards.index')->with('success',
            __('أُضيف :amount إلى رصيد الهدايا.', ['amount' => GiftCardService::money($card->amount)]));
    }

    /** يعود إليه الزبون من صفحة البنك. النتيجة الحقيقية تصل بإشعار البنك. */
    public function back(string $reference): RedirectResponse
    {
        $card = GiftCard::where('reference', $reference)->first(['status']);

        return redirect()->route('customer.gift-cards.index')->with(
            $card?->status === GiftCard::ACTIVE ? 'success' : 'info',
            match ($card?->status) {
                GiftCard::ACTIVE => 'تم الدفع وبطاقة الهدية جاهزة. انسخ الكود وأرسله لمن تريد.',
                GiftCard::FAILED => 'لم يكتمل الدفع، ولم يُخصم شيء.',
                default => 'ننتظر تأكيد البنك. ستصلك رسالة عندما تصبح البطاقة جاهزة.',
            }
        );
    }
}
