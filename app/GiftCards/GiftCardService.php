<?php

namespace App\GiftCards;

use App\GiftCards\Models\GiftBalanceTransaction as Tx;
use App\GiftCards\Models\GiftCard;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\User;
use App\Payments\Contracts\WebhookEvent;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * بطاقات الهدايا ورصيدها. كل تغيير في الرصيد يقفل صف المستخدم داخل معاملة،
 * فلا يُصرف نفس الرصيد مرتين ولا يُضاف نفس الكود مرتين.
 * المبالغ بالوحدة الصغرى ×100.
 */
class GiftCardService
{
    public const MIN_AMOUNT = 1000000;      // 10,000 ل.س

    public const MAX_AMOUNT = 100000000;    // 1,000,000 ل.س

    public const PRESETS = [2500000, 5000000, 10000000, 25000000];

    /** مهلة إدخال الكود بعد الشراء. الرصيد بعد إدخاله لا ينتهي. */
    public const VALID_MONTHS = 12;

    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // بلا 0/O و1/I

    public function __construct(private readonly PaymentService $payments) {}

    public function onlineEnabled(): bool
    {
        return $this->payments->onlineEnabled();
    }

    public function balance(int $userId): int
    {
        return (int) Tx::where('user_id', $userId)->latest('id')->value('balance_after');
    }

    // ───────────────────────── الشراء ─────────────────────────

    /**
     * ينشئ بطاقة بانتظار الدفع ويعيد رابط صفحة البنك.
     *
     * @return array{card: GiftCard, url: string}
     *
     * @throws GiftCardException
     */
    public function purchase(User $user, int $amount, ?string $recipientName = null, ?string $message = null): array
    {
        if ($user->role !== 'customer' || $user->status !== 'active') {
            throw new GiftCardException('شراء بطاقات الهدايا متاح لحسابات العملاء فقط.');
        }
        if (! $this->onlineEnabled()) {
            throw new GiftCardException('الدفع الإلكتروني غير متاح حالياً، لذا لا يمكن شراء بطاقات الهدايا الآن.');
        }
        if ($amount < self::MIN_AMOUNT || $amount > self::MAX_AMOUNT || $amount % 100 !== 0) {
            throw new GiftCardException(__('قيمة البطاقة يجب أن تكون بين :min و:max.', [
                'min' => self::money(self::MIN_AMOUNT), 'max' => self::money(self::MAX_AMOUNT),
            ]));
        }

        // محاولات قديمة لم تُدفع
        GiftCard::where('purchaser_id', $user->id)->where('status', GiftCard::PENDING)
            ->where('created_at', '<', now()->subDay())->update(['status' => GiftCard::FAILED]);

        $gateway = $this->payments->gateway();
        $card = GiftCard::create([
            'purchaser_id' => $user->id,
            'amount' => $amount,
            'reference' => (string) Str::uuid(),
            'gateway' => $gateway->name(),
            'recipient_name' => $recipientName ? Str::limit(trim($recipientName), 60, '') : null,
            'message' => $message ? Str::limit(trim($message), 200, '') : null,
        ]);

        $result = $gateway->initiateGiftCard($card);
        if ($result->type !== 'redirect' || ! $result->url) {
            $card->update(['status' => GiftCard::FAILED]);
            throw new GiftCardException($result->message ?: 'تعذّر فتح صفحة البنك. حاول لاحقاً.');
        }

        return ['card' => $card, 'url' => $result->url];
    }

    /** نتيجة البنك لشراء بطاقة. آمن للتكرار. */
    public function applyPayment(WebhookEvent $e): void
    {
        $activated = DB::transaction(function () use ($e) {
            $card = GiftCard::where('reference', $e->reference)->lockForUpdate()->first();
            if (! $card || $card->status !== GiftCard::PENDING || $e->status === 'pending') {
                return null;
            }

            if ($e->status !== 'paid') {
                $card->update(['status' => GiftCard::FAILED]);

                return null;
            }

            if ($e->amount !== null && abs((float) $e->amount - $card->amount) > 0.001) {
                Log::warning('Gift card amount mismatch', ['reference' => $e->reference, 'expected' => $card->amount, 'incoming' => $e->amount]);
                $card->update(['status' => GiftCard::FAILED]);

                return null;
            }

            $code = $this->newCode();
            $card->update([
                'status' => GiftCard::ACTIVE,
                'gateway_txn_id' => $e->txnId,
                'paid_at' => now(),
                'expires_at' => now()->addMonths(self::VALID_MONTHS),
                'code' => $code,
                'code_hash' => self::hash($code),
            ]);

            return $card;
        });

        if ($activated) {
            AppNotification::send($activated->purchaser_id, 'gift_card_ready', 'بطاقة الهدية جاهزة',
                'تم الدفع. افتح بطاقاتك وأرسل الكود لمن تريد إهداءه.', route('customer.gift-cards.index'));
        }
    }

    // ───────────────────────── الإدخال والصرف ─────────────────────────

