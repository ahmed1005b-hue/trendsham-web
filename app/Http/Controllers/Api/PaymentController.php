<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WithdrawalRequest;
use App\Payments\Payouts\PayoutResult;
use App\Services\PaymentService;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $svc) {}

    /** إعدادات الدفع للتطبيق: GET /api/v1/payments/config */
    public function config()
    {
        return [
            'online_enabled' => $this->svc->onlineEnabled(),
            'saved_cards_enabled' => $this->svc->savedCardsEnabled(),
            'gateway' => $this->svc->gateway()->name(),
            'currency' => config('payment.currency', 'SYP'),
        ];
    }

    /** بدء الدفع: POST /api/v1/payments/start  {order_id, card_id?} */
    public function start(Request $r)
    {
        $r->validate([
            'order_id' => 'required|integer',
            'card_id' => 'nullable|integer',
        ]);

        $order = Order::where('id', $r->order_id)
            ->where('user_id', $r->user()->id)
            ->firstOrFail();

        abort_if(($order->payment_status ?? 'unpaid') === 'paid' || ($order->payment && $order->payment->status === 'paid'), 422, 'الطلب مدفوع مسبقاً');
        abort_if($order->status === 'cancelled', 422, 'لا يمكن دفع طلب ملغى.');

        $card = $r->filled('card_id')
            ? $r->user()->customerCards()->active()->findOrFail($r->card_id)
            : null;

        return response()->json($this->svc->start($order, $card));
    }

    /** حالة الدفع: GET /api/v1/orders/{order}/payment-status */
    public function status(Request $r, Order $order)
    {
        Gate::authorize('view', $order);

        $p = Payment::where('order_id', $order->id)->latest('id')->first();

        return [
            'status' => $p?->status ?? 'none',
            'paid' => (($order->payment_status ?? 'unpaid') === 'paid') || ($p?->status === 'paid'),
        ];
    }

    // ------------------------------------------------------------------ البطاقات المحفوظة

    /** GET /api/v1/cards */
    public function cards(Request $r)
    {
        return [
            'enabled' => $this->svc->savedCardsEnabled(),
            'data' => $r->user()->customerCards()->active()->orderByDesc('is_default')->latest()->get()
                ->map->toApiArray()->values(),
        ];
    }

    /** POST /api/v1/cards → رابط صفحة البنك لإدخال البطاقة (يُفتح في المتصفح) */
    public function addCard(Request $r)
    {
        return response()->json($this->svc->startCardSave($r->user()), 201);
    }

    /** GET /api/v1/cards/status/{reference} — للتطبيق بعد العودة من صفحة البنك */
    public function cardStatus(Request $r, string $reference)
    {
        $card = $r->user()->customerCards()->where('reference', $reference)->firstOrFail();

        return ['status' => $card->status, 'card' => $card->status === 'active' ? $card->toApiArray() : null];
    }

    public function setDefaultCard(Request $r, CustomerCard $card)
    {
        Gate::authorize('manage', $card);
        $this->svc->setDefaultCard($card);

        return ['message' => 'تم تعيين البطاقة الافتراضية.'];
    }

    public function deleteCard(Request $r, CustomerCard $card)
    {
        Gate::authorize('manage', $card);
        $this->svc->deleteCard($card);

        return ['message' => 'تم حذف البطاقة.'];
    }

    // ------------------------------------------------------------------ إشعارات البنك

    /** إشعار البنك (دفع أو حفظ بطاقة): POST /api/v1/payments/webhook/{driver} */
    public function webhook(Request $r, string $driver)
    {
        $gw = $this->svc->gateway($driver);

        if (! $gw->verifyWebhook($r)) {
            Log::warning('Payment webhook: bad signature', ['driver' => $driver, 'ip' => $r->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $this->svc->handle($gw->parseWebhook($r));

        return response()->json(['ok' => true]);
    }

    /** إشعار البنك بنتيجة حوالة أرباح: POST /api/v1/payouts/webhook/{driver} */
    public function payoutWebhook(Request $r, PayoutService $payouts, string $driver)
    {
        $gw = $payouts->gateway();
        if ($gw->name() !== $driver || ! $gw->verifyWebhook($r)) {
            Log::warning('Payout webhook: bad signature', ['driver' => $driver, 'ip' => $r->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $e = $gw->parseWebhook($r);
        $w = WithdrawalRequest::find($e['id']);
        if ($w && in_array($w->status, ['approved', 'processing'], true)) {
            $payouts->applyResult($w, new PayoutResult($e['status'], $e['reference'], $e['reason'], $e['raw']));
        }

        return response()->json(['ok' => true]);
    }

    /** صفحة عودة المستخدم من البنك. تعرض الحالة فقط ولا تغيّرها. */
    public function back(string $reference)
    {
        $p = Payment::where('reference', $reference)->with('order')->firstOrFail();

        return view('payments.return', [
            'status' => $p->status,
            'order' => auth()->id() && $p->order?->user_id === auth()->id() ? $p->order : null,
        ]);
    }
}
