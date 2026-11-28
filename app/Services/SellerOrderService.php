<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Shipping\ParcelService;
use Illuminate\Database\Eloquent\Builder;

/**
 * ما يستطيع البائع فعله بطلبات منتجاته (الموقع والتطبيق — نفس القواعد).
 *  - يرى فقط الطلبات التي فيها منتجاته، وطلب البطاقة بعد تأكيد دفعه فقط.
 *  - يغيّر الحالة للأمام فقط: قيد التجهيز ← تم الشحن ← تم التسليم.
 *  - يلغي فقط قبل الشحن. لا يضع "مدفوع" (يحدده الدفع/الإدارة).
 *  - كل بائع يدير طرده فقط داخل الطلب المشترك مع متاجر أخرى.
 */
class SellerOrderService
{
    public const ALLOWED = ['processing', 'shipped', 'delivered', 'cancelled'];

    private const RANK = ParcelService::RANK;

    public function query(SellerProfile $vendor): Builder
    {
        return Order::releasedToSellers()->whereHas('items', fn ($q) => $q->where('vendor_id', $vendor->id));
    }

    public function owns(SellerProfile $vendor, Order $order): bool
    {
        return $order->items()->where('vendor_id', $vendor->id)->exists() && ! $order->isAwaitingOnlinePayment();
    }

    /** طرد البائع في هذا الطلب (جزء الطلب الخاص بمتجره). */
    public function parcelFor(SellerProfile $vendor, Order $order): Shipment
    {
        return app(ParcelService::class)->ensureFor($order)->get($vendor->id)
            ?? abort(403);
    }

    /** الحالات التي يستطيع البائع نقل طرده إليها الآن (لعرضها في القائمة). */
    public function nextStatuses(Order $order, SellerProfile $vendor): array
    {
        $parcel = app(ParcelService::class)->ensureFor($order)->get($vendor->id);
        if (! $parcel) {
            return [];
        }

        return array_values(array_filter(self::ALLOWED, fn ($to) => $this->transitionAllowed($parcel->status, $to)));
    }

    private function transitionAllowed(string $from, string $to): bool
    {
        return match (true) {
            ! isset(self::RANK[$from]) => false,                                   // ملغى أو غير معروف
            $to === 'cancelled' => self::RANK[$from] <= self::RANK['processing'],  // قبل الشحن فقط
            default => self::RANK[$to] > self::RANK[$from],                        // للأمام فقط
        };
    }

    /**
     * يغيّر البائع حالة طرده فقط؛ حالة الطلب الكاملة تُحسب من كل الطرود.
     *
     * @throws \DomainException برسالة عربية تُعرض للبائع
     */
    public function updateStatus(SellerProfile $vendor, Order $order, string $to, ?string $trackingNumber = null): Order
    {
        abort_unless($this->owns($vendor, $order), 403);

        if (! in_array($to, self::ALLOWED, true)) {
            throw new \DomainException('لا يمكن للبائع وضع هذه الحالة.');
        }

        $parcel = $this->parcelFor($vendor, $order);
        if ($trackingNumber !== null && trim($trackingNumber) !== '') {
            $parcel->update(['tracking_number' => trim($trackingNumber)]);
        }

        // الانتقال يُفحص على الحالة المقفولة: إن غيّرتها الإدارة لحظتها (مثلاً شحنت الطرد) يُرفض إلغاء البائع
        app(ParcelService::class)->change($parcel, $to, function (Shipment $locked, Order $lockedOrder) use ($to) {
            $from = $locked->status;
            if (! $this->transitionAllowed($from, $to)) {
                throw new \DomainException(__('لا يمكن نقل الطلب من ":from" إلى ":to".', ['from' => __(Shipment::STATUS_LABELS[$from] ?? $from), 'to' => __(Shipment::STATUS_LABELS[$to] ?? $to)]));
            }
            // آخر طرد في طلب مدفوع: إلغاؤه يلغي الطلب كله، والمال يرده المدير
            if ($to === 'cancelled' && $lockedOrder->needsRefundBeforeCancel()
                && $lockedOrder->shipments()->open()->whereKeyNot($locked->id)->doesntExist()) {
                throw new \DomainException(__('هذا الطلب مدفوع، فإلغاؤه كاملاً يكون عن طريق فريق الدعم حتى يُرد المبلغ للزبون. تواصل مع الدعم.'));
            }
        });

        return $order->refresh();
    }
}
