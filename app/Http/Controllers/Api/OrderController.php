<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\CustomerOrderCanceller;
use App\Shipping\ParcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()->orders()->latest()->paginate(10);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order)
    {
        Gate::authorize('view', $order);

        app(ParcelService::class)->ensureFor($order);
        $order->load('items.review', 'items.product.primaryImage', 'items.product.images', 'items.product.activeDailyDeal', 'items.vendor', 'payment', 'shipments.seller', 'shipments.shippingCompany');

        return new OrderResource($order);
    }

    public function items(Request $request, Order $order)
    {
        Gate::authorize('view', $order);

        $order->load('items.review', 'items.product.primaryImage', 'items.product.images', 'items.product.activeDailyDeal', 'items.vendor');

        return OrderItemResource::collection($order->items);
    }

    public function cancel(Request $request, Order $order, CustomerOrderCanceller $canceller)
    {
        Gate::authorize('cancel', $order);
        abort_unless($order->status === 'pending', 409, $canceller::NOT_CANCELLABLE);
        abort_if($order->needsRefundBeforeCancel(), 409, __($canceller::PAID_NEEDS_SUPPORT));
        abort_unless($canceller->cancel($order), 409, $canceller::NOT_CANCELLABLE);

        return new OrderResource($order->fresh(['items.review', 'items.product.primaryImage', 'items.product.images', 'items.product.activeDailyDeal', 'items.vendor', 'payment', 'shipments.seller', 'shipments.shippingCompany']));
    }

    /** إلغاء طرد متجر واحد من الطلب ما دام «قيد المراجعة». */
    public function cancelParcel(Request $request, Order $order, Shipment $shipment, CustomerOrderCanceller $canceller)
    {
        Gate::authorize('cancel', $order);
        abort_unless($shipment->order_id === $order->id, 404);
        abort_if($order->needsRefundBeforeCancel() && $order->shipments()->open()->whereKeyNot($shipment->id)->doesntExist(),
            409, __($canceller::PAID_NEEDS_SUPPORT));
        abort_unless($canceller->cancelParcel($order, $shipment), 409, $canceller::NOT_CANCELLABLE);

        return new OrderResource($order->fresh(['items.review', 'items.product.primaryImage', 'items.product.images', 'items.product.activeDailyDeal', 'items.vendor', 'payment', 'shipments.seller', 'shipments.shippingCompany']));
    }
}
