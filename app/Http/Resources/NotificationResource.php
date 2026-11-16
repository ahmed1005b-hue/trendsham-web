<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            // يُخزَّن الإشعار بالعربية، ويُعرض بلغة القارئ الحالية
            'title' => \App\Support\MessageTranslator::translate($this->title),
            'body' => \App\Support\MessageTranslator::translate($this->body),
            'link' => $this->link,
            'group' => $this->group,
            'target' => $this->target,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at,
        ];
    }
}
