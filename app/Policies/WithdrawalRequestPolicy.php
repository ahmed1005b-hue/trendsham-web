<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Auth\Access\Response;

class WithdrawalRequestPolicy
{
    /** إيصال الحوالة: للمدير أو للبائع صاحب الطلب فقط (مطابق لـ Admin\WithdrawalController::receipt الحالي). */
    public function viewReceipt(User $user, WithdrawalRequest $withdrawalRequest): Response
    {
        return $user->role === 'admin' || $withdrawalRequest->vendor?->user_id === $user->id
            ? Response::allow()
            : Response::deny('');
    }
}
