<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\WithdrawalRequest;
use App\Services\PayoutService;
use Illuminate\Http\Request;

/** طلبات سحب الأرباح من تطبيق المدير — نفس منطق لوحة الويب (PayoutService). */
class WithdrawalController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $requests = WithdrawalRequest::with('vendor.user')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'approved', 'processing']))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $requests->getCollection()->map->toAdminArray()->values(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'total' => $requests->total(),
                'automatic_payouts' => $this->payouts->gateway()->isAutomatic(),
            ],
        ]);
    }

    public function approve(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $w = $this->payouts->approve($withdrawalRequest, $request->user());

        return response()->json(['message' => 'تمت الموافقة.', 'withdrawal' => $w->toAdminArray()]);
    }

    public function reject(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $validated = $request->validate(['admin_note' => ['required', 'string', 'max:255']]);
        $w = $this->payouts->reject($withdrawalRequest, $validated['admin_note'], $request->user());

        return response()->json(['message' => 'تم الرفض.', 'withdrawal' => $w->toAdminArray()]);
    }

    public function markPaid(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $validated = $request->validate([
            'transfer_reference' => ['required', 'string', 'max:100'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $w = $this->payouts->markPaid($withdrawalRequest, $validated['transfer_reference'], $request->file('receipt'), $request->user());

        return response()->json(['message' => 'تم تأكيد التحويل.', 'withdrawal' => $w->toAdminArray()]);
    }

    public function markFailed(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $validated = $request->validate(['failure_reason' => ['required', 'string', 'max:255']]);
        $w = $this->payouts->markFailed($withdrawalRequest, $validated['failure_reason'], $request->user());

        return response()->json(['message' => 'تم إرجاع المبلغ لمحفظة البائع.', 'withdrawal' => $w->toAdminArray()]);
    }

    public function send(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $w = $this->payouts->send($withdrawalRequest, null, $request->user());

        return response()->json(['message' => $w->status_label, 'withdrawal' => $w->toAdminArray()]);
    }

    public function verifyMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $this->payouts->verifyMethod($paymentMethod, $request->user(), ! $paymentMethod->is_verified);

        return response()->json(['is_verified' => (bool) $paymentMethod->fresh()->is_verified]);
    }
}
