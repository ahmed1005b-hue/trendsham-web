<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'store_logo' => $this->store_logo ? asset('storage/'.$this->store_logo) : null,
            'store_url' => route('stores.show', $this->resource),
            'is_verified' => $this->isIdentityVerified(),
            // رقم أحدث قصة ظاهرة؛ رابطها: store_url#offer-{id} يفتحها في صفحة المتجر
            'story_id' => $this->whenLoaded('latestStory', fn () => $this->latestStory?->id),
        ];
    }
}
