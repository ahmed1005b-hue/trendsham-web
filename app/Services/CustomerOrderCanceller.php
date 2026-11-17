<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
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

    /** @return bool false إذا لم يعد الطلب قابلاً للإلغاء */
    public function cancel(Order $order): bool
    {
        $done = DB::transaction(function () use ($order) {
            if (Order::whereKey($order->id)->lockForUpdate()->value('status') !== 'pending') {
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
