<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'question' => $this->question,
            'asker_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'helpful_count' => $this->helpful_count,
            'is_helpful' => $this->isHelpfulBy($user),
            'is_answered' => $this->relationLoaded('answers')
                ? $this->answers->isNotEmpty()
                : $this->hasAnswer(),
            'can_delete' => (bool) $user && (
                $user->id === $this->user_id || $user->isAdmin()
            ),
            'created_at' => $this->created_at,
            'answers' => ProductQuestionAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
