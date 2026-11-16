<?php

namespace App\Notifications\Push;

/** نتيجة إرسال رسالة واحدة إلى Firebase. */
final class FcmResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly bool $invalidToken = false,
        public readonly ?string $error = null,
    ) {}

    public static function sent(): self
    {
        return new self(true);
    }

    public static function failed(string $error, bool $invalidToken = false): self
    {
        return new self(false, $invalidToken, $error);
    }
}
