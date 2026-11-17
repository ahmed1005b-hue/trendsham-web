<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Shipping\ParcelService;
use Illuminate\Http\Request;

/** المدير يعدّل طرد متجر داخل الطلب: شركة الشحن ورقم التتبع والحالة (الحالة عبر ParcelService). */
class ShipmentController extends Controller
{
    public function update(Request $request, Order $order, Shipment $shipment)
    {
        abort_unless($shipment->order_id === $order->id, 404);

        $validated = $request->validate([
            'shipping_company_id' => ['nullable', 'exists:shipping_companies,id'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,processing,shipped,in_transit,delivered,cancelled'],
        ]);

        $shipment->update([
            'shipping_company_id' => $validated['shipping_company_id'] ?? null,
            'tracking_number' => $validated['tracking_number'] ?? null,
        ]);

        try {
            app(ParcelService::class)->change($shipment, $validated['status']);
        } catch (\DomainException $e) {
            return back()->with('error', __($e->getMessage()));
        }

        return back()->with('success', 'تم تحديث بيانات الطرد.');
    }
}
