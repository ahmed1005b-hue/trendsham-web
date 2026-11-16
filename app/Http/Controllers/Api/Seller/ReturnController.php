<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnRequestResource;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** طلبات الإرجاع لمتجر البائع في التطبيق: نفس قواعد لوحة البائع (ReturnService) والسياسة. */
class ReturnController extends Controller
{
    private const RELATIONS = ['order', 'customer', 'items.orderItem', 'events'];

    public function __construct(private readonly ReturnService $returns) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = in_array($status, ReturnRequest::STATUSES, true) ? $status : null;

        $returnRequests = ReturnRequest::query()
            ->where('seller_profile_id', $request->user()->sellerProfile->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with('order', 'customer', 'items.orderItem')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return ReturnRequestResource::collection($returnRequests);
    }

    public function show(ReturnRequest $returnRequest)
    {
        Gate::authorize('viewAsSeller', $returnRequest);

        return new ReturnRequestResource($returnRequest->load(self::RELATIONS));
    }

    public function approve(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        Gate::authorize('decide', $returnRequest);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->returns->sellerApprove($request->user()->sellerProfile, $returnRequest, $validated['note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($returnRequest, 'تمت الموافقة على طلب الإرجاع.');
    }

    public function reject(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        Gate::authorize('decide', $returnRequest);
        $validated = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        try {
            $this->returns->sellerReject($request->user()->sellerProfile, $returnRequest, $validated['note']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($returnRequest, 'تم رفض طلب الإرجاع.');
    }

    public function received(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        Gate::authorize('confirmReceived', $returnRequest);

        try {
            $this->returns->confirmReceived($request->user(), $returnRequest);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $this->respond($returnRequest, 'تم تأكيد استلام المرتجع.');
    }

    private function respond(ReturnRequest $returnRequest, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => new ReturnRequestResource($returnRequest->fresh(self::RELATIONS)),
        ]);
    }
}
