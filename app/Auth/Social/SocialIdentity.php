<?php

namespace App\Auth\Social;

/** ما نعرفه عن الشخص بعد أن تحقّق منه Google أو Facebook. */
final class SocialIdentity
{
    public function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
    ) {}
}
