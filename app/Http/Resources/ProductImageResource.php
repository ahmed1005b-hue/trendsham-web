<?php

namespace App\Http\Resources;

use App\Support\ProductOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'url' => asset('storage/'.$this->path),
            'thumb_url' => $this->thumb_url,
            'path' => $this->path,
            'color' => $this->color,
            'color_name' => $this->color_name,
            'color_hex' => ProductOptions::hexFor($this->color, $this->color_name),
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}
