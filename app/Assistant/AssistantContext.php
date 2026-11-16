<?php

namespace App\Assistant;

use App\Models\ChatConversation;
use App\Models\User;

final class AssistantContext
{
    /**
     * @param  array|null  $pending  إجراء ينتظر التأكيد من الرد السابق مباشرة (مثل إلغاء طلب)
     * @param  int  $unknownStreak  كم رداً متتالياً لم نفهم فيه المستخدم قبل هذه الرسالة
     */
    public function __construct(
        public readonly ChatConversation $conversation,
        public readonly string $message,
        public readonly ?User $user = null,
        public readonly ?array $pending = null,
        public readonly int $unknownStreak = 0,
    ) {}

    public function isCustomer(): bool
    {
        return $this->user !== null;
    }
}
