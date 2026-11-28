<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Payments\Contracts\WebhookEvent;
use App\Services\PaymentService;
use App\Services\StaffActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * تأكيد استلام الدفع يدوياً (تحويل بنكي، دفع عند الاستلام تم تحصيله...).
     * يستخدم PaymentService::apply (idempotent) لتوحيد منطق التأكيد مع webhook البنكي.
     */
    public function markPaid(Request $request, Order $order, PaymentService $svc)
    {
        $validated = $request->validate([
            'transaction_ref' => ['nullable', 'string', 'max:255'],
        ]);

        $p = $order->payments()->latest()->first() ?? $order->payment;
        abort_unless($p && $p->status === 'pending', 409, 'تمت معالجة الدفع مسبقاً.');

        // تأكد وجود مرجع لPaymentService (متوافق مع سجلات الدفع القديمة)
        if (empty($p->reference)) {
            $p->update(['reference' => (string) Str::uuid()]);
            $p->refresh();
        }

        $svc->apply($p->reference, new WebhookEvent(
            $p->reference,
            'paid',
            $validated['transaction_ref'] ?? null,
            (float) ($order->total ?? $p->amount),
            ['by' => 'admin', 'admin_id' => $request->user()?->id, 'at' => now()->toIso8601String()]
        ));

        // توافق مع السلوك السابق: تحديث حالة الطلب + إشعار (إذا لم يكن svc قد غطاه)
        // تحديث مشروط: إن ألغى العميل الطلب لحظتها لا نعيد فتحه كـ"مدفوع" (أُرجعت كمياته للمخزون)
        Order::whereKey($order->id)->where('status', 'pending')->update(['status' => 'paid']);
        $order->refresh();
        if (($order->payment_status ?? null) !== 'paid') {
            $order->update(['payment_status' => 'paid']);
        }

        // إشعار العميل يرسله PaymentService::apply
        app(StaffActivityLog::class)->order($request->user(), 'payment.paid', $order);

        return back()->with('success', 'تم تأكيد الدفع.');
    }

    public function markFailed(Request $request, Order $order)
    {
        $p = $order->payments()->latest()->first() ?? $order->payment;
        abort_unless($p && $p->status === 'pending', 409, 'تمت معالجة الدفع مسبقاً.');

        $p->update(['status' => 'failed']);
        if (($order->payment_status ?? null) === 'unpaid') {
            $order->update(['payment_status' => 'failed']);
        }
        app(StaffActivityLog::class)->order($request->user(), 'payment.failed', $order);

        return back()->with('success', 'تم تعليم الدفع كفاشل.');
    }
}
