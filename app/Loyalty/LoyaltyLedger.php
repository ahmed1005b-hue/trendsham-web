<?php

namespace App\Loyalty;

use App\Loyalty\Models\LoyaltyAccount;
use App\Loyalty\Models\LoyaltyAllocation;
use App\Loyalty\Models\LoyaltyTransaction as Tx;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Returns\Models\ReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * دفتر نقاط الولاء — المكان الوحيد الذي يغيّر الرصيد.
 * كل عملية داخل معاملة وتقفل صف رصيد العميل، فلا تُصرف نفس النقاط مرتين.
 * الثابت: balance = مجموع remaining للدفعات غير المنتهية، و Σ points = balance − debt.
 */
class LoyaltyLedger
{
    public function __construct(
        private readonly LoyaltySettings $settings,
        private readonly LoyaltyCalculator $calc,
    ) {}

    // ───────────────────────── الطلبات ─────────────────────────

    /** أول تسليم: نقطة لكل 10,000 ل.س مدفوعة (للعملاء فقط، مرة واحدة لكل طلب) */
    public function earnForOrder(Order $order): ?Tx
    {
        $user = $order->user;
        if (! $user || $user->role !== 'customer' || ! $this->settings->enabled()) {
            return null;
        }
        $points = $this->calc->pointsFor($this->calc->paidBase($order));
        if ($points <= 0) {
            return null;
        }

        $tx = $this->credit($user->id, Tx::EARN, $points, orderId: $order->id, onceKey: 'earn:'.$order->id);
        if ($tx) {
            $balance = $this->account($user->id)->balance;
            $this->notify($user->id, 'loyalty_earned',
                'كسبت '.LoyaltyCalculator::pointsLabel($points).' من طلبك #'.$order->order_number,
                'رصيدك الآن '.LoyaltyCalculator::pointsLabel($balance).'.');
        }

        return $tx;
    }

    /**
     * إرجاع طلب مسلَّم: تُسحب نقاطه (وإن كانت مصروفة يصبح الباقي ديناً يُسدَّد من الكسب القادم).
     * ما سحبته المرتجعات المستلمة من نفس الطلب يُطرح، فلا تُسحب النقاط مرتين.
     */
    public function reverseEarnForOrder(Order $order): ?Tx
    {
        $earn = Tx::where('type', Tx::EARN)->where('order_id', $order->id)->first();
        if (! $earn) {
            return null;
        }

        $points = $earn->points - $this->clawedBackByReturns($order->id);
        if ($points <= 0) {
            return null;
        }

        $tx = $this->debit($earn->user_id, Tx::REVERSE_EARN, $points, orderId: $order->id,
            onceKey: 'reverse_earn:'.$order->id, allowDebt: true, preferLotId: $earn->id);
        if ($tx) {
            $this->notify($earn->user_id, 'loyalty_reversed',
                'سُحبت '.LoyaltyCalculator::pointsLabel($points).' بسبب إرجاع الطلب #'.$order->order_number, null);
        }

        return $tx;
    }

