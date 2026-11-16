<?php

namespace App\Showcases;

use App\Models\User;

interface ShowcaseWallet
{
    public function balance(User $seller): float;

    /** @throws InsufficientBalanceException */
    public function debit(User $seller, float $amount, string $reason, array $meta = []): void;

    public function credit(User $seller, float $amount, string $reason, array $meta = []): void;
}