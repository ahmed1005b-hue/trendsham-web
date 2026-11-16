<?php

namespace App\Accounting;

final class LedgerType
{
    public const OPENING        = 'opening_balance';
    public const SALE           = 'sale';
    public const COMMISSION     = 'commission';
    public const REFUND         = 'refund';
    public const COMM_REVERSAL  = 'commission_reversal';
    public const PAYOUT         = 'payout';
    public const PAYOUT_REVERSAL = 'payout_reversal';
    public const SHOWCASE_FEE   = 'showcase_fee';
    public const SHOWCASE_REFUND = 'showcase_refund';
    public const ADJUSTMENT     = 'adjustment';
    public const COUPON_SHIPPING = 'coupon_shipping';
    public const COUPON_SHIPPING_REVERSAL = 'coupon_shipping_reversal';

    public static function labels(): array
    {
        return [
            self::OPENING        => 'رصيد افتتاحي',
            self::SALE           => 'مبيعات',
            self::COMMISSION     => 'عمولة المنصة',
            self::REFUND         => 'استرجاع للعميل',
            self::COMM_REVERSAL  => 'إعادة عمولة',
            self::PAYOUT         => 'سحب / تحويل',
            self::PAYOUT_REVERSAL => 'إرجاع حوالة فاشلة',
            self::SHOWCASE_FEE   => 'رسوم مساحة عرض',
            self::SHOWCASE_REFUND => 'استرجاع رسوم عرض',
            self::ADJUSTMENT     => 'تسوية يدوية',
            self::COUPON_SHIPPING => 'شحن مجاني من كوبونك',
            self::COUPON_SHIPPING_REVERSAL => 'إرجاع شحن كوبون',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }
}
