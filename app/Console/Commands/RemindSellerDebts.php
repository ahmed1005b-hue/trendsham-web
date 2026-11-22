<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Wallet;
use Illuminate\Console\Command;

/** تذكير أسبوعي لكل متجر عليه دين للمنصة (عمولات طلبات حصّل ثمنها بنفسه). */
class RemindSellerDebts extends Command
{
    protected $signature = 'sellers:remind-debts';

    protected $description = 'Remind sellers who owe the platform commission on cash they collected';

    public function handle(): int
    {
        $sent = 0;
        Wallet::with('vendor')->where('balance', '<', 0)->each(function (Wallet $wallet) use (&$sent) {
            if (! $wallet->vendor) {
                return;
            }
            AppNotification::send($wallet->vendor->user_id, 'seller_debt',
                __('تذكير: عليك مبلغ للمنصة'),
                __('عليك :amount ل.س عمولات طلبات حصّلت ثمنها بنفسك. يُخصم من مبيعاتك القادمة، أو سدّده للإدارة.', ['amount' => number_format(-$wallet->balance / 100)]),
                route('seller.wallet.index'));
            $sent++;
        });
        $this->info("تذكيرات أُرسلت: {$sent}");

        return self::SUCCESS;
    }
}
