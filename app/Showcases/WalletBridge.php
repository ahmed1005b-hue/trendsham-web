<?php

namespace App\Showcases;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletBridge implements ShowcaseWallet
{
    public function balance(User $seller): float
    {
        $profile = $seller->sellerProfile ?? $seller->sellerProfile()->first();
        return (float) ($profile?->wallet?->balance ?? 0) / 100;
    }

    public function debit(User $seller, float $amount, string $reason, array $meta = []): void
    {
        DB::transaction(function () use ($seller, $amount, $reason, $meta) {
            $profile = $seller->sellerProfile ?? $seller->sellerProfile()->first();
            $wallet = $profile?->walletOrCreate();
            if (!$wallet) {
                throw new InsufficientBalanceException('لا توجد محفظة للبائع');
            }

            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $amountInCents = (int) round($amount * 100);

            if ($wallet->balance < $amountInCents) {
                throw new InsufficientBalanceException('رصيد المحفظة غير كافٍ');
            }

            $wallet->applyTransaction('debit', $amountInCents, $reason, $this->txExtra($meta));

            // قيد الدفتر: رسوم مساحة عرض (idempotent عبر showcase+id)
            if (($meta['showcase_id'] ?? null) && $reason === 'showcase_booking') {
                app(\App\Services\LedgerService::class)->showcaseFee(
                    $seller->id,
                    $amount,
                    "رسوم حجز مساحة عرض #{$meta['showcase_id']}",
                    (int) $meta['showcase_id']
                );
            }
        });
    }

    public function credit(User $seller, float $amount, string $reason, array $meta = []): void
    {
        DB::transaction(function () use ($seller, $amount, $reason, $meta) {
            $profile = $seller->sellerProfile ?? $seller->sellerProfile()->first();
            $wallet = $profile?->walletOrCreate();
            if (!$wallet) {
                return;
            }

            $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $amountInCents = (int) round($amount * 100);

            $wallet->applyTransaction('credit', $amountInCents, $reason, $this->txExtra($meta));

            // قيد الدفتر: استرجاع رسوم مساحة عرض (idempotent عبر showcase_refund+id)
            if (($meta['showcase'] ?? null) && $reason === 'showcase_refund') {
                app(\App\Services\LedgerService::class)->showcaseRefund(
                    $seller->id,
                    $amount,
                    "استرجاع رسوم مساحة عرض #{$meta['showcase']}",
                    (int) $meta['showcase']
                );
            }
        });
    }

    private function txExtra(array $meta): array
    {
        $note = $meta['note'] ?? null;
        if (!$note && !empty($meta)) {
            $note = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }
        return array_filter([
            'note' => $note,
        ]);
    }
}