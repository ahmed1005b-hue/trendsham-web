<?php

namespace App\Http\Resources;

use App\Loyalty\Models\LoyaltyTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payment = $this->whenLoaded('payment') ? $this->payment : $this->payment()->first();
        $paymentStatus = $this->payment_status ?? ($payment?->status ? (in_array($payment->status, ['paid', 'failed', 'cancelled', 'refunded'], true) ? ($payment->status === 'paid' ? 'paid' : ($payment->status === 'refunded' ? 'paid' : $payment->status)) : 'pending') : 'unpaid');
        if (($payment?->status ?? null) === 'paid') {
            $paymentStatus = 'paid';
        }
        if (($this->payment_status ?? null) === 'paid') {
            $paymentStatus = 'paid';
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $paymentStatus,
            'payment_method' => $payment?->method, // cash_on_delivery | card | wallet
            'archived_at' => $this->archived_at,
            'subtotal' => $this->subtotal / 100,
            'shipping_fee' => $this->shipping_fee / 100,
            'discount_amount' => $this->discount_amount / 100,
            'points_redeemed' => (int) $this->points_redeemed,   // نقاط الولاء المستخدمة
            'points_discount' => $this->points_discount / 100,
            'gift_balance_used' => (int) $this->gift_balance_used / 100,
            'points_earned' => (int) LoyaltyTransaction::where('order_id', $this->id)->where('type', 'earn')->value('points'),
            'total' => $this->total / 100,
            'currency' => 'SYP',
            'shipping_name' => $this->shipping_name,
            'shipping_phone' => $this->shipping_phone,
            'shipping_address' => $this->shipping_address,
            'can_be_cancelled' => $this->status === 'pending',
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            // المتحكمات تحمّل shipment (مفرد) غالباً؛ نعرضها ضمن shipments ليقرأها التطبيق.
            'shipments' => $this->relationLoaded('shipments')
                ? ShipmentResource::collection($this->shipments)
                : ShipmentResource::collection($this->relationLoaded('shipment') && $this->shipment ? collect([$this->shipment]) : collect()),
            'created_at' => $this->created_at,
        ];
    }
}
