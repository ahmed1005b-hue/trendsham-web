<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
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
}
