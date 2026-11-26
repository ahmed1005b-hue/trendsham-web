<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * صفحة دفع طلب بالبطاقة: اختيار بطاقة محفوظة أو بطاقة جديدة في صفحة البنك.
 * لا نستقبل رقم البطاقة أو CVV على خوادمنا إطلاقاً.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request, Order $order)
    {
        Gate::authorize('view', $order);

        if ($this->isPaid($order)) {
            return redirect()->route('customer.orders.show', $order)->with('success', 'هذا الطلب مدفوع مسبقاً.');
        }
        if ($closed = $this->closedReason($order)) {
            return redirect()->route('customer.orders.show', $order)->with('error', $closed);
        }

        return view('customer.checkout.pay', [
            'order' => $order,
            'enabled' => $this->payments->onlineEnabled(),
            'cards' => $this->payments->savedCardsEnabled()
                ? $request->user()->customerCards()->active()->orderByDesc('is_default')->latest()->get()->filter->isUsable()
                : collect(),
        ]);
    }

    public function process(Request $request, Order $order)
    {
        Gate::authorize('view', $order);
        abort_if($this->isPaid($order), 422, 'هذا الطلب مدفوع مسبقاً.');
        abort_if($order->status === 'cancelled', 422, 'لا يمكن دفع طلب ملغى.');
        if ($closed = $this->closedReason($order)) {
            return redirect()->route('customer.orders.show', $order)->with('error', $closed);
        }

        $validated = $request->validate([
            'card_id' => ['nullable', 'integer'],
        ]);

        $card = null;
        if (! empty($validated['card_id'])) {
            $card = $request->user()->customerCards()->active()->findOrFail($validated['card_id']);
        }

        $result = $this->payments->start($order, $card);

        return match ($result['type']) {
            'redirect' => redirect()->away($result['url']),
            'paid' => redirect()->route('customer.orders.show', $order)->with('success', 'تم الدفع بنجاح. شكراً لك!'),
            'failed' => back()->with('error', $result['message'] ?: 'رفض البنك عملية الدفع.'),
            default => redirect()->route('customer.orders.show', $order)->with('success', $result['message'] ?: 'تم استلام طلبك.'),
        };
    }

    /** سبب منع الدفع الآن (ملغى أو انتهت مهلة دفع البطاقة)، أو null. */
    private function closedReason(Order $order): ?string
    {
        return match (true) {
            $order->status === 'cancelled' => __('لا يمكن دفع طلب ملغى.'),
            $order->onlinePaymentWindowClosed() => __('انتهت مهلة دفع هذا الطلب. سيُلغى تلقائياً، ويمكنك الطلب من جديد.'),
            default => null,
        };
    }

    private function isPaid(Order $order): bool
    {
        return ($order->payment_status ?? null) === 'paid' || $order->payment?->status === 'paid';
    }
}
