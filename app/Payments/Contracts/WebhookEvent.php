<?php

namespace App\Payments\Contracts;

/**
 * إشعار موحّد من البنك بعد تحويله لصيغتنا.
 *  kind = payment (دفع طلب) | card (نتيجة حفظ بطاقة)
 */
final class WebhookEvent
{
    public function __construct(
        public string $reference,       // مرجعنا (payments.reference أو customer_cards.reference)
        public string $status,          // paid | failed | cancelled | pending
        public ?string $txnId = null,   // رقم العملية لدى البنك
        public ?float $amount = null,   // المبلغ كما أرسله البنك (بالوحدة الصغرى)
        public array $raw = [],         // الجسم الكامل للإشعار
        public string $kind = 'payment',
        public ?string $cardToken = null,
        public ?string $cardBrand = null,
        public ?string $cardLast4 = null,
        public ?int $cardExpMonth = null,
        public ?int $cardExpYear = null,
        public ?string $cardHolder = null,
    ) {}
}
