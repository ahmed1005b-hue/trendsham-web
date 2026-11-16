<?php

namespace App\Policies;

use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderItemPolicy
{
    /** عنصر طلب يملكه الزبون؛ قواعد «يمكن تقييمه» تبقى في المتحكم. */
    public function review(User $user, OrderItem $orderItem): Response
    {
        return $orderItem->order->user_id === $user->id ? Response::allow() : Response::deny('');
    }
}
