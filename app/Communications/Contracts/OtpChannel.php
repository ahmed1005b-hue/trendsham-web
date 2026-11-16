<?php

namespace App\Communications\Contracts;

interface OtpChannel
{
    public function send(string $phone, string $code): bool;
}
