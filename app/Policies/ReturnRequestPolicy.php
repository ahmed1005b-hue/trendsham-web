<?php

namespace App\Policies;

use App\Models\User;
use App\Returns\Models\ReturnRequest;
use Illuminate\Auth\Access\Response;

/** قواعد الوصول إلى طلب الإرجاع. الرفض 403 بلا رسالة؛ قواعد الحالة تبقى في ReturnService. */
class ReturnRequestPolicy
{
    public function view(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->customerOwner($user, $returnRequest);
    }

    public function withdraw(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->customerOwner($user, $returnRequest);
    }

    public function dispute(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->customerOwner($user, $returnRequest);
    }

    /** عرض الطلب في لوحة البائع: صاحب المتجر فقط. */
    public function viewAsSeller(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->isSellerOwner($user, $returnRequest) ? Response::allow() : Response::deny('');
    }

    /** موافقة/رفض البائع: صاحب المتجر فقط. */
    public function decide(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->isSellerOwner($user, $returnRequest) ? Response::allow() : Response::deny('');
    }

    /** تأكيد الاستلام: صاحب المتجر أو فريق المنصة (المدير والموظف). */
    public function confirmReceived(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->isSellerOwner($user, $returnRequest) || $user->isPlatformStaff()
            ? Response::allow()
            : Response::deny('');
    }

    /** قرار الإدارة في النزاع: المدير أو الموظف. */
    public function adminDecide(User $user, ReturnRequest $returnRequest): Response
    {
        return $user->isPlatformStaff() ? Response::allow() : Response::deny('');
    }

    /** تسجيل إرجاع المال: المدير وحده. */
    public function recordRefund(User $user, ReturnRequest $returnRequest): Response
    {
        return $this->adminOnly($user);
    }

    private function customerOwner(User $user, ReturnRequest $returnRequest): Response
    {
        return $returnRequest->user_id === $user->id ? Response::allow() : Response::deny('');
    }

    private function isSellerOwner(User $user, ReturnRequest $returnRequest): bool
    {
        $storeId = $user->sellerProfile?->id;

        return $storeId !== null && $storeId === $returnRequest->seller_profile_id;
    }

    private function adminOnly(User $user): Response
    {
        return $user->role === 'admin' ? Response::allow() : Response::deny('');
    }
}
