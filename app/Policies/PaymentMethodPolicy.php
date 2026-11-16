<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** طريقة الاستلام تخص متجر المستخدم. الرفض 403 بلا رسالة (مطابق لـ abort(403) الحالي). */
class PaymentMethodPolicy
{
    public function manage(User $user, PaymentMethod $paymentMethod): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $paymentMethod->vendor_id === $storeId ? Response::allow() : Response::deny('');
    }
}
