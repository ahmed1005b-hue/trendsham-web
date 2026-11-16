<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** الستوري يخص متجر المستخدم. الرفض 403 بلا رسالة (مطابق لـ abort(403) الحالي). */
class StoryPolicy
{
    public function manage(User $user, Story $story): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $story->vendor_id === $storeId ? Response::allow() : Response::deny('');
    }
}
