<?php

namespace App\Services;

use App\GiftCards\GiftCardService;
use App\GiftCards\Models\GiftCard;
use App\Models\AppNotification;
use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Push\SellerOrderNotifier;
use App\Payments\BankGateway;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\WebhookEvent;
use App\Payments\ManualGateway;
use App\Payments\SandboxGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * نقطة الدخول الوحيدة لدفع العملاء وبطاقاتهم المحفوظة (الموقع والتطبيق ولوحة الإدارة).
 * البوابة تُختار من PAYMENT_DRIVER: manual | sandbox | bank.
 */
class PaymentService
{
    public function gateway(?string $driver = null): PaymentGateway
    {
        return match ($driver ?? config('payment.driver')) {
            'bank' => new BankGateway,
            'sandbox' => SandboxGateway::enabled() ? new SandboxGateway : new ManualGateway,
            default => new ManualGateway,
        };
    }

    public function onlineEnabled(): bool
    {
        return $this->gateway()->name() !== 'manual';
    }

    public function savedCardsEnabled(): bool
    {
        return $this->gateway()->supportsSavedCards();
    }

    /**
     * يبدأ دفع طلب. المبلغ يُحسب في السيرفر من الطلب فقط.
     * مع $card: دفع ببطاقة محفوظة (قد يكتمل فوراً أو يحتاج تحقق 3-D Secure).
     *
     * @return array{reference:string,type:string,url:?string,message:?string,paid:bool}
     */
    public function start(Order $order, ?CustomerCard $card = null): array
    {
        $gw = $this->gateway();

        // كل محاولة دفع تؤجّل الإلغاء التلقائي: بعد المهلة لا محاولات جديدة، فلا يبقى المخزون محجوزاً
        abort_if($order->onlinePaymentWindowClosed(), 422, __('انتهت مهلة دفع هذا الطلب. سيُلغى تلقائياً، ويمكنك الطلب من جديد.'));
        // طلب الدفع عند الاستلام: مبلغه مسجّل على الطرد ليحصّله المندوب، فالدفع الإلكتروني فوقه يعني دفعه مرتين
        abort_if(Payment::where('order_id', $order->id)->oldest('id')->value('method') === 'cash_on_delivery', 422,
            __('هذا الطلب بالدفع عند الاستلام، يُدفع عند التسليم.'));

        if ($card) {
            abort_unless($card->user_id === $order->user_id, 403);
            abort_unless($card->isUsable(), 422, 'هذه البطاقة غير صالحة للدفع (منتهية أو غير مفعّلة).');
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'customer_card_id' => $card?->id,
            'gateway' => $gw->name(),
            'reference' => (string) Str::uuid(),
            'amount' => $this->orderAmount($order),
            'currency' => config('payment.currency', 'SYP'),
            'status' => 'pending',
            // أعمدة قديمة تبقى متوافقة مع شاشات الطلبات الحالية
            'method' => $gw->name() === 'manual' ? 'cash_on_delivery' : 'card',
        ]);

        $result = $card
            ? $gw->chargeSavedCard($payment, $order, $card)
            : $gw->initiate($payment, $order);

        if ($result->type === 'paid') {
            $this->apply($payment->reference, new WebhookEvent(
                $payment->reference, 'paid', $result->txnId, (float) $payment->amount, $result->raw
            ));
        } elseif ($result->type === 'failed') {
            $this->apply($payment->reference, new WebhookEvent($payment->reference, 'failed', null, null, $result->raw));
        }

