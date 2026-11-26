<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\CustomerOrderCanceller;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * يلغي طلبات البطاقة التي لم يكتمل دفعها خلال المهلة، فتعود كمياتها للمخزون.
 * المهلة تُحسب من آخر محاولة دفع: عميل بدأ الدفع للتو لا يُلغى طلبه. آمنة للتكرار.
 */
class CancelUnpaidCardOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid';

    protected $description = 'إلغاء طلبات البطاقة غير المدفوعة بعد انتهاء مهلة الدفع';

    public function handle(CustomerOrderCanceller $canceller): int
    {
        $cutoff = now()->subMinutes(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES);
        $cancelled = 0;

        Order::awaitingOnlinePayment()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('payments', fn (Builder $payments) => $payments
                ->where('status', 'pending')->where('created_at', '>', $cutoff))
            ->chunkById(100, function (Collection $orders) use ($canceller, &$cancelled) {
                foreach ($orders as $order) {
                    $cancelled += (int) $canceller->cancelUnpaid($order);
                }
            });

        $this->info("أُلغي {$cancelled} طلب غير مدفوع.");

        return self::SUCCESS;
    }
}
