<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->whenLoaded('product');
        $unitPrice = $this->unit_price ?? ($product ? (int) $product->effective_price : 0);
        $originalUnitPrice = $this->original_unit_price ?? ($product ? (int) $product->price : 0);
        $deal = $product && $product->relationLoaded('activeDailyDeal') ? $product->activeDailyDeal : null;

        return [
            'id' => $this->id,
            'variant_id' => $this->variant_id,
            'variant' => $this->whenLoaded('variant', function () {
                return $this->variant ? [
                    'id' => $this->variant->id,
                    'color' => $this->variant->color,
                    'color_name' => $this->variant->color_name,
                    'size' => $this->variant->size,
                    'label' => $this->variant->label,
                    'stock_quantity' => $this->variant->stock_quantity,
                ] : null;
            }),
            'quantity' => $this->quantity,
            'unit_price' => $unitPrice / 100,
            'original_unit_price' => $originalUnitPrice / 100,
            'has_active_deal' => $deal !== null || $unitPrice < $originalUnitPrice,
            'deal_discount_percentage' => $deal?->discount_percentage,
            'subtotal' => $this->subtotal / 100,
            'product' => new ProductResource($product),
        ];
    }
}
