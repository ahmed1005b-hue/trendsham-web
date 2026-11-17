<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\CartItem;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * تذكير الزبون بسلة تركها دون إتمام الطلب. تذكير واحد لكل «لمسة» للسلة
 * (إضافة/تعديل بند تبدأ دورة جديدة)، ولا أكثر من تذكير كل بضعة أيام.
 * لا يحتاج جدولاً جديداً: الإشعار المرسل نفسه هو سجل التذكير.
 */
class RemindAbandonedCarts extends Command
{
    public const TYPE = 'cart_reminder';

    /** السلة تُعدّ متروكة بعد هذا العدد من الساعات دون أي تعديل. */
    public const IDLE_HOURS = 24;

    /** السلال الأقدم من هذا لا تُذكَّر (الزبون غالباً لم يعد مهتماً). */
    public const MAX_AGE_DAYS = 7;

    /** أقل مدة بين تذكيرين لنفس الزبون. */
    public const COOLDOWN_DAYS = 3;

    protected $signature = 'cart:remind-abandoned';

    protected $description = 'تذكير الزبائن بالمنتجات المتروكة في السلة';

    public function handle(): int
    {
        $carts = CartItem::query()
            ->selectRaw('user_id, MAX(updated_at) as last_touched_at, COUNT(*) as items_count')
            ->whereHas('product', fn ($q) => $q->publiclyVisible())
            ->whereHas('user', fn ($q) => $q->where('role', 'customer')->where('status', 'active'))
            ->groupBy('user_id')
            ->havingRaw('MAX(updated_at) <= ? AND MAX(updated_at) >= ?', [
                now()->subHours(self::IDLE_HOURS), now()->subDays(self::MAX_AGE_DAYS),
            ])
            ->get();

        $sent = 0;
        foreach ($carts as $cart) {
            $lastTouchedAt = Carbon::parse($cart->last_touched_at);
            if ($this->alreadyReminded((int) $cart->user_id, $lastTouchedAt)) {
                continue;
            }

            $this->remind((int) $cart->user_id, (int) $cart->items_count);
            $sent++;
        }

        $this->info("أُرسل {$sent} تذكير بسلة متروكة.");

        return self::SUCCESS;
    }

    /** ذُكّر بعد آخر تعديل للسلة، أو ذُكّر مؤخراً. */
    private function alreadyReminded(int $userId, Carbon $lastTouchedAt): bool
    {
        $since = $lastTouchedAt->min(now()->subDays(self::COOLDOWN_DAYS));

        return AppNotification::where('user_id', $userId)
            ->where('type', self::TYPE)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    private function remind(int $userId, int $itemsCount): void
    {
        $name = (string) CartItem::where('user_id', $userId)
            ->whereHas('product', fn ($q) => $q->publiclyVisible())
            ->latest('updated_at')
            ->with('product:id,name')
            ->first()?->product?->name;

        $others = $itemsCount - 1;
        $body = match (true) {
            $others <= 0 => "«{$name}» ما زال في سلتك. أكمل طلبك قبل أن تنفد الكمية.",
            $others === 1 => "«{$name}» ومنتج آخر ما زالا في سلتك. أكمل طلبك قبل أن تنفد الكمية.",
            default => "«{$name}» و{$others} منتجات أخرى ما زالت في سلتك. أكمل طلبك قبل أن تنفد الكمية.",
        };

        AppNotification::send($userId, self::TYPE, 'نسيت شيئاً في سلتك؟', $body, route('customer.cart.index'));
    }
}
