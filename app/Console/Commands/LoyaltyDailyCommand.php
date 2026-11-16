<?php

namespace App\Console\Commands;

use App\Loyalty\LoyaltyCalculator;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Loyalty\PointsCouponIssuer;
use App\Models\AppNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** المهمة اليومية لنقاط الولاء. آمنة للتكرار. */
class LoyaltyDailyCommand extends Command
{
    protected $signature = 'loyalty:daily';

    protected $description = 'نقاط الولاء: إرجاع نقاط كوبونات منتهية، إنهاء صلاحية الدفعات، وتنبيه قبل الانتهاء بـ30 يوماً';

    public function handle(LoyaltyLedger $ledger, PointsCouponIssuer $coupons): int
    {
        // 1) كوبونات نقاط انتهت دون استخدام → النقاط تعود لدفعاتها (قبل الإنهاء، فالمنتهي منها يُسجَّل منتهياً)
        $restored = $coupons->restoreExpired();

        // 2) دفعات تجاوزت صلاحيتها
        $expired = $ledger->expireDue();

        // 3) تنبيه واحد لكل دفعة تنتهي خلال 30 يوماً
        $notified = 0;
        Tx::whereIn('type', Tx::LOT_TYPES)->where('remaining', '>', 0)->whereNull('expiry_notified_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->get()->groupBy('user_id')
            ->each(function ($lots, $userId) use (&$notified) {
                $points = (int) $lots->sum('remaining');
                $first = $lots->min('expires_at');
                AppNotification::send((int) $userId, 'loyalty_expiring',
                    LoyaltyCalculator::pointsLabel($points).' من نقاطك تنتهي قريباً',
                    'أقربها ينتهي في '.$first->format('Y-m-d').'. استخدمها في طلبك القادم أو حوّلها إلى كوبون.',
                    Route::has('customer.loyalty.index') ? route('customer.loyalty.index') : null);
                Tx::whereKey($lots->pluck('id'))->update(['expiry_notified_at' => now()]);
                $notified++;
            });

        $this->info("أُعيدت {$restored} نقطة من كوبونات منتهية، انتهت {$expired} نقطة، وأُرسل {$notified} تنبيه.");

        return self::SUCCESS;
    }
}
