<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'seller_profile_id' => $this->seller_profile_id,
            'seller_name' => $this->whenLoaded('seller', fn () => $this->seller?->store_name),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'shipping_fee' => (int) $this->shipping_fee / 100,
            'refund_amount' => (int) $this->refund_amount / 100,
            'refused' => $this->refused_at !== null,
            // العميل يلغي طرد متجر واحد ما دام «قيد المراجعة» وفي الطلب طرود أخرى مفتوحة
            'can_be_cancelled' => $this->status === 'pending',
            'tracking_number' => $this->tracking_number,
            'shipping_company' => $this->whenLoaded('shippingCompany', fn () => $this->shippingCompany?->name),
            'shipped_at' => $this->shipped_at,
            'delivered_at' => $this->delivered_at,
        ];
    }
}
