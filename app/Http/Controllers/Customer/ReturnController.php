<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $returnRequests = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with('order', 'seller')
            ->latest('id')
            ->paginate(10);

        return view('customer.returns.index', ['returnRequests' => $returnRequests]);
    }

    public function create(Request $request, Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load('items.vendor');
        $returnable = $order->items
            ->mapWithKeys(fn ($item) => [$item->id => $this->returns->returnableQuantity($item)])
            ->filter();
        abort_if($returnable->isEmpty(), 404);

        return view('customer.returns.create', [
            'order' => $order,
            'returnable' => $returnable,
            'reasons' => ReturnRequest::REASONS,
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);

        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'reason' => ['required', 'in:'.implode(',', ReturnRequest::REASONS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.ReturnService::MAX_PHOTOS],
            'photos.*' => ['image', 'max:5120'],
        ]);

        try {
            $returnRequest = $this->returns->create(
                $request->user(), $order, $validated['lines'], $validated['reason'],
                $validated['description'] ?? null, $request->file('photos', []),
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('customer.returns.show', $returnRequest)->with('success', 'تم إرسال طلب الإرجاع.');
    }

    public function show(Request $request, ReturnRequest $returnRequest): View
    {
        Gate::authorize('view', $returnRequest);

        $returnRequest->load('order', 'seller', 'items.orderItem', 'events', 'refund');

        return view('customer.returns.show', ['returnRequest' => $returnRequest]);
    }

    public function withdraw(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('withdraw', $returnRequest);

        try {
            $this->returns->withdraw($request->user(), $returnRequest);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم سحب طلب الإرجاع.');
    }

    public function dispute(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        Gate::authorize('dispute', $returnRequest);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.ReturnService::MAX_PHOTOS],
            'photos.*' => ['image', 'max:5120'],
        ]);

        try {
            $this->returns->dispute($request->user(), $returnRequest, $validated['note'], $request->file('photos', []));
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم رفع النزاع إلى الإدارة.');
    }
}
