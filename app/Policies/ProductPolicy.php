<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** المنتج يخص متجر المستخدم. الرفض 403 بلا رسالة (مطابق لـ abort(403) الحالي). */
class ProductPolicy
{
    public function manage(User $user, Product $product): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $product->vendor_id === $storeId ? Response::allow() : Response::deny('');
    }
}