    /**
     * سطر مرتجع مستلم: تُسحب نقاط الطلب بنسبة المبلغ المسترد من أساس النقاط
     * round(earn × refund / paidBase). الحساب تراكمي على كل سطور الطلب المستلمة فلا يتجاوز المجموع
     * نقاط الطلب ويساويها تماماً عند إرجاع كل شيء. مرة واحدة لكل سطر، ويسمح بالدَّين.
     */
    public function reverseEarnForReturn(ReturnItem $line): ?Tx
    {
        $orderId = (int) OrderItem::whereKey($line->order_item_id)->value('order_id');
        $earn = Tx::where('type', Tx::EARN)->where('order_id', $orderId)->first();
        $order = Order::find($orderId);
        $paidBase = $order ? $this->calc->paidBase($order) : 0;
        if (! $earn || $paidBase <= 0) {
            return null;
        }

        $refunded = (int) ReturnItem::query()
            ->whereIn('order_item_id', OrderItem::where('order_id', $orderId)->select('id'))
            ->whereNotNull('settled_at')->whereKeyNot($line->id)
            ->sum('refund_amount') + (int) $line->refund_amount;
        $due = min($earn->points, (int) round($earn->points * $refunded / $paidBase));
        $points = $due - $this->clawedBackByReturns($orderId);
        if ($points <= 0) {
            return null;
        }

        $tx = $this->debit($earn->user_id, Tx::REVERSE_EARN, $points, orderId: $orderId,
            onceKey: 'reverse_earn_return:'.$line->id, allowDebt: true, preferLotId: $earn->id);
        if ($tx) {
            $this->notify($earn->user_id, 'loyalty_reversed',
                'سُحبت '.LoyaltyCalculator::pointsLabel($points).' بسبب إرجاع منتجات من الطلب #'.$order->order_number, null);
        }

        return $tx;
    }

    /** مجموع ما سحبته المرتجعات من نقاط طلب (موجب) */
    private function clawedBackByReturns(int $orderId): int
    {
        return -(int) Tx::where('type', Tx::REVERSE_EARN)->where('order_id', $orderId)
            ->where('once_key', 'like', 'reverse_earn_return:%')->sum('points');
    }

    /** استبدال نقاط كخصم على طلب عند الدفع */
    public function redeemForOrder(User $user, Order $order, int $points): Tx
    {
        $this->assertCanRedeem($user, $points);

        return $this->debit($user->id, Tx::REDEEM_CHECKOUT, $points, orderId: $order->id,
            onceKey: 'redeem_checkout:'.$order->id)
            ?? throw new LoyaltyException('استُخدمت النقاط لهذا الطلب مسبقاً.');
    }

    /** إلغاء طلب قبل تسليمه: النقاط المستبدلة فيه تعود لدفعاتها الأصلية */
    public function restoreRedeemedForOrder(Order $order): int
    {
        $redeem = Tx::where('type', Tx::REDEEM_CHECKOUT)->where('order_id', $order->id)->first();

        return $redeem ? $this->restoreAllocations($redeem) : 0;
    }

    public function assertCanRedeem(User $user, int $points): void
    {
        if (! $this->settings->enabled()) {
            throw new LoyaltyException('برنامج النقاط متوقف حالياً.');
        }
        if ($user->status === 'suspended') {
            throw new LoyaltyException('لا يمكن استخدام النقاط في حساب موقوف.');
        }
        $min = $this->settings->minRedeem();
        if ($points < $min) {
            throw new LoyaltyException(__('تحتاج :points على الأقل للاستبدال.', ['points' => LoyaltyCalculator::localPointsLabel($min)]));
        }
        $balance = $this->account($user->id)->balance;
        if ($points > $balance) {
            throw new LoyaltyException(__('رصيدك :points فقط.', ['points' => LoyaltyCalculator::localPointsLabel($balance)]));
        }
    }

    // ───────────────────────── الإدارة ─────────────────────────

    public function adjust(User $customer, string $direction, int $points, string $reason, int $adminId): Tx
    {
        if ($points < 1) {
            throw new LoyaltyException('عدد النقاط يجب أن يكون 1 على الأقل.');
        }
        if ($direction === 'add') {
            return $this->credit($customer->id, Tx::ADMIN_ADD, $points, reason: $reason, adminId: $adminId);
        }
        if ($points > $this->account($customer->id)->balance) {
            throw new LoyaltyException('لا يمكن خصم أكثر من رصيد العميل.');
        }

        return $this->debit($customer->id, Tx::ADMIN_DEDUCT, $points, reason: $reason, adminId: $adminId);
    }

    // ───────────────────────── النواة ─────────────────────────

