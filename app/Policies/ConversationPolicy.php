<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    /** طرف في المحادثة: الزبون، أو بائع يملك متجرها (API المشترك). */
    public function participate(User $user, Conversation $conversation): Response
    {
        return $conversation->customer_id === $user->id
            || ($user->isSeller() && $user->sellerProfile?->id === $conversation->vendor_id)
            ? Response::allow()
            : Response::deny('');
    }

    /** شاشات الزبون: الزبون صاحب المحادثة فقط. */
    public function asCustomer(User $user, Conversation $conversation): Response
    {
        return $conversation->customer_id === $user->id ? Response::allow() : Response::deny('');
    }

    /** شاشات البائع: مالك متجر المحادثة فقط. */
    public function asSeller(User $user, Conversation $conversation): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $storeId === $conversation->vendor_id ? Response::allow() : Response::deny('');
    }
}
