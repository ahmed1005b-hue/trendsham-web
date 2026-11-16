<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailableCouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->type === 'fixed' ? $this->value / 100 : $this->value,
            'min_order_amount' => $this->min_order_amount ? $this->min_order_amount / 100 : null,
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'expires_at' => $this->expires_at,
            'is_active' => $this->is_active,
            'scope' => $this->seller_profile_id ? ($this->product_id ? 'product' : 'seller') : 'platform',
            'scope_label' => $this->scopeLabel(),
            'seller_profile_id' => $this->seller_profile_id,
            'product_id' => $this->product_id,
        ];
    }
}