    /**
     * إضافة دفعة جديدة. تُسدَّد الديون أولاً منها.
     *
     * @return Tx|null null إن سبق تنفيذ نفس onceKey
     */
    public function credit(int $userId, string $type, int $points, ?int $orderId = null, ?string $reason = null,
        ?int $adminId = null, ?int $customerCouponId = null, ?string $onceKey = null): ?Tx
    {
        return DB::transaction(function () use ($userId, $type, $points, $orderId, $reason, $adminId, $customerCouponId, $onceKey) {
            $acct = $this->lockAccount($userId);
            if ($onceKey && Tx::where('once_key', $onceKey)->exists()) {
                return null;
            }

            $settle = min($acct->debt, $points);
            if ($settle > 0) {
                $reason = trim(($reason ? $reason.' — ' : '').'سُدّد منها '.LoyaltyCalculator::pointsLabel($settle).' مستحقة من طلب مرتجع');
            }

            $tx = Tx::create([
                'user_id' => $userId, 'type' => $type, 'points' => $points,
                'remaining' => $points - $settle,
                'expires_at' => now()->addMonths($this->settings->expiryMonths()),
                'order_id' => $orderId, 'customer_coupon_id' => $customerCouponId,
                'admin_id' => $adminId, 'reason' => $reason, 'once_key' => $onceKey,
            ]);

            $acct->balance += $points - $settle;
            $acct->debt -= $settle;
            if ($type === Tx::EARN) {
                $acct->lifetime_earned += $points;
            }
            $acct->save();

            return $tx;
        });
    }

    /**
     * استهلاك نقاط من الدفعات الأقدم انتهاءً أولاً، مع تسجيل التوزيع.
     *
     * @param  bool  $allowDebt  إن لم يكفِ الرصيد يُحمَّل الباقي ديناً (لسحب نقاط طلب مرتجع فقط)
     * @param  int|null  $preferLotId  دفعة تُستهلك قبل غيرها (دفعة الطلب المرتجع نفسه)
     * @return Tx|null null إن سبق تنفيذ نفس onceKey
     */
    public function debit(int $userId, string $type, int $points, ?int $orderId = null, ?string $reason = null,
        ?int $adminId = null, ?int $customerCouponId = null, ?string $onceKey = null,
        bool $allowDebt = false, ?int $preferLotId = null): ?Tx
    {
        return DB::transaction(function () use ($userId, $type, $points, $orderId, $reason, $adminId, $customerCouponId, $onceKey, $allowDebt, $preferLotId) {
            $acct = $this->lockAccount($userId);
            if ($onceKey && Tx::where('once_key', $onceKey)->exists()) {
                return null;
            }
            $this->expireLocked($acct);   // لا نصرف من دفعة انتهت ولم تُعالَج بعد

            if ($points > $acct->balance && ! $allowDebt) {
                throw new LoyaltyException(__('رصيدك :points فقط.', ['points' => LoyaltyCalculator::localPointsLabel($acct->balance)]));
            }

            $tx = Tx::create([
                'user_id' => $userId, 'type' => $type, 'points' => -$points,
                'order_id' => $orderId, 'customer_coupon_id' => $customerCouponId,
                'admin_id' => $adminId, 'reason' => $reason, 'once_key' => $onceKey,
            ]);

            $lots = Tx::where('user_id', $userId)->activeLots()->lockForUpdate()->get();
            if ($preferLotId) {
                $lots = $lots->sortBy(fn (Tx $l) => $l->id === $preferLotId ? 0 : 1)->values();
            }

            $need = $points;
            foreach ($lots as $lot) {
                if ($need === 0) {
                    break;
                }
                $take = min($need, $lot->remaining);
                $lot->decrement('remaining', $take);
                LoyaltyAllocation::create(['debit_transaction_id' => $tx->id, 'lot_transaction_id' => $lot->id, 'points' => $take]);
                $need -= $take;
            }

            $taken = $points - $need;
            $acct->balance -= $taken;
            $acct->debt += $need;   // صفر إلا مع allowDebt
            $acct->save();

            return $tx;
        });
    }

