<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use App\Shipping\ParcelService;

/**
 * تغيير حالة الطلب من فريق المنصة (لوحة المدير ولوحة الموظف، في الموقع والتطبيق) بنفس القواعد.
 *
 *  - الحالة للأمام فقط: قيد التجهيز ← تم الشحن ← تم التسليم. خطأ في طرد واحد يُصلَّح من أدوات الطرود.
 *  - «مدفوع» لا يُختار من هنا: الدفع يأتي من البنك أو من زر «تم الدفع» عند المدير.
 *  - طلب بطاقة لم يُدفع لا يُجهَّز (كان يصل للبائع وهو غير مدفوع). إلغاؤه مسموح.
 *  - الإلغاء قبل الشحن فقط، وبسبب مكتوب. بعد الشحن: «رفض الاستلام» أو طلب إرجاع.
 *  - الطلب المدفوع لا يُلغى من هنا: يلغيه المدير مع تسجيل رد المال (OrderRefundCanceller).
 *  - الزبون وكل بائع في الطلب يُبلَّغون بالتغيير.
 */
class StaffOrderStatusChanger
{
    /** @var list<string> */
    public const STATUSES = ['processing', 'shipped', 'delivered', 'cancelled'];

    public const SHIPPED_CANCEL_MESSAGE = 'شُحن هذا الطلب فلا يُلغى. إن رفض الزبون الاستلام سجّل «رفض الاستلام» على الطرد، وإن استلمه فالإرجاع يكون بطلب إرجاع.';

    public const PAID_CANCEL_MESSAGE = 'هذا الطلب مدفوع، فيلغيه المدير مع تسجيل رد المال للزبون.';

    /** @throws \DomainException */
    public function change(User $staff, Order $order, string $to, ?string $reason = null): void
    {
        $reason = trim((string) $reason);
        if (! in_array($to, self::STATUSES, true)) {
            throw new \DomainException(__('الدفع يُؤكَّد من زر «تم الدفع»، لا من حالة الطلب.'));
        }
        if ($to === 'cancelled' && $reason === '') {
            throw new \DomainException(__('اكتب سبب الإلغاء.'));
        }

        $from = $order->changeStatus($to, function (Order $locked) use ($to): void {
            self::assertAllowed($locked, $to);
        });

        if ($from === $to) {
            return;
        }
        app(StaffActivityLog::class)->order($staff, $to === 'cancelled' ? 'order.cancel' : 'order.status', $order,
            ['from' => $from, 'to' => $to] + ($to === 'cancelled' ? ['reason' => $reason] : []));

        $label = Order::STATUS_LABELS[$to] ?? $to;
        AppNotification::send(
            $order->user_id,
            'order_status_changed',
            "تحديث على طلبك #{$order->order_number}",
            'الحالة الجديدة: '.$label.($to === 'cancelled' ? ' — السبب: '.$reason : ''),
            route('customer.orders.show', $order)
        );
        self::notifySellers($order, 'غيّر فريق المنصة حالة الطلب إلى: '.$label.($to === 'cancelled' ? ' — السبب: '.$reason : ''));
    }

    /**
     * قواعد الفريق على الطلب المقفول.
     *
     * @throws \DomainException
     */
    public static function assertAllowed(Order $locked, string $to): void
    {
        if ($to === 'cancelled') {
            if (self::hasShipped($locked)) {
                throw new \DomainException(__(self::SHIPPED_CANCEL_MESSAGE));
            }
            if ($locked->needsRefundBeforeCancel()) {
                throw new \DomainException(__(self::PAID_CANCEL_MESSAGE));
            }

            return;
        }

        if ($locked->isAwaitingOnlinePayment()) {
            throw new \DomainException(__('هذا الطلب ينتظر دفع البطاقة، فلا يُجهَّز قبل تأكيد البنك. يمكنك إلغاؤه فقط.'));
        }

        $rank = ParcelService::RANK;
        if (($rank[$to] ?? 0) < ($rank[$locked->status] ?? 0)) {
            throw new \DomainException(__('لا يُرجَع الطلب للخلف. إن كان طرد متجر بحالة خاطئة فصحّحه من أدوات الطرود.'));
        }
    }

    /** هل خرجت بضاعة من الطلب؟ (حالة الطلب نفسه أو أي طرد مفتوح؛ الطلبات القديمة قد تكون بلا طرود بعد) */
    public static function hasShipped(Order $order): bool
    {
        return in_array($order->status, ['shipped', 'delivered'], true)
            || $order->shipments()->open()->whereIn('status', ['shipped', 'in_transit', 'delivered'])->exists();
    }

    /** يُبلغ كل بائع له منتجات في الطلب (الطلب ظاهر له، فالرابط يعمل). */
    public static function notifySellers(Order $order, string $body): void
    {
        $vendorIds = $order->items()->distinct()->pluck('vendor_id');
        SellerProfile::whereKey($vendorIds)->pluck('user_id')->filter()->each(
            fn (int $userId) => AppNotification::send(
                $userId,
                'seller_order_updated',
                "تحديث على الطلب #{$order->order_number}",
                $body,
                route('seller.orders.show', $order),
            )
        );
    }
}
