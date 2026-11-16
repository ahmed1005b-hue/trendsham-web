<?php

namespace App\Coupons;

/** نتيجة فحص كوبون على سلة: صالح؟ لماذا لا؟ كم يوفّر؟ وكيف يتوزع الخصم على البنود. */
final class EligibilityResult
{
    /** @param array<int,int> $lineDiscounts مفتاح = رقم السطر في السلة كما مُرِّر */
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $reason = null,
        public readonly int $discount = 0,
        public readonly int $shippingDiscount = 0,
        public readonly array $lineDiscounts = [],
        public readonly ?int $giftProductId = null,
        public readonly int $giftValue = 0,   // سعر منتج الهدية: يدخل في اختيار أفضل كوبون
    ) {}

    public static function no(string $reason): self
    {
        return new self(false, $reason);
    }

    public function saving(): int
    {
        return $this->discount + $this->shippingDiscount + $this->giftValue;
    }
}
