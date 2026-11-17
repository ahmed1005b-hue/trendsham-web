<?php

namespace App\Shipping;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * رفض الزبون استلام طلبه: المنصة تدفع أجرة الشحن للشركة، وتُسجَّل ديناً على الزبون
 * يُضاف إلى طلبه القادم، ويُمنع من الدفع عند الاستلام شهراً.
 */
class ShippingDebt
{
    public const COD_BLOCK_DAYS = 30;

    /**
     * يسجّل الرفض مرة واحدة: يلغي الطلب (فتعود الكميات) ويضيف الدين على الزبون.
     *
     * @return int الدين المضاف (صفر لطلب كان مدفوعاً)
     *
     * @throws \DomainException
     */
    public function recordRefusal(Order $order): int
    {
        $added = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->refused_at) {
                throw new \DomainException('سُجّل رفض هذا الطلب من قبل.');
            }
            if ($locked->status !== 'shipped') {
                throw new \DomainException('يُسجَّل رفض الاستلام فقط لطلب حالته «تم الشحن».');
            }

            $locked->update(['refused_at' => now()]);
            $locked->changeStatus('cancelled');

            $user = User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $debt = $locked->payment_status === 'paid' ? 0 : (int) $locked->shipping_cost;
            $until = now()->addDays(self::COD_BLOCK_DAYS);
            $user->forceFill([
                'shipping_debt' => (int) $user->shipping_debt + $debt,
                'cod_blocked_until' => $user->cod_blocked_until?->isAfter($until) ? $user->cod_blocked_until : $until,
            ])->save();

            return $debt;
        });

        $order->refresh();
        AppNotification::send($order->user_id, 'order_refused', 'سُجّل رفض استلام طلبك',
            $added > 0
                ? 'رفضت استلام الطلب '.$order->order_number.'. أجرة الشحن ('.number_format($added / 100).' ل.س) ستُضاف إلى طلبك القادم، ولا يمكنك الدفع عند الاستلام لمدة شهر.'
                : 'رفضت استلام الطلب '.$order->order_number.'، فلا يمكنك الدفع عند الاستلام لمدة شهر.',
            route('customer.orders.show', $order));

        return $added;
    }

    /** يأخذ دين الزبون ليُضاف إلى طلبه الجديد (داخل معاملة إنشاء الطلب). */
    public function takeForNewOrder(User $user): int
    {
        $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        $debt = (int) $locked->shipping_debt;
        if ($debt > 0) {
            $locked->forceFill(['shipping_debt' => 0])->save();
        }

        return $debt;
    }

    /** إلغاء طلب حُمّل عليه دين سابق يعيد الدين على الزبون، لأنه لم يدفعه. */
    public function restoreForCancelledOrder(Order $order): void
    {
        $debt = (int) $order->shipping_debt_charged;
        if ($debt > 0) {
            User::whereKey($order->user_id)->increment('shipping_debt', $debt);
        }
    }

    public static function codBlocked(User $user): bool
    {
        return (bool) $user->cod_blocked_until?->isFuture();
    }

    public static function codBlockedMessage(User $user): string
    {
        return __('لا يمكنك الدفع عند الاستلام حتى :date لأنك رفضت استلام طلب سابق. اختر طريقة دفع أخرى.', [
            'date' => $user->cod_blocked_until?->format('Y-m-d'),
        ]);
    }
}
