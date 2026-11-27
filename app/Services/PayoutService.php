<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use App\Payments\Payouts\BankPayoutGateway;
use App\Payments\Payouts\ManualPayoutGateway;
use App\Payments\Payouts\PayoutGateway;
use App\Payments\Payouts\PayoutResult;
use App\Payments\Payouts\SandboxPayoutGateway;
use App\Payments\SandboxGateway;
use App\Returns\Models\ReturnItem;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * أرباح البائعين: طرق الاستلام + طلبات السحب + تنفيذ الحوالات (يدوياً أو عبر API البنك).
 * يستخدمه الموقع والتطبيق ولوحة الإدارة، فالقواعد واحدة في كل مكان.
 *
 * دورة الطلب: pending → approved (خُصم من المحفظة) → paid | processing → paid | failed (يُعاد المبلغ)
 *                     ↘ rejected
 */
class PayoutService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function gateway(): PayoutGateway
    {
        return match (config('payment.payout.driver')) {
            'bank' => new BankPayoutGateway,
            'sandbox' => SandboxGateway::enabled() ? new SandboxPayoutGateway : new ManualPayoutGateway,
            default => new ManualPayoutGateway,
        };
    }

    // ------------------------------------------------------------------ طرق الاستلام

    public static function methodRules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(PaymentMethod::TYPES))],
            'provider' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:64'],
            'account_holder_name' => ['required', 'string', 'min:3', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function addMethod(SellerProfile $vendor, array $data): PaymentMethod
    {
        $number = $this->normalizeAccount($data['type'], $data['account_number']);

        return DB::transaction(function () use ($vendor, $data, $number) {
            $makeDefault = ($data['is_default'] ?? false) || ! $vendor->paymentMethods()->exists();
            if ($makeDefault) {
                $vendor->paymentMethods()->update(['is_default' => false]);
            }

            return $vendor->paymentMethods()->create([
                'type' => $data['type'],
                'provider' => trim($data['provider']),
                'account_number' => $number,
                'account_holder_name' => trim($data['account_holder_name']),
                'is_default' => $makeDefault,
                'is_verified' => false,
            ]);
        });
    }

    public function removeMethod(PaymentMethod $method): void
    {
        $vendor = $method->vendor;
        $wasDefault = $method->is_default;
        $method->delete();

        if ($wasDefault) {
            $vendor->paymentMethods()->latest()->first()?->update(['is_default' => true]);
        }
    }

    public function setDefaultMethod(PaymentMethod $method): void
    {
        DB::transaction(function () use ($method) {
            PaymentMethod::where('vendor_id', $method->vendor_id)->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });
    }

    public function verifyMethod(PaymentMethod $method, User $admin, bool $verified = true): void
    {
        $method->update([
            'is_verified' => $verified,
            'verified_at' => $verified ? now() : null,
            'verified_by' => $verified ? $admin->id : null,
        ]);
    }

    /** تحقق من صحة الرقم حسب النوع وتوحيد كتابته. */
    public function normalizeAccount(string $type, string $raw): string
    {
        $value = strtoupper(preg_replace('/[\s-]+/', '', $raw));

        $error = match ($type) {
            'bank_card' => (! preg_match('/^\d{16,19}$/', $value) || ! self::luhn($value))
                ? 'رقم البطاقة غير صحيح (16 إلى 19 رقماً).' : null,
            'bank_account' => preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $value)
                ? (self::ibanValid($value) ? null : 'رقم IBAN غير صحيح، تأكد من كتابته كاملاً.')
                : (preg_match('/^\d{6,30}$/', $value) ? null : 'أدخل رقم IBAN (مثل TR.. أو SY..) أو رقم حساب صحيح.'),
            'electronic_wallet' => preg_match('/^\+?\d{6,20}$/', $value) ? null : 'رقم المحفظة غير صحيح.',
            default => 'نوع غير معروف.',
        };

        if ($error) {
            throw ValidationException::withMessages(['account_number' => $error]);
        }

        return $value;
    }

    // ------------------------------------------------------------------ طلبات السحب

    /**
     * المبلغ المتاح للسحب = الرصيد − طلبات السحب المعلّقة − أرباح ما زالت في مدة الإرجاع (بالوحدة الصغرى).
     * الأرباح تدخل الرصيد عند التسليم، لكنها لا تُسحب حتى تنتهي مدة الإرجاع: إرجاعٌ بعد السحب كان يصير ديناً على البائع.
     */
    public function available(SellerProfile $vendor): int
    {
        return max(0, $this->balance($vendor) - $this->pendingWithdrawals($vendor) - $this->heldLines($vendor)->sum('remaining'));
    }

    /**
     * أرباح لا تُسحب بعد: بنود لم تنتهِ مدة إرجاعها، وبنود عليها طلب إرجاع لم يُحسم ولو انتهت المدة
     * (بعد طرح ما خُصم منها). لا يتجاوز ما في الرصيد بعد طلبات السحب المعلّقة، فلا يُعرض محجوز أكبر من الرصيد.
     */
    public function heldForReturns(SellerProfile $vendor): int
    {
        $room = max(0, $this->balance($vendor) - $this->pendingWithdrawals($vendor));

        return min((int) $this->heldLines($vendor)->sum('remaining'), $room);
    }

    /** متى يصير أقرب مبلغ محجوز متاحاً للسحب (null إن لم يكن محجوز يُفرج عنه بتاريخ؛ المرتجع المفتوح يُفرج عنه عند حسمه). */
    public function nextRelease(SellerProfile $vendor): ?Carbon
    {
        return $this->heldLines($vendor)->pluck('releases_at')->filter()->min();
    }

    /** الرصيد من القاعدة مباشرة لا من نسخة محمّلة سابقاً (قد تكون قبل إيداع أرباح جديدة). */
    private function balance(SellerProfile $vendor): int
    {
        return (int) Wallet::whereKey($vendor->walletOrCreate()->id)->value('balance');
    }

    private function pendingWithdrawals(SellerProfile $vendor): int
    {
        return (int) $vendor->withdrawalRequests()->where('status', 'pending')->sum('amount');
    }

    /**
     * بنود محجوزة مع الباقي من أرباح كلٍّ منها بعد ما خُصم منه، وتاريخ الإفراج (null للمرتجع المفتوح).
     * طرود التوصيل الخاص بالدفع عند الاستلام لا تُحجز: البائع قبض ثمنها بيده وخُصم من محفظته.
     *
     * @return Collection<int, array{remaining: int, releases_at: ?Carbon}>
     */
    private function heldLines(SellerProfile $vendor): Collection
    {
        $windowDays = app(ReturnSettings::class)->windowDays();
        $openReturnItemIds = ReturnItem::query()
            ->whereHas('returnRequest', fn (Builder $request) => $request
                ->where('seller_profile_id', $vendor->id)
                ->where(fn (Builder $open) => $open
                    ->whereIn('status', ReturnRequest::OPEN)
                    ->orWhere(fn (Builder $disputable) => $disputable->where('status', 'rejected')->where('dispute_by', '>', now()))))
            ->pluck('order_item_id')->map(fn ($id) => (int) $id)->all();

        $items = OrderItem::query()
            ->where('vendor_id', $vendor->id)
            ->where('vendor_earning', '>', 0)
            ->whereNotNull('commission_settled_at')
            ->whereNotIn('order_id', Shipment::query()->select('order_id')
                ->where('seller_profile_id', $vendor->id)
                ->where('delivery_mode', '!=', 'platform')
                ->where('cod_amount', '>', 0))
            ->where(fn (Builder $held) => $held
                ->where('commission_settled_at', '>', now()->subDays(max(0, $windowDays)))
                ->orWhereIn('id', $openReturnItemIds ?: [0]))
            ->get(['id', 'vendor_earning', 'commission_settled_at']);
        if ($items->isEmpty()) {
            return collect();
        }

        $reversed = WalletTransaction::query()
            ->where('wallet_id', $vendor->walletOrCreate()->id)
            ->where('type', 'debit')
            ->whereIn('order_item_id', $items->pluck('id'))
            ->groupBy('order_item_id')
            ->selectRaw('order_item_id, SUM(amount) as total')
            ->pluck('total', 'order_item_id');

        return $items
            ->map(fn (OrderItem $item) => [
                'remaining' => max(0, (int) $item->vendor_earning - (int) ($reversed[$item->id] ?? 0)),
                'releases_at' => in_array($item->id, $openReturnItemIds, true)
                    ? null
                    : Carbon::parse($item->commission_settled_at)->addDays($windowDays),
            ])
            ->filter(fn (array $line) => $line['remaining'] > 0)
            ->values();
    }

    /** @param  float  $amount  بالليرة (الوحدة الكبرى) كما يدخلها البائع */
    public function request(SellerProfile $vendor, float $amount, ?string $note = null): WithdrawalRequest
    {
        $method = $vendor->paymentMethods()->where('is_default', true)->first();
        if (! $method) {
            throw ValidationException::withMessages(['amount' => 'أضف طريقة استلام واجعلها الافتراضية قبل طلب السحب.']);
        }
        if (config('payment.payout.require_verified_method') && ! $method->is_verified) {
            throw ValidationException::withMessages(['amount' => 'طريقة الاستلام بانتظار تحقق الإدارة.']);
        }

        $min = (int) config('payment.payout.min_amount', 0);
        if ($amount < $min) {
            throw ValidationException::withMessages(['amount' => __('أقل مبلغ للسحب هو :amount ل.س.', ['amount' => number_format($min)])]);
        }

        $minor = (int) round($amount * 100);
        $wallet = $vendor->walletOrCreate();

        return DB::transaction(function () use ($vendor, $wallet, $method, $minor, $note) {
            // قفل المحفظة يمنع طلبين متزامنين من تجاوز الرصيد
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
            $pending = (int) $vendor->withdrawalRequests()->where('status', 'pending')->sum('amount');

            if ($minor > (int) $locked->balance - $pending - (int) $this->heldLines($vendor)->sum('remaining')) {
                throw ValidationException::withMessages(['amount' => __('المبلغ أكبر من رصيدك المتاح. أرباح الطلبات التي ما زالت في مدة الإرجاع لا تُسحب حتى تنتهي المدة.')]);
            }

            return WithdrawalRequest::create([
                'vendor_id' => $vendor->id,
                'payment_method_id' => $method->id,
                'amount' => $minor,
                // نسخة من بيانات التحويل وقت الطلب (لا تتأثر لو عدّل البائع طريقته لاحقاً)
                'payout_method_type' => $method->type,
                'payout_provider' => $method->provider,
                'payout_account_number' => $method->account_number,
                'payout_account_holder_name' => $method->account_holder_name,
                'note' => $note,
                'status' => 'pending',
            ]);
        });
    }

    /** موافقة الإدارة: خصم من المحفظة + قيد محاسبي، ثم إرسال الحوالة إن كانت البوابة تلقائية. */
    public function approve(WithdrawalRequest $w, User $admin): WithdrawalRequest
    {
        $gateway = $this->gateway();

        DB::transaction(function () use ($w, $admin, $gateway) {
            $w = WithdrawalRequest::whereKey($w->id)->lockForUpdate()->firstOrFail();
            abort_unless($w->status === 'pending', 409, 'تم التعامل مع هذا الطلب مسبقاً.');
            abort_unless(
                $w->payout_account_number && $w->payout_account_holder_name,
                409,
                'لا توجد بيانات تحويل محفوظة لهذا الطلب. اطلب من البائع تقديم طلب جديد بعد إعداد طريقة الاستلام.'
            );

            $wallet = Wallet::whereKey($w->vendor->walletOrCreate()->id)->lockForUpdate()->first();
            abort_if($wallet->balance < $w->amount, 422, 'رصيد البائع لم يعد كافياً لهذا الطلب.');

            $wallet->applyTransaction('debit', $w->amount, 'withdrawal', [
                'withdrawal_request_id' => $w->id,
                'note' => 'سحب أرباح معتمد',
            ]);

            $this->ledger->payout(
                $w->vendor->user_id,
                round($w->amount / 100, 2),
                "سحب أرباح - طلب سحب #{$w->id}",
                $w->id,
                $admin->id
            );

            $w->update([
                'status' => 'approved',
                'payout_gateway' => $gateway->name(),
                'processed_at' => now(),
                'processed_by' => $admin->id,
            ]);
        });

        $w->refresh();
        $this->notify($w, 'withdrawal_approved', 'تمت الموافقة على طلب السحب', $w->formatted_amount.' — جاري التحويل');

        if ($gateway->isAutomatic()) {
            $this->send($w, $gateway, $admin);
        }

        return $w->refresh();
    }

    /** إرسال الحوالة عبر API البنك (أو إعادة المحاولة لطلب approved). */
    public function send(WithdrawalRequest $w, ?PayoutGateway $gateway = null, ?User $admin = null): WithdrawalRequest
    {
        $gateway ??= $this->gateway();
        abort_unless($w->status === 'approved', 409, 'هذا الطلب ليس بانتظار التحويل.');
        abort_unless($gateway->isAutomatic(), 422, 'التحويل التلقائي غير مفعّل؛ حوّل يدوياً ثم سجّل رقم الحوالة.');

        try {
            $result = $gateway->send($w);
        } catch (\Throwable $e) {
            // خطأ اتصال: يبقى الطلب approved ويمكن إعادة المحاولة؛ لا نعيد المبلغ لأننا لا نعرف إن نُفّذت الحوالة
            Log::error('Payout send failed', ['withdrawal_id' => $w->id, 'error' => $e->getMessage()]);
            $w->update(['failure_reason' => 'تعذّر الاتصال بالبنك: '.mb_substr($e->getMessage(), 0, 200)]);

            return $w;
        }

        return $this->applyResult($w, $result, $admin);
    }

    public function applyResult(WithdrawalRequest $w, PayoutResult $r, ?User $admin = null): WithdrawalRequest
    {
        return match ($r->status) {
            'paid' => $this->markPaid($w, $r->reference ?: 'BANK', null, $admin, $r->raw),
            'processing' => tap($w)->update([
                'status' => 'processing',
                'transfer_reference' => $r->reference,
                'gateway_payload' => $r->raw,
                'failure_reason' => null,
            ]),
            'failed' => $this->markFailed($w, $r->message ?: 'رفض البنك الحوالة.', $admin, $r->raw),
            default => $w,
        };
    }

    /** تأكيد وصول المال: رقم الحوالة إلزامي، والإيصال اختياري. */
    public function markPaid(WithdrawalRequest $w, string $reference, ?UploadedFile $receipt = null, ?User $admin = null, array $raw = []): WithdrawalRequest
    {
        $receiptPath = $receipt?->store('withdrawal-receipts', 'local');

        $changed = DB::transaction(function () use ($w, $reference, $receiptPath, $admin, $raw) {
            $locked = WithdrawalRequest::whereKey($w->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'paid') {
                return false; // تكرار آمن (webhook مكرر)
            }
            abort_unless(in_array($locked->status, ['approved', 'processing'], true), 409, 'لا يمكن تأكيد تحويل طلب بهذه الحالة.');

            $locked->update([
                'status' => 'paid',
                'transfer_reference' => trim($reference),
                'receipt_path' => $receiptPath ?? $locked->receipt_path,
                'gateway_payload' => $raw ?: $locked->gateway_payload,
                'failure_reason' => null,
                'paid_at' => now(),
                'processed_by' => $admin?->id ?? $locked->processed_by,
            ]);

            return true;
        });

        $w->refresh();
        if ($changed) {
            $this->notify($w, 'withdrawal_paid', 'تم تحويل أرباحك', $w->formatted_amount.' — رقم الحوالة: '.$w->transfer_reference);
        }

        return $w;
    }

    /** فشل التحويل: نعيد المبلغ لمحفظة البائع ونعكس القيد المحاسبي. */
    public function markFailed(WithdrawalRequest $w, string $reason, ?User $admin = null, array $raw = []): WithdrawalRequest
    {
        $changed = DB::transaction(function () use ($w, $reason, $admin, $raw) {
            $locked = WithdrawalRequest::whereKey($w->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'failed') {
                return false;
            }
            abort_unless(in_array($locked->status, ['approved', 'processing'], true), 409, 'لا يمكن تعليم هذا الطلب كفاشل.');

            $locked->vendor->walletOrCreate()->applyTransaction('credit', $locked->amount, 'withdrawal_reversal', [
                'withdrawal_request_id' => $locked->id,
                'note' => 'إرجاع حوالة فاشلة',
            ]);

            $this->ledger->payoutReversal(
                $locked->vendor->user_id,
                round($locked->amount / 100, 2),
                "إرجاع حوالة فاشلة - طلب سحب #{$locked->id}",
                $locked->id,
                $admin?->id
            );

            $locked->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($reason, 0, 255),
                'gateway_payload' => $raw ?: $locked->gateway_payload,
                'processed_by' => $admin?->id ?? $locked->processed_by,
            ]);

            return true;
        });

        $w->refresh();
        if ($changed) {
            $this->notify($w, 'withdrawal_failed', 'فشل تحويل الأرباح وأُعيد المبلغ لمحفظتك', $reason);
        }

        return $w;
    }

    public function reject(WithdrawalRequest $w, string $note, User $admin): WithdrawalRequest
    {
        DB::transaction(function () use ($w, $note, $admin) {
            $locked = WithdrawalRequest::whereKey($w->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'تم التعامل مع هذا الطلب مسبقاً.');

            $locked->update([
                'status' => 'rejected',
                'admin_note' => $note,
                'processed_at' => now(),
                'processed_by' => $admin->id,
            ]);
        });

        $w->refresh();
        $this->notify($w, 'withdrawal_rejected', 'تم رفض طلب السحب', $note);

        return $w;
    }

    // ------------------------------------------------------------------ أدوات

    private function notify(WithdrawalRequest $w, string $type, string $title, ?string $body): void
    {
        AppNotification::send($w->vendor->user_id, $type, $title, $body, route('seller.wallet.index'));
    }

    public static function luhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    public static function ibanValid(string $iban): bool
    {
        $moved = substr($iban, 4).substr($iban, 0, 4);
        $numeric = preg_replace_callback('/[A-Z]/', fn ($m) => (string) (ord($m[0]) - 55), $moved);

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder === 1;
    }
}
