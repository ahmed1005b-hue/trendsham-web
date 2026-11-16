<?php

namespace App\Notifications\Push;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\SellerProfile;

/** إشعار "طلب جديد" لكل بائع في الطلب، بمجموع منتجاته فقط (بلا الهدايا المجانية). */
class SellerOrderNotifier
{
    public function notify(Order $order): void
    {
        $order->loadMissing('items');

        $order->items
            ->groupBy('vendor_id')
            ->each(function ($items, $vendorId) use ($order) {
                $userId = SellerProfile::whereKey($vendorId)->value('user_id');
                if (! $userId) {
                    return;
                }

                $paid = $items->reject(fn ($i) => (bool) $i->is_gift);
                $total = (int) $paid->sum('subtotal');
                $count = (int) $items->sum('quantity');

                AppNotification::send(
                    $userId,
                    'seller_new_order',
                    "طلب جديد #{$order->order_number}",
                    "{$count} قطعة بقيمة ".number_format($total / 100).' ل.س — جهّزه للشحن.',
                    route('seller.orders.show', $order),
                );
            });
    }
}
