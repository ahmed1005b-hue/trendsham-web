<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\StaffConversation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * دعم البائعين صندوق واحد لفريق المنصة: البائع لا يختار موظفاً بالاسم (كانت أسماء الفريق تظهر له،
 * ورسالته تنتظر شخصاً واحداً قد يكون مشغولاً). كل مدير وكل موظف يعمل في قسم الدعم يُشعَر ويرد.
 */
class SellerSupportTeam
{
    /** صاحب المحادثة المسجّل (عمود staff_id إلزامي): أقدم مدير نشط، وإلا أي عضو في فريق الدعم. */
    public function ownerId(): int
    {
        $id = (int) User::query()->where('role', 'admin')->where('status', 'active')->orderBy('id')->value('id')
            ?: (int) User::query()->where('role', 'admin')->orderBy('id')->value('id')
            ?: (int) $this->members()->first()?->id;

        if ($id === 0) {
            throw ValidationException::withMessages(['body' => __('الدعم غير متاح الآن، حاول لاحقاً.')]);
        }

        return $id;
    }

    /**
     * من يرد على البائعين: المدراء والموظفون المعتمدون الذين يعملون في قسم الدعم.
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('role', 'admin')
                ->orWhere(fn ($employees) => $employees->where('role', 'employee')->where('employee_status', 'approved')))
            ->get()
            ->filter(fn (User $user) => $user->canWorkOn('support'))
            ->values();
    }

    /** إشعار كل الفريق، كلٌّ برابط لوحته (المدير أو الموظف). */
    public function notify(StaffConversation $conversation, string $title, string $body): void
    {
        foreach ($this->members() as $member) {
            AppNotification::send(
                $member->id,
                'staff_message',
                $title,
                mb_substr($body, 0, 120),
                route($member->isAdmin() ? 'admin.support.show' : 'employee.support.show', $conversation)
            );
        }
    }
}
