<?php

namespace App\Coupons;

/** سبب رفض "احفظ": already (موجود في المحفظة) | sold_out (نفد) | unavailable (غير متاح) */
class CouponCollectException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /** رمز HTTP لواجهة التطبيق */
    public function status(): int
    {
        return match ($this->reason) {
            'already' => 409,
            'sold_out' => 410,
            default => 404,
        };
    }
}
