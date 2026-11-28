<?php

namespace App\Notifications\Push;

/**
 * تصنيف أنواع الإشعارات في مجموعات يتحكم بها المستخدم من صفحة الإعدادات.
 * نفس النوع (مثل new_message) يقع في مجموعة العميل أو البائع حسب دور المستلم.
 */
final class NotificationGroups
{
    public const GENERAL = 'general';

    public const CRITICAL_GROUP = 'critical';

    public const OFFERS = 'offers';

    /** تُرسل دائماً مهما كانت الإعدادات (أمان ومال). */
    public const CRITICAL = ['store_suspended', 'store_unsuspended', 'withdrawal_failed', 'payment_duplicate', 'order_refused'];

    /** دور ← مجموعة ← أنواع. نوع ينتهي بـ * = بادئة. */
    public const MAP = [
        'customer' => [
            'orders' => ['order_created', 'order_status_changed', 'order_cancelled', 'order_refused', 'shipment_updated', 'payment_confirmed', 'payment', 'cart_reminder'],
            'messages' => ['new_message', 'staff_message', 'product_question_answer'],
            'coupons_points' => ['coupon_received', 'loyalty_expiring', 'referral_*', 'gift_card_*'],
            'favorites' => ['price_drop'],
            'offers' => ['store_new_offer', 'store_new_product', 'broadcast'],
        ],
        'seller' => [
            'seller_orders' => ['seller_new_order', 'seller_order_updated'],
            'seller_messages' => ['new_message', 'product_question', 'staff_message'],
            'seller_store' => ['product_approved', 'product_rejected', 'seller_approved', 'seller_rejected', 'showcase_*'],
            'seller_payouts' => ['withdrawal_approved', 'withdrawal_paid', 'withdrawal_rejected'],
            'seller_coupons' => ['seller_coupon_review'],
        ],
    ];

    private const LABELS = [
        'orders' => ['الطلبات والدفع والشحن', 'تأكيد الطلب، الشحن، التسليم، الدفع، وتذكير السلة'],
        'messages' => ['الرسائل والردود', 'رسائل المتاجر والدعم وأجوبة أسئلتك'],
        'coupons_points' => ['الكوبونات والنقاط', 'كوبون جديد في محفظتك وتنبيه انتهاء النقاط'],
        'favorites' => ['المفضلة', 'انخفاض سعر منتج أضفته إلى مفضلتك'],
        'offers' => ['العروض والمنتجات الجديدة', 'من المتاجر التي تتابعها، حتى 3 يومياً ولا شيء ليلاً'],
        'seller_orders' => ['الطلبات الجديدة', 'طلب جديد على منتجات متجرك'],
        'seller_messages' => ['الرسائل والأسئلة', 'رسائل العملاء والدعم وأسئلة المنتجات'],
        'seller_store' => ['المتجر والمنتجات', 'قبول المنتجات أو رفضها وحالة المتجر والعروض المدفوعة'],
        'seller_payouts' => ['السحب والأرباح', 'قبول طلبات السحب وتحويلها'],
        'seller_coupons' => ['كوبونات المتجر', 'نتيجة مراجعة الإدارة لكوبوناتك'],
    ];

    public const ALWAYS_ON_NOTE = 'إشعارات الأمان والمال المهمة تصلك دائماً.';

    /** مجموعات دور المستخدم بالاسم والوصف (بلا حالة التفعيل). */
    public static function groupsFor(string $role): array
    {
        return collect(array_keys(self::MAP[$role] ?? []))
            ->map(fn (string $key) => ['key' => $key, 'label' => self::LABELS[$key][0], 'description' => self::LABELS[$key][1]])
            ->all();
    }

    public static function keysFor(string $role): array
    {
        return array_keys(self::MAP[$role] ?? []);
    }

    public static function groupOf(string $type, string $role): string
    {
        if (self::isCritical($type)) {
            return self::CRITICAL_GROUP;
        }

        foreach (self::MAP[$role] ?? [] as $group => $types) {
            foreach ($types as $t) {
                if ($t === $type || (str_ends_with($t, '*') && str_starts_with($type, rtrim($t, '*')))) {
                    return $group;
                }
            }
        }

        return self::GENERAL;
    }

    public static function isCritical(string $type): bool
    {
        return in_array($type, self::CRITICAL, true);
    }
}
