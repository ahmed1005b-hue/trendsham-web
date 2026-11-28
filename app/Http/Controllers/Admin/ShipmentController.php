<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\StaffOrderStatusChanger;
use App\Shipping\ParcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** المدير يعدّل طرد متجر داخل الطلب: شركة الشحن ورقم التتبع والحالة (الحالة عبر ParcelService). */
class ShipmentController extends Controller
{
    public function update(Request $request, Order $order, Shipment $shipment)
    {
        try {
            $this->save($request, $order, $shipment);
        } catch (\DomainException $e) {
            return back()->with('error', __($e->getMessage()));
        }

        return back()->with('success', 'تم تحديث بيانات الطرد.');
    }

    /**
     * يحفظ ما أُرسل فقط (شركة الشحن ورقم التتبع لا يُمسحان إن لم يُرسلا)، ثم ينقل الحالة.
     * آخر طرد في طلب مدفوع لا يُلغى من هنا: الإلغاء مع رد المال من صفحة الطلب.
     *
     * @throws \DomainException
     */
    protected function save(Request $request, Order $order, Shipment $shipment): void
    {
        abort_unless($shipment->order_id === $order->id, 404);

        $validated = $request->validate([
            'shipping_company_id' => ['nullable', 'exists:shipping_companies,id,deleted_at,NULL'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,processing,shipped,in_transit,delivered,cancelled'],
        ]);

        $details = Arr::only($validated, ['shipping_company_id', 'tracking_number']);
        if ($details !== []) {
            $shipment->update($details);
        }

        $from = $shipment->status;
        $parcel = app(ParcelService::class)->change($shipment, $validated['status'], function (Shipment $locked, Order $lockedOrder) use ($validated) {
            if ($validated['status'] === 'cancelled' && $lockedOrder->needsRefundBeforeCancel()
                && $lockedOrder->shipments()->open()->whereKeyNot($locked->id)->doesntExist()) {
                throw new \DomainException(StaffOrderStatusChanger::PAID_CANCEL_MESSAGE);
            }
        });

        if ($parcel->status !== $from && ($sellerUserId = $parcel->seller?->user_id)) {
            AppNotification::send($sellerUserId, 'seller_order_updated', "تحديث على الطلب #{$order->order_number}",
                'غيّر فريق المنصة حالة طردك إلى: '.$parcel->statusLabel(), route('seller.orders.show', $order));
        }
    }
}
