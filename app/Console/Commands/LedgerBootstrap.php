<?php

namespace App\Console\Commands;

use App\Accounting\LedgerType;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class LedgerBootstrap extends Command
{
    protected $signature = 'ledger:bootstrap';
    protected $description = 'ينشئ قيد رصيد افتتاحي لكل بائع من رصيد المحفظة الحالي (آمن للتكرار)';

    public function handle(LedgerService $ledger): int
    {
        // رصيد المحفظة الحقيقي في هذا المشروع موجود على SellerProfile->wallet->balance (بقم）
        SellerProfile::with('wallet')->whereNotNull('user_id')->each(function (SellerProfile $profile) use ($ledger) {
            $userId = $profile->user_id;
            if (! $userId || ! User::whereKey($userId)->where('role', 'seller')->exists()) {
                return;
            }

            // الرصيد مخزّن بالقروش (cents)؛ الدفتر يعمل بالليرات
            $balance = round((float) ($profile->wallet?->balance ?? 0) / 100, 2);

            if (abs($balance) < 0.005) {
                return;
            }

            $ledger->post(
                $userId,
                LedgerType::OPENING,
                $balance < 0 ? abs($balance) : 0,
                $balance > 0 ? $balance : 0,
                'رصيد افتتاحي منقول من النظام السابق',
                'bootstrap',
                $profile->id
            );
        });

        $this->info('تم إنشاء الأرصدة الافتتاحية.');
        return self::SUCCESS;
    }
}
