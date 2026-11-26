<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\User;

/**
 * تغيير حالة الطلب من فريق المنصة (لوحة المدير ولوحة الموظف، في الموقع والتطبيق) بنفس القواعد.
 *
 * الموظف لا يضع «مدفوع» بيده (الدفع يأتي من البنك أو من زر المدير)، ولا يُجهّز طلب بطاقة
 * لم يُدفع بعد: نقله للتجهيز كان يوصله للبائع وهو غير مدفوع. إلغاؤه مسموح.
 */
class StaffOrderStatusChanger
{
    /** @var list<string> */
    public const EMPLOYEE_STATUSES = ['processing', 'shipped', 'delivered', 'cancelled'];

    /** @throws \DomainException */
    public function change(User $staff, Order $order, string $to): void
    {
        if (! $staff->isAdmin() && ! in_array($to, self::EMPLOYEE_STATUSES, true)) {
            throw new \DomainException(__('هذه الحالة يضعها المدير فقط.'));
        }

        $order->changeStatus($to, function (Order $locked) use ($staff, $to): void {
            if (! $staff->isAdmin() && $to !== 'cancelled' && $locked->isAwaitingOnlinePayment()) {
                throw new \DomainException(__('هذا الطلب ينتظر دفع البطاقة، فلا يُجهَّز قبل تأكيد البنك. يمكنك إلغاؤه فقط.'));
            }
        });

        AppNotification::send(
            $order->user_id,
            'order_status_changed',
            "تحديث على طلبك #{$order->order_number}",
            'الحالة الجديدة: '.(Order::STATUS_LABELS[$to] ?? $to),
            route('customer.orders.show', $order)
        );
    }
}
