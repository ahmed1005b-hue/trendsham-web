<?php

namespace App\Payments\Contracts;

/**
 * نتيجة بدء عملية لدى البوابة.
 *  redirect = افتح $url (صفحة البنك أو تحقق 3-D Secure)
 *  manual   = لا دفع إلكتروني؛ $message يشرح للعميل
 *  paid     = تم الخصم فوراً (دفع ببطاقة محفوظة بدون تحقق إضافي)
 *  failed   = رُفضت العملية؛ $message سبب الرفض
 */
final class InitResult
{
    public function __construct(
        public string $type,
        public ?string $url = null,
        public ?string $message = null,
        public ?string $txnId = null,
        public array $raw = [],
    ) {}
}