        return [
            'reference' => $payment->reference,
            'type' => $result->type,
            'url' => $result->url,
            'message' => $result->message,
            'paid' => $result->type === 'paid',
        ];
    }

    /** يوزّع إشعار البنك حسب نوعه (دفع طلب أو حفظ بطاقة). */
    public function handle(WebhookEvent $e): void
    {
        if ($e->kind === 'card') {
            $this->applyCard($e);
        } elseif (GiftCard::where('reference', $e->reference)->exists()) {
            app(GiftCardService::class)->applyPayment($e);
        } else {
            $this->apply($e->reference, $e);
        }
    }

    /**
     * يُستدعى من الـ webhook أو من لوحة الإدارة (تأكيد يدوي).
     * آمن للتكرار: إشعار مكرر لا يغيّر شيئاً.
     */
    public function apply(string $reference, WebhookEvent $e): void
    {
        $notifyPaid = null;
        $duplicate = null;
        $paidAfterCancel = null;
        $releasedToSellers = null;

        DB::transaction(function () use ($reference, $e, &$notifyPaid, &$duplicate, &$paidAfterCancel, &$releasedToSellers) {
            $p = Payment::where('reference', $reference)->lockForUpdate()->first();

            if (! $p || in_array($p->status, ['paid', 'duplicate', 'refunded'], true) || $e->status === 'pending') {
                return;
            }

            // قفل الطلب يمنع دفعتين متزامنتين من تسجيل الطلب كمدفوع مرتين
            $order = $p->order_id ? Order::whereKey($p->order_id)->lockForUpdate()->first() : null;

            if ($e->status === 'paid' && $order?->status === 'cancelled') {
                // البنك أكّد الدفع بعد إلغاء الطلب (انتهت المهلة أو ألغاه العميل): لا يُعاد فتح الطلب،
                // والمبلغ يُسترجع للعميل. نسجّلها "مكررة" فلا يغيّرها إشعار لاحق.
                $p->update([
                    'status' => 'duplicate',
                    'gateway_txn_id' => $e->txnId,
                    'transaction_ref' => $e->txnId ?: $p->transaction_ref,
                    'paid_at' => now(),
                    'payload' => $e->raw,
                ]);
                $paidAfterCancel = $p;

                return;
            }

            if ($e->status === 'paid' && $order
                && Payment::where('order_id', $order->id)->where('id', '!=', $p->id)->where('status', 'paid')->exists()) {
                // العميل دفع الطلب مرتين (مثلاً أكمل صفحتي بنك). لا نخفي ذلك: نسجّلها "مكررة" لتُسترجع.
                $p->update([
                    'status' => 'duplicate',
                    'gateway_txn_id' => $e->txnId,
                    'transaction_ref' => $e->txnId ?: $p->transaction_ref,
                    'paid_at' => now(),
                    'payload' => $e->raw,
                ]);
                $duplicate = $p;

                return;
            }

            if ($e->status === 'paid') {
                // تحقق من تطابق المبلغ مع ما نتوقعه
                $expected = (float) $this->orderAmount($order);
                $incoming = $e->amount !== null ? (float) $e->amount : $expected;
                if (abs($incoming - $expected) > 0.001) {
                    Log::warning('Payment amount mismatch', ['reference' => $reference, 'expected' => $expected, 'incoming' => $incoming]);
                    $p->update([
                        'status' => 'failed',
                        'payload' => $e->raw,
                        'transaction_ref' => $e->txnId ?: $p->transaction_ref,
                        'gateway_txn_id' => $e->txnId ?: $p->gateway_txn_id,
                    ]);

                    return;
                }

                // طلب البطاقة يصل للبائع الآن فقط (قبل الدفع كان مخفياً عنه)
                $wasAwaitingPayment = $order?->isAwaitingOnlinePayment() ?? false;

                $p->update([
                    'status' => 'paid',
                    'gateway_txn_id' => $e->txnId,
                    'transaction_ref' => $e->txnId ?: $p->transaction_ref,
                    'paid_at' => now(),
                    'payload' => $e->raw,
                ]);

                // نجعل هذه الدفعة هي دفعة الطلب الظاهرة في الشاشات (order->payment)
                Payment::where('order_id', $p->order_id)->where('id', '!=', $p->id)
                    ->where('status', 'pending')->update(['status' => 'cancelled']);

                if ($order) {
                    $order->update(['payment_status' => 'paid']);
                    $notifyPaid = $order;
                    if ($wasAwaitingPayment) {
                        $releasedToSellers = $order;
                    }
                }

                return;
            }

            $p->update([
                'status' => $e->status,
                'payload' => $e->raw,
                'transaction_ref' => $e->txnId ?: $p->transaction_ref,
                'gateway_txn_id' => $e->txnId ?: $p->gateway_txn_id,
            ]);

            if ($order && in_array($e->status, ['failed', 'cancelled'], true) && $order->payment_status !== 'paid') {
                $order->update(['payment_status' => $e->status]);
            }
        });

        if ($notifyPaid) {
            AppNotification::send(
                $notifyPaid->user_id,
                'payment_confirmed',
                "تم استلام دفعتك للطلب #{$notifyPaid->order_number}",
                null,
                route('customer.orders.show', $notifyPaid)
            );
        }

        if ($releasedToSellers) {
            app(SellerOrderNotifier::class)->notify($releasedToSellers);
        }

        if ($paidAfterCancel) {
            Log::critical('Payment confirmed for a cancelled order — refund required', [
                'payment_id' => $paidAfterCancel->id, 'order_id' => $paidAfterCancel->order_id, 'txn' => $paidAfterCancel->gateway_txn_id,
            ]);
            User::where('role', 'admin')->pluck('id')->each(fn ($adminId) => AppNotification::send(
                $adminId,
                'payment_duplicate',
                'دفعة لطلب ملغى تحتاج استرجاعاً',
                "الطلب #{$paidAfterCancel->order?->order_number} أُلغي قبل وصول تأكيد البنك — رقم العملية: {$paidAfterCancel->gateway_txn_id}",
                route('admin.orders.show', $paidAfterCancel->order_id)
            ));
        }

        if ($duplicate) {
            Log::critical('Duplicate payment for an already-paid order — refund required', [
                'payment_id' => $duplicate->id, 'order_id' => $duplicate->order_id, 'txn' => $duplicate->gateway_txn_id,
            ]);
            User::where('role', 'admin')->pluck('id')->each(fn ($adminId) => AppNotification::send(
                $adminId,
                'payment_duplicate',
                'دفعة مكررة تحتاج استرجاعاً',
                "الطلب #{$duplicate->order?->order_number} دُفع مرتين — رقم العملية الثانية: {$duplicate->gateway_txn_id}",
                route('admin.orders.show', $duplicate->order_id)
            ));
        }
    }

    // ------------------------------------------------------------------ البطاقات المحفوظة

    /** ينشئ سجل بطاقة "قيد الحفظ" ويعيد رابط صفحة البنك لإدخالها. */
    public function startCardSave(User $user): array
    {
        $gw = $this->gateway();
        abort_unless($gw->supportsSavedCards(), 422, 'حفظ البطاقات غير متاح حتى يتم ربط بوابة البنك.');

        // تنظيف محاولات قديمة لم تكتمل
        $user->customerCards()->where('status', 'pending')->where('created_at', '<', now()->subDay())->delete();

        $card = $user->customerCards()->create([
            'gateway' => $gw->name(),
            'reference' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        $result = $gw->startCardSave($card);
        if ($result->type !== 'redirect' || ! $result->url) {
            $card->update(['status' => 'failed']);
            abort(422, $result->message ?: 'تعذّر فتح صفحة البنك.');
        }

        return ['reference' => $card->reference, 'url' => $result->url];
    }

    public function applyCard(WebhookEvent $e): void
    {
        DB::transaction(function () use ($e) {
            $card = CustomerCard::where('reference', $e->reference)->lockForUpdate()->first();
            if (! $card || $card->status === 'active') {
                return;
            }

            if ($e->status !== 'paid' || ! $e->cardToken) {
                $card->update(['status' => 'failed']);

                return;
            }

            $isFirst = ! CustomerCard::where('user_id', $card->user_id)->active()->exists();

            $card->update([
                'status' => 'active',
                'token' => $e->cardToken,
                'brand' => $e->cardBrand,
                'last4' => $e->cardLast4,
                'exp_month' => $e->cardExpMonth,
                'exp_year' => $e->cardExpYear,
                'holder_name' => $e->cardHolder,
                'is_default' => $isFirst,
            ]);
        });
    }

    public function deleteCard(CustomerCard $card): void
    {
        $this->gateway($card->gateway)->deleteSavedCard($card);
        $wasDefault = $card->is_default;
        $userId = $card->user_id;
        $card->delete();

        if ($wasDefault) {
            CustomerCard::where('user_id', $userId)->active()->latest()->first()?->update(['is_default' => true]);
        }
    }

    public function setDefaultCard(CustomerCard $card): void
    {
        abort_unless($card->status === 'active', 422, 'البطاقة غير مفعّلة.');

        DB::transaction(function () use ($card) {
            CustomerCard::where('user_id', $card->user_id)->update(['is_default' => false]);
            $card->update(['is_default' => true]);
        });
    }

    private function orderAmount(?Order $order): int|float
    {
        // المبالغ في المشروع محفوظة بالوحدة الصغرى (orders.total)
        return $order ? $order->total : 0;
    }
}
