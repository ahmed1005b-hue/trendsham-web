<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** قواعد الوصول إلى الطلب. الرفض 403 بلا رسالة (مطابق لـ abort(403) الحالي). */
class OrderPolicy
{
    public function view(User $user, Order $order): Response
    {
        return $this->ownerOnly($user, $order);
    }

    /** الملكية فقط؛ قاعدة الحالة (pending → 409) تبقى في المتحكم/CustomerOrderCanceller. */
    public function cancel(User $user, Order $order): Response
    {
        return $this->ownerOnly($user, $order);
    }

    /** البائع الذي لمتجره عنصر واحد على الأقل في الطلب. */
    public function viewAsSeller(User $user, Order $order): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $order->items()->where('vendor_id', $storeId)->exists()
            ? Response::allow()
            : Response::deny('');
    }

    /** مطابق لـ InvoiceController الحالي: المالك أو المدير فقط (البائع لا يرى الفاتورة). */
    public function invoice(User $user, Order $order): Response
    {
        return $order->user_id === $user->id || $user->role === 'admin'
            ? Response::allow()
            : Response::deny('');
    }

    private function ownerOnly(User $user, Order $order): Response
    {
        return $order->user_id === $user->id ? Response::allow() : Response::deny('');
    }
}
