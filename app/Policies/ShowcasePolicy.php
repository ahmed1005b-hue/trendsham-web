<?php

namespace App\Policies;

use App\Models\Showcase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** حجز المساحة المؤجرة يخص متجر المستخدم. الرفض 403 بلا رسالة (مطابق لـ abort(403) الحالي). */
class ShowcasePolicy
{
    public function manage(User $user, Showcase $showcase): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $showcase->seller_id === $storeId ? Response::allow() : Response::deny('');
    }
}
