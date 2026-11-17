<?php

namespace App\Auth\Social;

use RuntimeException;

/** سبب رفض الدخول الاجتماعي، برسالة جاهزة للعرض للمستخدم. */
class SocialLoginException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
