<?php

namespace App\Shipping;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Shipment;
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
     * يسجّل رفض طرد مرة واحدة: يُلغى الطرد (فتعود كمياته ولا يُحسب ثمنه على الزبون)
     * وتُضاف أجرة شحنه ديناً على الزبون. إن كان الطرد الوحيد في الطلب أُلغي الطلب كله.
     *
     * @return int الدين المضاف (صفر لطلب كان مدفوعاً)
     *
     * @throws \DomainException
     */
    public function recordRefusal(Shipment $parcel): int
    {
        $added = DB::transaction(function () use ($parcel) {
            $order = Order::whereKey($parcel->order_id)->lockForUpdate()->firstOrFail();
            $locked = Shipment::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
            if ($locked->refused_at) {
                throw new \DomainException('سُجّل رفض هذا الطرد من قبل.');
            }
            if (! in_array($locked->status, ['shipped', 'in_transit'], true)) {
                throw new \DomainException('يُسجَّل رفض الاستلام فقط لطرد حالته «تم الشحن».');
            }

            $locked->update(['refused_at' => now()]);
            $order->update(['refused_at' => $order->refused_at ?? now()]);
            $debt = $order->payment_status === 'paid' ? 0 : (int) max($locked->shipping_cost, $locked->shipping_fee);
            app(ParcelService::class)->change($locked, 'cancelled');

            $user = User::whereKey($order->user_id)->lockForUpdate()->firstOrFail();
            $until = now()->addDays(self::COD_BLOCK_DAYS);
            $user->forceFill([
                'shipping_debt' => (int) $user->shipping_debt + $debt,
                'cod_blocked_until' => $user->cod_blocked_until?->isAfter($until) ? $user->cod_blocked_until : $until,
            ])->save();

            return $debt;
        });

        $order = $parcel->order()->first();
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
