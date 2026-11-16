<?php

namespace App\Policies;

use App\Models\CustomerCard;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerCardPolicy
{
    public function manage(User $user, CustomerCard $card): Response
    {
        return $card->user_id === $user->id ? Response::allow() : Response::deny('');
    }
}
