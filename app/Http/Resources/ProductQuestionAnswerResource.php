<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductQuestionAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'answer' => $this->answer,
            'is_seller_answer' => (bool) $this->is_seller_answer,
            'author_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'author_role' => $this->whenLoaded('user', fn () => $this->user->role),
            'created_at' => $this->created_at,
        ];
    }
}
