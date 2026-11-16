<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Returns\Models\ReturnRefund;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public const TABS = ['escalated', 'awaiting_refund', 'all'];

    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'escalated';

        $returnRequests = ReturnRequest::query()
            ->when($tab === 'escalated', fn ($query) => $query->where('status', 'escalated'))
            ->when($tab === 'awaiting_refund', fn ($query) => $query->where('status', 'received'))
            ->with('order', 'customer', 'seller')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = ReturnRequest::query()
            ->whereIn('status', ['escalated', 'received'])
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return view('admin.returns.index', [
            'returnRequests' => $returnRequests,
            'tab' => $tab,
            'counts' => $counts,
        ]);
    }

    public function show(ReturnRequest $returnRequest): View
    {
        $returnRequest->load('order', 'customer', 'seller', 'items.orderItem.product', 'events', 'refund');

        return view('admin.returns.show', ['returnRequest' => $returnRequest]);
    }

    public function approve(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('adminDecide', $returnRequest);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->returns->adminApprove($request->user(), $returnRequest, $validated['note'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تمت الموافقة على طلب الإرجاع.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('adminDecide', $returnRequest);
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            $this->returns->adminReject($request->user(), $returnRequest, $validated['note']);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم رفض طلب الإرجاع.');
    }

    public function received(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('confirmReceived', $returnRequest);

        try {
            $this->returns->confirmReceived($request->user(), $returnRequest);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تأكيد استلام المرتجع.');
    }

    public function refund(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('recordRefund', $returnRequest);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'method' => ['required', Rule::in(array_keys(ReturnRefund::METHOD_LABELS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'extra_shipping' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        try {
            $this->returns->recordRefund(
                $request->user(),
                $returnRequest,
                (int) round($validated['amount'] * 100),
                $validated['method'],
                $validated['reference'] ?? null,
                $validated['note'] ?? null,
                (int) round(($validated['extra_shipping'] ?? 0) * 100),
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تسجيل الاسترداد.');
    }
}
