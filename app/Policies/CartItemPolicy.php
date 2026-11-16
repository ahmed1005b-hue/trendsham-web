<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CartItemPolicy
{
    public function manage(User $user, CartItem $cartItem): Response
    {
        return $cartItem->user_id === $user->id ? Response::allow() : Response::deny('');
    }
}
