<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\WithdrawalRequest;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class WithdrawalController extends Controller
{
    public function __construct(protected readonly PayoutService $payouts) {}

    public function index(Request $request)
    {
        $status = $request->get('status', 'open');

        $requests = WithdrawalRequest::with('vendor.user', 'paymentMethod')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending', 'approved', 'processing']))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = WithdrawalRequest::selectRaw('status, count(*) c, sum(amount) s')->groupBy('status')->get()->keyBy('status');

        return view('admin.withdrawals.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
            'automatic' => $this->payouts->gateway()->isAutomatic(),
            'gatewayName' => $this->payouts->gateway()->name(),
        ]);
    }

    public function approve(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $w = $this->payouts->approve($withdrawalRequest, $request->user());

        return back()->with('success', match ($w->status) {
            'paid' => 'تمت الموافقة وتحويل المبلغ عبر البنك.',
            'processing' => 'تمت الموافقة وأُرسلت الحوالة للبنك؛ بانتظار تأكيده.',
            'failed' => 'تمت الموافقة لكن البنك رفض الحوالة، وأُعيد المبلغ للبائع.',
            default => 'تمت الموافقة وخصم المبلغ. حوّل المبلغ الآن ثم سجّل رقم الحوالة.',
        });
    }

    public function reject(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $validated = $request->validate(['admin_note' => ['required', 'string', 'max:255']]);
        $this->payouts->reject($withdrawalRequest, $validated['admin_note'], $request->user());

        return back()->with('success', 'تم رفض طلب السحب.');
    }

    public function markPaid(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $validated = $request->validate([
            'transfer_reference' => ['required', 'string', 'max:100'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $this->payouts->markPaid($withdrawalRequest, $validated['transfer_reference'], $request->file('receipt'), $request->user());

        return back()->with('success', 'تم تأكيد التحويل وإشعار البائع.');
    }

    public function markFailed(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $validated = $request->validate(['failure_reason' => ['required', 'string', 'max:255']]);
        $this->payouts->markFailed($withdrawalRequest, $validated['failure_reason'], $request->user());

        return back()->with('success', 'تم تعليم الحوالة كفاشلة وإعادة المبلغ لمحفظة البائع.');
    }

    public function send(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        $w = $this->payouts->send($withdrawalRequest, null, $request->user());

        return back()->with($w->status === 'approved' ? 'error' : 'success', match ($w->status) {
            'paid' => 'تم التحويل عبر البنك.',
            'processing' => 'أُرسلت الحوالة؛ بانتظار تأكيد البنك.',
            'failed' => 'رفض البنك الحوالة وأُعيد المبلغ للبائع.',
            default => $w->failure_reason ?: 'تعذّر الإرسال، حاول مرة أخرى.',
        });
    }

    public function verifyMethod(Request $request, PaymentMethod $paymentMethod)
    {
        $this->payouts->verifyMethod($paymentMethod, $request->user(), ! $paymentMethod->is_verified);

        return back()->with('success', $paymentMethod->fresh()->is_verified ? 'تم توثيق طريقة الاستلام.' : 'تم إلغاء توثيق طريقة الاستلام.');
    }

    /** إيصال الحوالة: للمدير أو للبائع صاحب الطلب فقط. */
    public function receipt(Request $request, WithdrawalRequest $withdrawalRequest)
    {
        Gate::authorize('viewReceipt', $withdrawalRequest);
        abort_unless($withdrawalRequest->receipt_path && Storage::disk('local')->exists($withdrawalRequest->receipt_path), 404);

        return Storage::disk('local')->response($withdrawalRequest->receipt_path);
    }
}
