<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\PaymentController as WebPaymentController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * تأكيد الدفع يدوياً أو تعليمه كفاشل من التطبيق؛ المنطق نفسه في متحكم الويب (PaymentService::apply).
 */
class PaymentController extends WebPaymentController
{
    public function markPaid(Request $request, Order $order, PaymentService $svc)
    {
        parent::markPaid($request, $order, $svc);

        return $this->order($order);
    }

    public function markFailed(Request $request, Order $order)
    {
        parent::markFailed($request, $order);

        return $this->order($order);
    }

    private function order(Order $order): OrderResource
    {
        return new OrderResource($order->fresh(['items.vendor', 'payment', 'shipments.shippingCompany', 'shipments.seller', 'user']));
    }
}
