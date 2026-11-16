<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnRequestResource;
use App\Models\Order;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use App\Returns\ReturnSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/** إرجاع المنتجات للزبون في التطبيق: نفس قواعد الموقع (ReturnService) والسياسة. */
class ReturnController extends Controller
{
    private const RELATIONS = ['order', 'seller', 'items.orderItem', 'events', 'refund'];

    public function __construct(private readonly ReturnService $returns, private readonly ReturnSettings $settings) {}

    public function index(Request $request)
    {
        $returnRequests = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with('order', 'seller', 'items.orderItem')
            ->latest('id')
            ->paginate(10);

        return ReturnRequestResource::collection($returnRequests);
    }

    public function show(ReturnRequest $returnRequest)
    {
        Gate::authorize('view', $returnRequest);

        return new ReturnRequestResource($returnRequest->load(self::RELATIONS));
    }

    /** البنود القابلة للإرجاع الآن مع الكمية ونهاية المهلة (قائمة فارغة إن لم يوجد). */
    public function returnable(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->load('items.vendor');
        $windowDays = $this->settings->windowDays();

        $lines = $order->items
            ->map(fn ($item) => ['item' => $item, 'quantity' => $this->returns->returnableQuantity($item)])
            ->filter(fn ($row) => $row['quantity'] > 0)
            ->map(fn ($row) => [
                'order_item_id' => $row['item']->id,
                'vendor_id' => $row['item']->vendor_id,
                'store_name' => $row['item']->vendor?->store_name,
                'product_name' => $row['item']->product_name,
                'variant_label' => $row['item']->variant_label,
                'unit_price' => $row['item']->unit_price / 100,
                'quantity' => $row['item']->quantity,
                'returnable_quantity' => $row['quantity'],
                'window_ends_at' => Carbon::parse($row['item']->commission_settled_at)->addDays($windowDays)->toIso8601String(),
            ])
            ->values();

        return response()->json(['data' => $lines]);
    }

    public function store(Request $request, Order $order): JsonResponse
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($returnRequest, 'تم إرسال طلب الإرجاع.', 201);
    }

    public function withdraw(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        Gate::authorize('withdraw', $returnRequest);

        try {
            $this->returns->withdraw($request->user(), $returnRequest);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($returnRequest, 'تم سحب طلب الإرجاع.');
    }

    public function dispute(Request $request, ReturnRequest $returnRequest): JsonResponse
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
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($returnRequest, 'تم رفع النزاع إلى الإدارة.');
    }

    private function respond(ReturnRequest $returnRequest, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => new ReturnRequestResource($returnRequest->fresh(self::RELATIONS)),
        ], $status);
    }
}
