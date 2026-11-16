<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'method' => $this->method,
            'gateway' => $this->gateway ?? null,
            'status' => $this->status,
            'reference' => $this->reference ?? null,
            'paid_at' => $this->paid_at,
        ];
    }
}