    /**
     * يعيد نقاط حركة استهلاك إلى دفعاتها الأصلية؛ ما انتهت دفعته يُسجَّل منتهياً فوراً. آمن للتكرار.
     */
    public function restoreAllocations(Tx $debit): int
    {
        return DB::transaction(function () use ($debit) {
            $acct = $this->lockAccount($debit->user_id);
            $onceKey = 'restore:'.$debit->id;
            if (Tx::where('once_key', $onceKey)->exists()) {
                return 0;
            }

            $live = 0;
            $expired = 0;
            foreach (LoyaltyAllocation::where('debit_transaction_id', $debit->id)->get() as $alloc) {
                $lot = Tx::whereKey($alloc->lot_transaction_id)->lockForUpdate()->first();
                if ($lot && $lot->expires_at && $lot->expires_at->isFuture()) {
                    $lot->increment('remaining', $alloc->points);
                    $live += $alloc->points;
                } else {
                    $expired += $alloc->points;
                }
            }
            $total = $live + $expired;
            if ($total === 0) {
                return 0;
            }

            Tx::create([
                'user_id' => $debit->user_id, 'type' => Tx::RESTORE, 'points' => $total, 'remaining' => 0,
                'order_id' => $debit->order_id, 'customer_coupon_id' => $debit->customer_coupon_id,
                'once_key' => $onceKey,
            ]);
            if ($expired > 0) {
                Tx::create(['user_id' => $debit->user_id, 'type' => Tx::EXPIRE, 'points' => -$expired,
                    'reason' => 'نقاط أُعيدت بعد انتهاء صلاحيتها']);
            }

            $acct->balance += $live;
            $acct->save();

            return $live;
        });
    }

    /** إنهاء صلاحية كل الدفعات المستحقة (المهمة اليومية). يعيد عدد النقاط المنتهية. */
    public function expireDue(): int
    {
        $total = 0;
        $userIds = Tx::whereIn('type', Tx::LOT_TYPES)->where('remaining', '>', 0)
            ->where('expires_at', '<=', now())->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $total += DB::transaction(fn () => $this->expireLocked($this->lockAccount($userId)));
        }

        return $total;
    }

    /** رصيد العميل (بلا قفل، للعرض) */
    public function account(int $userId): LoyaltyAccount
    {
        return LoyaltyAccount::firstOrNew(['user_id' => $userId], ['balance' => 0, 'debt' => 0, 'lifetime_earned' => 0]);
    }

    // ───────────────────────── داخلي ─────────────────────────

    private function lockAccount(int $userId): LoyaltyAccount
    {
        LoyaltyAccount::query()->insertOrIgnore([
            'user_id' => $userId, 'balance' => 0, 'debt' => 0, 'lifetime_earned' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return LoyaltyAccount::where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    /** يُستدعى والحساب مقفول */
    private function expireLocked(LoyaltyAccount $acct): int
    {
        $lots = Tx::where('user_id', $acct->user_id)->whereIn('type', Tx::LOT_TYPES)
            ->where('remaining', '>', 0)->where('expires_at', '<=', now())->lockForUpdate()->get();

        $sum = 0;
        foreach ($lots as $lot) {
            $n = $lot->remaining;
            $lot->update(['remaining' => 0]);
            $tx = Tx::create(['user_id' => $acct->user_id, 'type' => Tx::EXPIRE, 'points' => -$n]);
            LoyaltyAllocation::create(['debit_transaction_id' => $tx->id, 'lot_transaction_id' => $lot->id, 'points' => $n]);
            $sum += $n;
        }
        if ($sum > 0) {
            $acct->balance -= $sum;
            $acct->save();
        }

        return $sum;
    }

    private function notify(int $userId, string $type, string $title, ?string $body): void
    {
        AppNotification::send($userId, $type, $title, $body,
            Route::has('customer.loyalty.index') ? route('customer.loyalty.index') : null);
    }
}
