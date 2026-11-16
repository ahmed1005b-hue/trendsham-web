<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\SellerAccount;
use App\Models\SellerProfile;
use Illuminate\Console\Command;

class LedgerReconcile extends Command
{
    protected $signature = 'ledger:reconcile';
    protected $description = 'يقارن رصيد الدفتر برصيد المحفظة ومجموع القيود ويعرض أي فروق';

    public function handle(): int
    {
        $bad = 0;

        SellerAccount::chunk(200, function ($accounts) use (&$bad) {
            foreach ($accounts as $acc) {
                $sum = (float) LedgerEntry::forSeller($acc->seller_id)->selectRaw('COALESCE(SUM(credit - debit),0) as s')->value('s');

                // رصيد المحفظة الحقيقي على ملف المتجر (SellerProfile->wallet) بالقروش
                $profile = SellerProfile::with('wallet')->where('user_id', $acc->seller_id)->first();
                $wallet = round((float) ($profile?->wallet?->balance ?? 0) / 100, 2);

                if (abs($sum - (float) $acc->balance) > 0.009 || abs($wallet - (float) $acc->balance) > 0.009) {
                    $bad++;
                    $this->warn("بائع #{$acc->seller_id}: حساب={$acc->balance} | مجموع القيود={$sum} | محفظة={$wallet}");
                }
            }
        });

        $bad ? $this->error("وُجد {$bad} فرق/فروق.") : $this->info('كل الأرصدة متطابقة.');
        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
