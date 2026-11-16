<?php

namespace App\Http\Resources;

use App\Support\ProductOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'color' => $this->color,
            'color_name' => $this->color_name,
            'color_hex' => ProductOptions::hexFor($this->color, $this->color_name),
            'size' => $this->size,
            'sku' => $this->sku,
            'price_adjustment' => $this->price_adjustment / 100,
            'effective_price' => $this->effective_price / 100,
            'formatted_price' => $this->formatted_price,
            'stock_quantity' => $this->stock_quantity,
            'is_active' => $this->is_active,
            'label' => $this->label,
        ];
    }
}
