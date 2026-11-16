<?php

namespace App\Policies;

use App\Models\StaffConversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** محادثة الدعم تخص متجر المستخدم. الرفض 403 بلا رسالة (مطابق لـ abort(403) الحالي). */
class StaffConversationPolicy
{
    public function manage(User $user, StaffConversation $conversation): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $conversation->vendor_id === $storeId ? Response::allow() : Response::deny('');
    }
}
