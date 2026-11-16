<?php

namespace App\Policies;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AppNotificationPolicy
{
    public function markRead(User $user, AppNotification $notification): Response
    {
        return $notification->user_id === $user->id ? Response::allow() : Response::deny('');
    }
}
