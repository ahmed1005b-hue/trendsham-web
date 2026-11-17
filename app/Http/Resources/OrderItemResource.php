<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productRelation = $this->whenLoaded('product');
        $product = ($productRelation && ! ($productRelation instanceof MissingValue)) ? $productRelation : null;
        $originalUnitPrice = $product ? (int) $product->price : null;
        $unitPrice = (int) $this->unit_price;
        $hasDeal = $originalUnitPrice !== null && $unitPrice < $originalUnitPrice;
        $dealPercent = $hasDeal && $originalUnitPrice > 0
            ? (int) round((($originalUnitPrice - $unitPrice) / $originalUnitPrice) * 100)
            : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'is_gift' => (bool) $this->is_gift,
            'vendor_id' => $this->vendor_id, // لربط البند بطرد متجره في الطلب
            'vendor_name' => $this->whenLoaded('vendor', fn () => $this->vendor->store_name),
            'order_number' => $this->whenLoaded('order', fn () => $this->order->order_number),
            'order_status' => $this->whenLoaded('order', fn () => $this->order->status),
            'unit_price' => $unitPrice / 100,
            'original_unit_price' => $originalUnitPrice !== null ? $originalUnitPrice / 100 : null,
            'has_deal_price' => $hasDeal,
            'deal_discount_percentage' => $dealPercent,
            'quantity' => $this->quantity,
            'subtotal' => $this->subtotal / 100,
            'can_be_reviewed' => $this->canBeReviewed(),
            'has_review' => $this->relationLoaded('review') ? $this->review !== null : null,
            'product' => $product ? new ProductResource($product) : null,
        ];
    }
}
