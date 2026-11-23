<?php

namespace App\Services;

use App\Models\Invitation;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * قبول دعوة بائع أو موظف (بحساب جديد أو بحساب عميل موجود). الدعوة تُقفل وتُفحص
 * داخل نفس المعاملة، فلا يقبلها شخصان معاً ولا تُستعمل مرتين.
 */
class InvitationService
{
    /**
     * @throws \DomainException برسالة عربية تُعرض للمستخدم
     */
    public function claim(Invitation $invitation, User $user, ?string $storeName = null): void
    {
        DB::transaction(function () use ($invitation, $user, $storeName) {
            $locked = Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if ($problem = $locked->problem()) {
                throw new \DomainException($problem);
            }
            if (! $locked->allows($user->phone, $user->email)) {
                throw new \DomainException(__('هذه الدعوة مخصصة لشخص آخر (رقم هاتف أو بريد مختلف).'));
            }
            if ($user->role !== 'customer') {
                throw new \DomainException(__('حسابك الحالي ليس حساب عميل، فلا يمكن قبول الدعوة به. أنشئ حساباً جديداً من الرابط.'));
            }

            if ($locked->role === 'seller') {
                $name = trim((string) ($storeName ?: $locked->store_name ?: $user->name));
                $profile = $user->sellerProfile()->first();
                // الدعوة موافقة مسبقة من الإدارة: المتجر يعمل فوراً، والتوثيق بالهوية لاحقاً للشارة
                $profile
                    ? $profile->update(['store_name' => $name, 'status' => 'approved', 'rejection_reason' => null])
                    : SellerProfile::create(['user_id' => $user->id, 'store_name' => $name, 'status' => 'approved']);
                $user->update(['role' => 'seller']);
            } else {
                $user->update(['role' => 'employee', 'employee_status' => 'approved', 'status' => 'active']);
            }

            $locked->update(['accepted_at' => now(), 'accepted_by' => $user->id]);
        });
    }
}
