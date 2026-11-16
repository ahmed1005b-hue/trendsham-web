<?php

namespace App\Payments\Payouts;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * تحويل تلقائي عبر API البنك (Payouts/Transfers). كل التفاصيل في config/payment.php → payout.bank.
 * TODO(BANK): طابق جسم الطلب مع وثائق البنك عند استلامها.
 */
class BankPayoutGateway implements PayoutGateway
{
    public function name(): string
    {
        return 'bank';
    }

    public function isAutomatic(): bool
    {
        return true;
    }

    public function send(WithdrawalRequest $withdrawal): PayoutResult
    {
        $cfg = config('payment.payout.bank');
        if (! $cfg['url'] || ! $cfg['key']) {
            throw new \RuntimeException('API التحويلات غير مضبوط: املأ PAYOUT_GATEWAY_URL و PAYOUT_GATEWAY_KEY في .env');
        }

        $amount = config('payment.bank.amount_in_minor_units') ? (int) $withdrawal->amount : round($withdrawal->amount / 100, 2);

        $res = Http::baseUrl(rtrim($cfg['url'], '/'))
            ->withToken($cfg['key'])
            ->acceptJson()
            ->asJson()
            ->timeout($cfg['timeout'] ?? 30)
            ->withHeaders(['Idempotency-Key' => 'withdrawal-'.$withdrawal->id]) // يمنع التحويل المزدوج
            ->post('/'.ltrim($cfg['path'], '/'), [
                'reference' => 'withdrawal-'.$withdrawal->id,
                'amount' => $amount,
                'currency' => config('payment.currency', 'SYP'),
                'destination' => [
                    'type' => $withdrawal->payout_method_type,      // bank_account | bank_card | electronic_wallet
                    'provider' => $withdrawal->payout_provider,
                    'account' => $withdrawal->payout_account_number,
                    'holder_name' => $withdrawal->payout_account_holder_name,
                ],
                'description' => 'أرباح متجر - تريندشام',
                'notify_url' => route('payouts.webhook', 'bank'),
            ]);

        $data = (array) $res->json();

        // 5xx / مهلة / رد غير متوقع: لا نعرف إن نُفّذت الحوالة، فلا نعيد المبلغ للبائع.
        // نرمي استثناءً فيبقى الطلب "approved" مع سبب، ويعيد المدير المحاولة (Idempotency-Key يمنع التكرار).
        if ($res->serverError() || $res->status() === 408 || $res->status() === 429) {
            throw new \RuntimeException('البنك لم يؤكد الحوالة (HTTP '.$res->status().')، تحقق من حالتها لدى البنك قبل إعادة المحاولة.');
        }

        // 4xx = رفض صريح من البنك (بيانات حساب خاطئة، رصيد...) فنعيد المبلغ للبائع
        if (! $res->successful()) {
            return new PayoutResult('failed', null, Arr::get($data, $cfg['reason_field'], Arr::get($data, 'message', 'رفض البنك الحوالة.')), $data);
        }

        $status = $this->mapStatus(Arr::get($data, $cfg['status_field']));

        return new PayoutResult($status, Arr::get($data, $cfg['txn_field']), Arr::get($data, $cfg['reason_field']), $data);
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('payment.payout.bank.webhook_secret');
        $sig = preg_replace('/^sha256=/i', '', (string) $request->header(config('payment.payout.bank.signature_header'), ''));

        return $secret !== '' && $sig !== ''
            && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $sig);
    }

    public function parseWebhook(Request $request): array
    {
        $cfg = config('payment.payout.bank');
        $d = $request->json()->all();
        $ref = (string) Arr::get($d, $cfg['reference_field'], '');

        return [
            'id' => (int) str_replace('withdrawal-', '', $ref),
            'status' => $this->mapStatus(Arr::get($d, $cfg['status_field'])),
            'reference' => Arr::get($d, $cfg['txn_field']),
            'reason' => Arr::get($d, $cfg['reason_field']),
            'raw' => $d,
        ];
    }

    private function mapStatus(mixed $status): string
    {
        // TODO(BANK): قيم الحالة لدى البنك.
        // فقط حالات الرفض الصريحة تعني "فشل" (وتعيد المبلغ للبائع). أي حالة غير معروفة أو فارغة
        // نعاملها "قيد التحويل" حتى لا نعيد المبلغ لحوالة قد تصل فعلاً فيُدفع للبائع مرتين.
        return match (strtolower((string) $status)) {
            'paid', 'success', 'succeeded', 'completed', 'settled' => 'paid',
            'failed', 'rejected', 'declined', 'cancelled', 'canceled', 'returned', 'reversed' => 'failed',
            default => 'processing',
        };
    }
}
