<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * إلغاء طلب مدفوع: المدير وحده، قبل الشحن، ومع تسجيل كيف رُدّ المال للزبون.
 * الإلغاء نفسه يمر بنفس الطريق (المخزون، الكوبون، النقاط، رصيد الهدايا يعود تلقائياً)،
 * ثم تُعلَّم الدفعة «مُستردة» ويُحفظ المبلغ والطريقة والسبب في سجل نشاط الموظفين.
 */
class OrderRefundCanceller
{
    /** @var array<string, string> */
    public const METHODS = [
        'card' => 'إلى بطاقة الزبون',
        'bank_transfer' => 'تحويل بنكي',
        'cash' => 'نقداً',
    ];

    /**
     * @return int المبلغ المردود (بالقروش)
     *
     * @throws \DomainException
     */
    public function cancel(User $admin, Order $order, string $reason, string $method, ?string $note = null): int
    {
        if (! $admin->isAdmin()) {
            throw new \DomainException(__(StaffOrderStatusChanger::PAID_CANCEL_MESSAGE));
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException(__('اكتب سبب الإلغاء.'));
        }
        if (! array_key_exists($method, self::METHODS)) {
            throw new \DomainException(__('اختر كيف رُدّ المال للزبون.'));
        }

        $amount = DB::transaction(function () use ($order, $admin, $reason, $method, $note): int {
            Payment::where('order_id', $order->id)->lockForUpdate()->get();
            $amount = 0;

            $order->changeStatus('cancelled', function (Order $locked) use (&$amount): void {
                if (! $locked->needsRefundBeforeCancel()) {
                    throw new \DomainException(__('هذا الطلب غير مدفوع. ألغه من «تحديث حالة الطلب».'));
                }
                if (StaffOrderStatusChanger::hasShipped($locked)) {
                    throw new \DomainException(__(StaffOrderStatusChanger::SHIPPED_CANCEL_MESSAGE));
                }
                $amount = (int) $locked->total;
            });

            Payment::where('order_id', $order->id)->where('status', 'paid')->update(['status' => 'refunded']);
            $order->update(['payment_status' => 'refunded']);

            app(StaffActivityLog::class)->order($admin, 'order.refund_cancel', $order, [
                'amount' => number_format($amount / 100),
                'method' => $method,
                'reason' => $reason,
            ] + (trim((string) $note) !== '' ? ['note' => trim((string) $note)] : []));

            return $amount;
        });

        $money = number_format($amount / 100).' ل.س';
        AppNotification::send(
            $order->user_id,
            'order_cancelled',
            "تم إلغاء طلبك #{$order->order_number}",
            "رُدّ لك {$money} (".self::METHODS[$method].'). السبب: '.$reason,
            route('customer.orders.show', $order)
        );
        StaffOrderStatusChanger::notifySellers($order, 'ألغى فريق المنصة الطلب ورُدّ المبلغ للزبون — السبب: '.$reason);

        return $amount;
    }
}
