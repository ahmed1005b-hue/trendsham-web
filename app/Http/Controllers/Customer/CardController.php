<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomerCard;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * بطاقاتي البنكية. إدخال البطاقة يتم في صفحة البنك فقط؛ نحن نستلم توكن + آخر 4 أرقام.
 */
class CardController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request)
    {
        return view('customer.cards.index', [
            'cards' => $request->user()->customerCards()->active()->orderByDesc('is_default')->latest()->get(),
            'enabled' => $this->payments->savedCardsEnabled(),
            'sandbox' => $this->payments->gateway()->name() === 'sandbox',
        ]);
    }

    public function store(Request $request)
    {
        $result = $this->payments->startCardSave($request->user());

        return redirect()->away($result['url']);
    }

    /** يعود إليها العميل من صفحة البنك. الحالة الفعلية تأتي من webhook البنك فقط. */
    public function back(Request $request, string $reference)
    {
        $card = $request->user()->customerCards()->where('reference', $reference)->firstOrFail();

        return redirect()->route('customer.cards.index')->with(...match ($card->status) {
            'active' => ['success', 'تم حفظ البطاقة '.$card->brand_label.' '.$card->masked.' بنجاح.'],
            'failed' => ['error', 'لم يتم حفظ البطاقة. يمكنك المحاولة مرة أخرى.'],
            default => ['success', 'جاري تأكيد البطاقة من البنك، ستظهر هنا خلال لحظات.'],
        });
    }

    public function setDefault(Request $request, CustomerCard $card)
    {
        Gate::authorize('manage', $card);
        $this->payments->setDefaultCard($card);

        return back()->with('success', 'تم تعيين البطاقة الافتراضية.');
    }

    public function destroy(Request $request, CustomerCard $card)
    {
        Gate::authorize('manage', $card);
        $this->payments->deleteCard($card);

        return back()->with('success', 'تم حذف البطاقة.');
    }
}
