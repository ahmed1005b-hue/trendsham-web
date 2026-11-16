<?php

namespace App\Console\Commands;

use App\Notifications\Push\BroadcastService;
use Illuminate\Console\Command;

/** يرسل الإشعارات الجماعية التي حان موعدها (يعمل كل دقيقة من المجدول). */
class SendDueBroadcasts extends Command
{
    protected $signature = 'notifications:send-broadcasts';

    protected $description = 'إرسال الإشعارات الجماعية المستحقة للعملاء';

    public function handle(BroadcastService $service): int
    {
        $count = $service->sendDue();
        if ($count) {
            $this->info("أُرسل {$count} إشعار جماعي.");
        }

        return self::SUCCESS;
    }
}
