<?php

namespace App\Accounting;

final class LedgerType
{
    public const OPENING = 'opening_balance';

    public const SALE = 'sale';

    public const COMMISSION = 'commission';

    public const REFUND = 'refund';

    public const COMM_REVERSAL = 'commission_reversal';

    public const PAYOUT = 'payout';

    public const PAYOUT_REVERSAL = 'payout_reversal';

    public const SHOWCASE_FEE = 'showcase_fee';

    public const SHOWCASE_REFUND = 'showcase_refund';

    public const ADJUSTMENT = 'adjustment';

    public const COUPON_SHIPPING = 'coupon_shipping';

    public const COUPON_SHIPPING_REVERSAL = 'coupon_shipping_reversal';

    public const SELF_SHIPPING = 'self_shipping';

    public const SELF_SHIPPING_REVERSAL = 'self_shipping_reversal';

    public const CASH_COLLECTED = 'cash_collected';

    public const CASH_COLLECTED_REVERSAL = 'cash_collected_reversal';

    public const DEBT_PAYMENT = 'debt_payment';

    public static function labels(): array
    {
        return [
            self::OPENING => 'رصيد افتتاحي',
            self::SALE => 'مبيعات',
            self::COMMISSION => 'عمولة المنصة',
            self::REFUND => 'استرجاع للعميل',
            self::COMM_REVERSAL => 'إعادة عمولة',
            self::PAYOUT => 'سحب / تحويل',
            self::PAYOUT_REVERSAL => 'إرجاع حوالة فاشلة',
            self::SHOWCASE_FEE => 'رسوم مساحة عرض',
            self::SHOWCASE_REFUND => 'استرجاع رسوم عرض',
            self::ADJUSTMENT => 'تسوية يدوية',
            self::COUPON_SHIPPING => 'شحن مجاني من كوبونك',
            self::COUPON_SHIPPING_REVERSAL => 'إرجاع شحن كوبون',
            self::SELF_SHIPPING => 'أجرة توصيل طردك بنفسك',
            self::SELF_SHIPPING_REVERSAL => 'إلغاء أجرة توصيل',
            self::CASH_COLLECTED => 'مبلغ حصّلته نقداً من الزبون',
            self::CASH_COLLECTED_REVERSAL => 'إلغاء مبلغ محصّل',
            self::DEBT_PAYMENT => 'تسديد دين للمنصة',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }
}