    /** @throws GiftCardException */
    public function redeem(User $user, string $code): GiftCard
    {
        if ($user->role !== 'customer' || $user->status !== 'active') {
            throw new GiftCardException('رصيد الهدايا متاح لحسابات العملاء فقط.');
        }
        $hash = self::hash(self::normalize($code));

        $card = DB::transaction(function () use ($user, $hash) {
            $card = GiftCard::where('code_hash', $hash)->lockForUpdate()->first();
            if (! $card) {
                throw new GiftCardException('كود بطاقة الهدية غير صحيح.');
            }
            if ($card->status === GiftCard::REDEEMED) {
                throw new GiftCardException('هذه البطاقة استُخدمت من قبل.');
            }
            if ($card->status !== GiftCard::ACTIVE || $card->expires_at?->isPast()) {
                throw new GiftCardException('انتهت صلاحية هذه البطاقة.');
            }

            $card->update(['status' => GiftCard::REDEEMED, 'redeemed_by' => $user->id, 'redeemed_at' => now()]);
            $this->move($user->id, Tx::REDEEM, $card->amount, giftCardId: $card->id, onceKey: 'redeem:'.$card->id);

            return $card;
        });

        if ($card->purchaser_id !== $user->id) {
            AppNotification::send($card->purchaser_id, 'gift_card_redeemed', 'استُخدمت بطاقة الهدية التي أرسلتها',
                'أضافها '.$user->first_name.' إلى رصيده.', route('customer.gift-cards.index'));
        }

        return $card;
    }

    /**
     * داخل معاملة إنشاء الطلب، بعد الكوبون والنقاط: يغطي ما يمكن من المبلغ المتبقي (مع الشحن).
     * يعيد المبلغ المستخدم.
     */
    public function spendForOrder(User $user, Order $order): int
    {
        $total = (int) $order->total;
        if ($total <= 0) {
            return 0;
        }

        $used = min($this->lockedBalance($user->id), $total);
        if ($used <= 0) {
            return 0;
        }

        $this->move($user->id, Tx::SPEND, -$used, orderId: $order->id, onceKey: 'spend:'.$order->id);
        $order->update(['gift_balance_used' => $used, 'total' => $total - $used]);

        return $used;
    }

    /** ما دُفع من الطلب برصيد الهدايا ولم يُعد بعد (بإلغاء أو بمرتجعات). */
    public function refundableForOrder(Order $order): int
    {
        $returned = (int) Tx::where('order_id', $order->id)->where('type', Tx::REFUND)->sum('amount');

        return max(0, (int) $order->gift_balance_used - $returned);
    }

    /** إلغاء الطلب يعيد ما تبقّى مما دُفع منه برصيد الهدايا. مرة واحدة. */
    public function refundForOrder(Order $order): void
    {
        if ((int) $order->gift_balance_used <= 0 || Tx::where('once_key', 'refund:'.$order->id)->exists()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $this->lockedBalance($order->user_id);
            $remaining = $this->refundableForOrder($order);
            if ($remaining > 0) {
                $this->move($order->user_id, Tx::REFUND, $remaining, orderId: $order->id, onceKey: 'refund:'.$order->id);
            }
        });
    }

    /**
     * استرداد مرتجع إلى رصيد الهدايا (يختاره المدير)، بحد ما دُفع من الطلب بالرصيد.
     * يُستدعى داخل معاملة تسجيل الاسترداد.
     *
     * @throws GiftCardException
     */
    public function creditForReturn(Order $order, int $amount, int $refundId): void
    {
        $this->lockedBalance($order->user_id);
        $refundable = $this->refundableForOrder($order);
        if ($amount > $refundable) {
            throw new GiftCardException(__('المبلغ أكبر مما دُفع برصيد الهدايا في هذا الطلب (:amount). استرد الباقي بطريقة أخرى.', [
                'amount' => self::money($refundable),
            ]));
        }

        $this->move($order->user_id, Tx::REFUND, $amount, orderId: $order->id, onceKey: 'return_refund:'.$refundId);
    }

    // ───────────────────────── أدوات ─────────────────────────

    public static function normalize(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return strlen($code) === 12 ? 'TS'.$code : $code;
    }

    public static function format(string $code): string
    {
        return 'TS-'.implode('-', str_split(substr($code, 2), 4));
    }

    public static function money(int $minor): string
    {
        return number_format($minor / 100).' ل.س';
    }

    private static function hash(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    private function newCode(): string
    {
        do {
            $code = 'TS';
            for ($i = 0; $i < 12; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (GiftCard::where('code_hash', self::hash($code))->exists());

        return $code;
    }

    /** يقفل صف المستخدم (يجب أن يكون داخل معاملة) ويعيد رصيده. */
    private function lockedBalance(int $userId): int
    {
        User::whereKey($userId)->lockForUpdate()->value('id');

        return $this->balance($userId);
    }

    private function move(int $userId, string $type, int $amount, ?int $giftCardId = null, ?int $orderId = null, ?string $onceKey = null): Tx
    {
        $balance = $this->lockedBalance($userId) + $amount;
        if ($balance < 0) {
            throw new GiftCardException('رصيد الهدايا غير كافٍ.');
        }

        return Tx::create([
            'user_id' => $userId, 'type' => $type, 'amount' => $amount, 'balance_after' => $balance,
            'gift_card_id' => $giftCardId, 'order_id' => $orderId, 'once_key' => $onceKey,
        ]);
    }
}
