<?php

namespace App\Policies;

use App\Models\DailyDeal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** العرض اليومي يخص متجر المستخدم عبر منتجه. الرفض 403 بالرسالة العربية الحالية لكل إجراء. */
class DailyDealPolicy
{
    public function manage(User $user, DailyDeal $deal): Response
    {
        return $this->ownedBy($user, $deal, 'غير مصرح لك بالتعديل على هذا العرض.');
    }

    public function delete(User $user, DailyDeal $deal): Response
    {
        return $this->ownedBy($user, $deal, 'غير مصرح لك بحذف هذا العرض.');
    }

    public function toggle(User $user, DailyDeal $deal): Response
    {
        return $this->ownedBy($user, $deal, 'غير مصرح لك بتعديل هذا العرض.');
    }

    private function ownedBy(User $user, DailyDeal $deal, string $message): Response
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $deal->product?->vendor_id === $storeId
            ? Response::allow()
            : Response::deny($message);
    }
}
