<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Shipping\ParcelService;
use Illuminate\Support\Facades\DB;

/**
 * إلغاء العميل لطلبه (من الموقع أو التطبيق أو المساعد).
 * مسموح فقط والطلب "قيد المراجعة"، ويُعاد الفحص تحت القفل حتى لا تُرجع الكمية مرتين.
 */
class CustomerOrderCanceller
{
    public const NOT_CANCELLABLE = 'ما بقدر تلغي طلب دخل مرحلة التجهيز أو تجاوزها.';

    /** الطلب المدفوع يلغيه المدير مع رد المال (OrderRefundCanceller)، لا الزبون وحده. */
    public const PAID_NEEDS_SUPPORT = 'طلبك مدفوع، فإلغاؤه يكون عن طريق فريق الدعم حتى يُرد لك المبلغ. تواصل معنا وسنساعدك.';

    /** @return bool false إذا لم يعد الطلب قابلاً للإلغاء */
    public function cancel(Order $order): bool
    {
        $done = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($locked?->status !== 'pending' || $locked->needsRefundBeforeCancel()) {
                return false;
            }

            // يرجع كمية المنتج واللون/المقاس للمخزون ويعكس الكوبون (Order::settleForStatusChange)
            $order->changeStatus('cancelled');

            if ($order->payment && $order->payment->status === 'pending') {
                $order->payment->update(['status' => 'failed']);
            }

            return true;
        });

        if ($done) {
            AppNotification::send(
                $order->user_id,
                'order_cancelled',
                "تم إلغاء طلبك #{$order->order_number}",
                null,
                route('customer.orders.show', $order)
            );
        }

        return $done;
    }

    /**
     * إلغاء تلقائي لطلب بطاقة لم يُدفع خلال المهلة: يعود المخزون والكوبون والنقاط ورصيد الهدايا.
     * يقفل الدفعات ثم الطلب (نفس ترتيب PaymentService::apply) ويعيد الفحص تحت القفل:
     * إن وصل تأكيد البنك قبله يبقى الطلب كما هو.
     *
     * @return bool false إذا دُفع الطلب أو لم يعد قابلاً للإلغاء
     */
    public function cancelUnpaid(Order $order): bool
    {
        $done = DB::transaction(function () use ($order) {
            Payment::where('order_id', $order->id)->lockForUpdate()->get();
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending' || ! $locked->isAwaitingOnlinePayment()) {
                return false;
            }

            $order->changeStatus('cancelled');
            Payment::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            $order->update(['payment_status' => 'cancelled']);

            return true;
        });

        if ($done) {
            AppNotification::send(
                $order->user_id,
                'order_cancelled',
                "تم إلغاء طلبك #{$order->order_number}",
                'لم يكتمل الدفع بالبطاقة خلال '.Order::ONLINE_PAYMENT_TIMEOUT_MINUTES.' دقيقة. يمكنك الطلب من جديد متى شئت.',
                route('customer.orders.show', $order)
            );
        }

        return $done;
    }

    /**
     * إلغاء طرد متجر واحد ما دام «قيد المراجعة»؛ بقية الطرود تكمل.
     * إن كان آخر طرد مفتوح فهو إلغاء للطلب كله.
     *
     * @return bool false إذا لم يعد الطرد قابلاً للإلغاء
     */
    public function cancelParcel(Order $order, Shipment $parcel): bool
    {
        if ($order->shipments()->open()->whereKeyNot($parcel->id)->doesntExist()) {
            return $this->cancel($order);
        }

        try {
            app(ParcelService::class)->change($parcel, 'cancelled', function (Shipment $locked) {
                if ($locked->status !== 'pending') {
                    throw new \DomainException(self::NOT_CANCELLABLE);
                }
            });
        } catch (\DomainException) {
            return false;
        }

        return true;
    }
}
