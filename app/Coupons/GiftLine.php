<?php

namespace App\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

/**
 * بند الهدية في الطلب (داخل معاملة الدفع): سعره 0 فلا ربح ولا عمولة للبائع عليه،
 * ويُخصم من المخزون. الإلغاء يعيده مع باقي البنود (Order::restockItems).
 */
class GiftLine
{
    public function add(Order $order, CouponCampaign $campaign): OrderItem
    {
        $gift = Product::whereKey($campaign->gift_product_id)->lockForUpdate()->first();
        if (! $gift || ! $gift->isLive() || $gift->stock_quantity < 1) {
            throw new \DomainException('coupon:الهدية نفدت');
        }

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $gift->id,
            'vendor_id' => $gift->vendor_id,
            'product_name' => $gift->name,
            'unit_price' => 0,
            'quantity' => 1,
            'subtotal' => 0,
            'discount_amount' => 0,
            'is_gift' => true,
        ]);
        $gift->adjustStock(-1, 'sale', "هدية مع طلب رقم {$order->order_number}");

        return $item;
    }
}
