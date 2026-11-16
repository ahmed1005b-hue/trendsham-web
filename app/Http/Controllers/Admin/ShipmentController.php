<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function update(Request $request, Order $order, Shipment $shipment)
    {
        abort_unless($shipment->order_id === $order->id, 404);

        $validated = $request->validate([
            'shipping_company_id' => ['nullable', 'exists:shipping_companies,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,shipped,in_transit,delivered,returned'],
        ]);

        $wasShipped = $shipment->status !== 'pending';

        $shipment->update([
            ...$validated,
            'shipped_at' => $validated['status'] !== 'pending' && ! $wasShipped ? now() : $shipment->shipped_at,
            'delivered_at' => $validated['status'] === 'delivered' ? now() : $shipment->delivered_at,
        ]);

        $statusLabels = ['pending' => 'بانتظار الشحن', 'shipped' => 'تم الشحن', 'in_transit' => 'في الطريق', 'delivered' => 'تم التسليم', 'returned' => 'مرتجع'];

        AppNotification::send(
            $order->user_id,
            'shipment_updated',
            "تحديث شحن طلبك #{$order->order_number}",
            'الحالة الجديدة: '.($statusLabels[$validated['status']] ?? $validated['status']).
                (($validated['tracking_number'] ?? null) ? ' - رقم التتبع: '.$validated['tracking_number'] : ''),
            route('customer.orders.show', $order)
        );

        return back()->with('success', 'تم تحديث بيانات الشحن.');
    }
}