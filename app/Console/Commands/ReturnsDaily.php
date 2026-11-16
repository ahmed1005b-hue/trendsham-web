<?php

namespace App\Console\Commands;

use App\Returns\ReturnService;
use Illuminate\Console\Command;

/** المهمة اليومية للمرتجعات: تصعيد الطلبات التي لم يرد عليها البائع وإغلاق المنتهية مهلتها. آمنة للتكرار. */
class ReturnsDaily extends Command
{
    protected $signature = 'returns:daily';

    protected $description = 'المرتجعات: تصعيد الطلبات المتأخرة وإغلاق ما انتهت مهلته';

    public function handle(ReturnService $service): int
    {
        $result = $service->runDaily();

        $this->info("صُعِّد {$result['escalated']} طلب وأُغلق {$result['closed']} طلب.");

        return self::SUCCESS;
    }
}
