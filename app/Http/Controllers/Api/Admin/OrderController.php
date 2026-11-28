<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderRefundCanceller;
use App\Services\StaffOrderStatusChanger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('user')->latest()->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        $order->load('items.vendor', 'payment', 'shipments.shippingCompany', 'shipments.seller', 'user');

        return new OrderResource($order);
    }

    /**
     * نفس قواعد لوحة المدير في الموقع (StaffOrderStatusChanger): الطرود تتبع الطلب عبر ParcelService،
     * ورقم التتبع يُكتب على الطرود المفتوحة فقط (كان يُكتب على أول طرد مباشرة فيعيد فتح طرد ملغى).
     */
    public function updateStatus(Request $request, Order $order, StaffOrderStatusChanger $changer): OrderResource|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(StaffOrderStatusChanger::STATUSES)],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:500'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ], [
            'status.in' => __('الدفع يُؤكَّد من زر «تم الدفع»، لا من حالة الطلب.'),
            'reason.required_if' => __('اكتب سبب الإلغاء.'),
        ]);

        try {
            $changer->change($request->user(), $order, $validated['status'], $validated['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (filled($validated['tracking_number'] ?? null)) {
            $order->shipments()->open()->update(['tracking_number' => $validated['tracking_number']]);
        }

        return new OrderResource($order->fresh(['items.vendor', 'payment', 'shipments.shippingCompany', 'shipments.seller', 'user']));
    }

    /** إلغاء طلب مدفوع مع تسجيل رد المال (نفس زر لوحة المدير في الموقع). */
    public function cancelWithRefund(Request $request, Order $order, OrderRefundCanceller $canceller): OrderResource|JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'refund_method' => ['required', Rule::in(array_keys(OrderRefundCanceller::METHODS))],
            'refund_note' => ['nullable', 'string', 'max:255'],
        ], [
            'reason.required' => __('اكتب سبب الإلغاء.'),
            'refund_method.required' => __('اختر كيف رُدّ المال للزبون.'),
        ]);

        try {
            $canceller->cancel($request->user(), $order, $validated['reason'], $validated['refund_method'], $validated['refund_note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OrderResource($order->fresh(['items.vendor', 'payment', 'shipments.shippingCompany', 'shipments.seller', 'user']));
    }
}
