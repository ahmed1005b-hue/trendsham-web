<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, ReturnRequest::STATUSES, true) ? $status : null;

        $returnRequests = ReturnRequest::query()
            ->where('seller_profile_id', $request->user()->sellerProfile->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('order', 'customer')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('seller.returns.index', ['returnRequests' => $returnRequests, 'status' => $status]);
    }

    public function show(ReturnRequest $returnRequest): View
    {
        Gate::authorize('viewAsSeller', $returnRequest);

        $returnRequest->load('order', 'customer', 'items.orderItem.product', 'events');

        return view('seller.returns.show', ['returnRequest' => $returnRequest]);
    }

    public function approve(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('decide', $returnRequest);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->returns->sellerApprove($request->user()->sellerProfile, $returnRequest, $validated['note'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تمت الموافقة على طلب الإرجاع.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('decide', $returnRequest);
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            $this->returns->sellerReject($request->user()->sellerProfile, $returnRequest, $validated['note']);
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
}
