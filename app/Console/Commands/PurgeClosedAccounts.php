<?php

namespace App\Console\Commands;

use App\Services\AccountClosureService;
use Illuminate\Console\Command;

/** يمسح بيانات العملاء الذين طلبوا الحذف وانتهت مهلة التراجع (يومياً). */
class PurgeClosedAccounts extends Command
{
    protected $signature = 'accounts:purge-closed';

    protected $description = 'Anonymize customer accounts whose deletion grace period has ended';

    public function handle(AccountClosureService $closure): int
    {
        $count = $closure->purgeDue();
        $this->info("Purged {$count} account(s).");

        return self::SUCCESS;
    }
}
