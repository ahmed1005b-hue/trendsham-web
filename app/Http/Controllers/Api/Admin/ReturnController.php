<?php

namespace App\Http\Controllers\Api\Admin;

use App\GiftCards\GiftCardService;
use App\Http\Controllers\Admin\ReturnController as WebReturnController;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnRequestResource;
use App\Returns\Models\ReturnRefund;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * طلبات الإرجاع للمدير في التطبيق (نفس صفحة «المرتجعات» في الموقع):
 * الحكم في المحالة للإدارة، وتأكيد الاستلام، وتسجيل الاسترداد.
 */
class ReturnController extends Controller
{
    private const RELATIONS = ['order', 'customer', 'seller', 'items.orderItem', 'events', 'refund'];

    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request): JsonResponse
    {
        $tab = in_array($request->query('tab'), WebReturnController::TABS, true) ? $request->query('tab') : 'escalated';

        $page = ReturnRequest::query()
            ->when($tab === 'escalated', fn ($q) => $q->where('status', 'escalated'))
            ->when($tab === 'awaiting_refund', fn ($q) => $q->where('status', 'received'))
            ->with('order', 'customer', 'seller', 'items.orderItem')
            ->latest('id')
            ->paginate(20);

        $counts = ReturnRequest::whereIn('status', ['escalated', 'received'])
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return response()->json([
            'tab' => $tab,
            'counts' => ['escalated' => (int) ($counts['escalated'] ?? 0), 'awaiting_refund' => (int) ($counts['received'] ?? 0)],
            'data' => ReturnRequestResource::collection($page->getCollection())->resolve($request),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards): JsonResponse
    {
        return $this->present($request, $returnRequest, $giftCards);
    }

    public function approve(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards): JsonResponse
    {
        Gate::authorize('adminDecide', $returnRequest);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        return $this->run($request, $returnRequest, $giftCards, __('تمت الموافقة على طلب الإرجاع.'),
            fn () => $this->returns->adminApprove($request->user(), $returnRequest, $data['note'] ?? null));
    }

    public function reject(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards): JsonResponse
    {
        Gate::authorize('adminDecide', $returnRequest);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        return $this->run($request, $returnRequest, $giftCards, __('تم رفض طلب الإرجاع.'),
            fn () => $this->returns->adminReject($request->user(), $returnRequest, $data['note']));
    }

    public function received(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards): JsonResponse
    {
        Gate::authorize('confirmReceived', $returnRequest);

        return $this->run($request, $returnRequest, $giftCards, __('تم تأكيد استلام المرتجع.'),
            fn () => $this->returns->confirmReceived($request->user(), $returnRequest));
    }

    public function refund(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards): JsonResponse
    {
        Gate::authorize('recordRefund', $returnRequest);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'method' => ['required', Rule::in(array_keys(ReturnRefund::METHOD_LABELS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'extra_shipping' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        return $this->run($request, $returnRequest, $giftCards, __('تم تسجيل الاسترداد.'),
            fn () => $this->returns->recordRefund(
                $request->user(), $returnRequest,
                (int) round($data['amount'] * 100), $data['method'],
                $data['reference'] ?? null, $data['note'] ?? null,
                (int) round(($data['extra_shipping'] ?? 0) * 100),
            ));
    }

    private function run(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards, string $message, callable $action): JsonResponse
    {
        try {
            $action();
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->present($request, $returnRequest->fresh(), $giftCards, $message);
    }

    private function present(Request $request, ReturnRequest $returnRequest, GiftCardService $giftCards, ?string $message = null): JsonResponse
    {
        $returnRequest->load(self::RELATIONS);
        $order = $returnRequest->order;
        $giftUsed = (int) $order->gift_balance_used;

        return response()->json(array_filter([
            'message' => $message,
            'data' => (new ReturnRequestResource($returnRequest))->resolve($request),
            // ما يستطيع المدير فعله الآن (نفس أزرار صفحة الموقع)
            'actions' => [
                'decide' => $returnRequest->status === 'escalated',
                'confirm_received' => $returnRequest->status === 'approved',
                // إرجاع المال للمدير وحده؛ الموظف يرى الحالة دون الزر
                'record_refund' => $returnRequest->status === 'received' && $request->user()->isAdmin(),
            ],
            'refund_form' => [
                'suggested_amount' => $returnRequest->refund_amount / 100,
                'max_extra_shipping' => $order->shipping_fee / 100,
                'gift_balance_used' => $giftUsed / 100,
                'gift_balance_refundable' => $giftUsed > 0 ? $giftCards->refundableForOrder($order) / 100 : 0,
                'methods' => collect(ReturnRefund::METHOD_LABELS)
                    ->reject(fn ($label, $method) => $method === 'gift_balance' && $giftUsed <= 0)
                    ->map(fn ($label, $method) => ['value' => $method, 'label' => __($label)])
                    ->values(),
            ],
        ], fn ($v) => $v !== null));
    }
}
