<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\ShipmentController as WebShipmentController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingCompany;
use Illuminate\Http\Request;

/**
 * شركات الشحن + تحديث شحنة الطلب. تحديث الشحنة يعيد استخدام منطق الويب (الإشعار للعميل وتواريخ الشحن).
 */
class ShippingController extends WebShipmentController
{
    public function companies()
    {
        return response()->json([
            'data' => ShippingCompany::latest()->get()->map(fn ($c) => $this->company($c)),
        ]);
    }

    public function storeCompany(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(['data' => $this->company(ShippingCompany::create($validated))], 201);
    }

    public function toggleCompany(ShippingCompany $shippingCompany)
    {
        $shippingCompany->update(['is_active' => ! $shippingCompany->is_active]);

        return response()->json(['data' => $this->company($shippingCompany)]);
    }

    public function destroyCompany(ShippingCompany $shippingCompany)
    {
        $shippingCompany->delete();

        return response()->json(['message' => 'تم حذف شركة الشحن.']);
    }

    public function update(Request $request, Order $order, Shipment $shipment)
    {
        parent::update($request, $order, $shipment);

        return new OrderResource($order->fresh(['items.vendor', 'payment', 'shipments.shippingCompany', 'shipments.seller', 'user']));
    }

    private function company(ShippingCompany $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'is_active' => (bool) $c->is_active,
        ];
    }
}
