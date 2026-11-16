<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\SellerProfile;
use Illuminate\Database\Eloquent\Builder;

/**
 * ما يستطيع البائع فعله بطلبات منتجاته (الموقع والتطبيق — نفس القواعد).
 *  - يرى فقط الطلبات التي فيها منتجاته.
 *  - يغيّر الحالة للأمام فقط: قيد التجهيز ← تم الشحن ← تم التسليم.
 *  - يلغي فقط قبل الشحن. لا يضع "مدفوع" (يحدده الدفع/الإدارة).
 *  - الطلب المشترك مع متاجر أخرى تديره الإدارة.
 */
class SellerOrderService
{
    public const ALLOWED = ['processing', 'shipped', 'delivered', 'cancelled'];

    private const RANK = ['pending' => 0, 'paid' => 0, 'processing' => 1, 'shipped' => 2, 'delivered' => 3];

    public function query(SellerProfile $vendor): Builder
    {
        return Order::whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id));
    }

    public function owns(SellerProfile $vendor, Order $order): bool
    {
        return $order->items()->where('vendor_id', $vendor->id)->exists();
    }

    /** الحالات التي يستطيع البائع نقل الطلب إليها الآن (لعرضها في القائمة). */
    public function nextStatuses(Order $order): array
    {
        if (! $order->isSingleVendor()) {
            return [];
        }

        return array_values(array_filter(self::ALLOWED, fn ($to) => $this->transitionAllowed($order->status, $to)));
    }

    private function transitionAllowed(string $from, string $to): bool
    {
        return match (true) {
            ! isset(self::RANK[$from]) => false,                                   // ملغى أو غير معروف
            $to === 'cancelled' => self::RANK[$from] <= self::RANK['processing'],  // قبل الشحن فقط
            default => self::RANK[$to] > self::RANK[$from],                        // للأمام فقط
        };
    }

    /** @throws \DomainException برسالة عربية تُعرض للبائع */
    public function updateStatus(SellerProfile $vendor, Order $order, string $to): Order
    {
        abort_unless($this->owns($vendor, $order), 403);

        if (! in_array($to, self::ALLOWED, true)) {
            throw new \DomainException('لا يمكن للبائع وضع هذه الحالة.');
        }
        if (! $order->isSingleVendor()) {
            throw new \DomainException('هذا الطلب مشترك مع متاجر أخرى، وتدير الإدارة حالته.');
        }

        // الانتقال يُفحص على الحالة المقفولة: إن غيّرتها الإدارة لحظتها (مثلاً شحنت الطلب) يُرفض إلغاء البائع
        $order->changeStatus($to, function (Order $locked) use ($to) {
            $from = $locked->status;
            if (! $this->transitionAllowed($from, $to)) {
                throw new \DomainException(__('لا يمكن نقل الطلب من ":from" إلى ":to".', ['from' => __(Order::STATUS_LABELS[$from] ?? $from), 'to' => __(Order::STATUS_LABELS[$to] ?? $to)]));
            }
        });

        AppNotification::send(
            $order->user_id,
            'order_status_changed',
            "تحديث حالة طلب رقم {$order->order_number}",
            'حالة طلبك أصبحت: '.(Order::STATUS_LABELS[$to] ?? $to),
            route('customer.orders.show', $order)
        );

        return $order;
    }
}
