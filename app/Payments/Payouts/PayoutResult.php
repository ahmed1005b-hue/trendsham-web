<?php

namespace App\Payments\Payouts;

/**
 * نتيجة إرسال حوالة.
 *  manual     = لا API؛ المدير يحوّل بنفسه ثم يسجّل رقم الحوالة
 *  paid       = تم التحويل فوراً
 *  processing = قبلها البنك وسيؤكد لاحقاً عبر webhook
 *  failed     = رفضها البنك ($message السبب)
 */
final class PayoutResult
{
    public function __construct(
        public string $status,
        public ?string $reference = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
