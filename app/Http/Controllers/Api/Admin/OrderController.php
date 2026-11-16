<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\AppNotification;
use App\Models\Order;
use Illuminate\Http\Request;

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

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,processing,shipped,delivered,cancelled'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $order->changeStatus($validated['status']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (array_key_exists('tracking_number', $validated) ||
            in_array($validated['status'], ['shipped', 'delivered'], true)) {
            $shipment = $order->shipment()->firstOrCreate([], ['status' => 'pending']);
            $shipmentUpdates = [];

            if (array_key_exists('tracking_number', $validated)) {
                $shipmentUpdates['tracking_number'] = $validated['tracking_number'];
            }
            if ($validated['status'] === 'shipped') {
                $shipmentUpdates['status'] = 'shipped';
                $shipmentUpdates['shipped_at'] = $shipment->shipped_at ?? now();
            } elseif ($validated['status'] === 'delivered') {
                $shipmentUpdates['status'] = 'delivered';
                $shipmentUpdates['shipped_at'] = $shipment->shipped_at ?? now();
                $shipmentUpdates['delivered_at'] = $shipment->delivered_at ?? now();
            }

            if ($shipmentUpdates !== []) {
                $shipment->update($shipmentUpdates);
            }
        }

        AppNotification::send($order->user_id, 'order_status_changed', "تحديث على طلبك #{$order->order_number}", null, null);

        return new OrderResource($order->fresh(['items', 'payment', 'shipment.shippingCompany']));
    }
}
