<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'translated_name' => __('categories.'.$this->name, [], request()->getPreferredLanguage() ?? app()->getLocale()),
            'name_en' => __('categories.'.$this->name, [], 'en'),
            'name_tr' => __('categories.'.$this->name, [], 'tr'),
            'image' => $this->image ? asset('storage/'.$this->image) : null,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
