<?php

namespace App\Payments;

use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\InitResult;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\WebhookEvent;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * بوابة البنك الحقيقية (Hosted Checkout + Tokenization).
 *
 * كل ما يختلف بين البنوك (المسارات، أسماء الحقول، قيم الحالات، التوقيع) موجود في config/payment.php
 * تحت 'bank'، فعند استلام وثائق البنك يكفي غالباً تعبئة .env وتعديل القيم المعلّمة TODO(BANK).
 * إن احتاج البنك شكل طلب مختلف جذرياً فعدّل الدوال payload*() هنا فقط.
 */
class BankGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'bank';
    }

    // ------------------------------------------------------------------ دفع طلب

    public function initiate(Payment $payment, Order $order): InitResult
    {
        $res = $this->client()->post($this->path('checkout'), $this->payloadCheckout($payment, $order))->throw();

        $url = $res->json($this->field('checkout_url'));
        if (! $url) {
            throw new \RuntimeException('البنك لم يُرجع رابط صفحة الدفع (راجع PAYMENT_GATEWAY_CHECKOUT_URL_FIELD).');
        }

        return new InitResult('redirect', $url, raw: (array) $res->json());
    }

    // ------------------------------------------------------------------ البطاقات المحفوظة

    public function supportsSavedCards(): bool
    {
        return true;
    }

    public function startCardSave(CustomerCard $card): InitResult
    {
        $res = $this->client()->post($this->path('card_save'), [
            'merchant_id' => config('payment.bank.merchant_id'),
            'reference' => $card->reference,
            'customer_id' => (string) $card->user_id,
            'return_url' => route('customer.cards.return', $card->reference),
            'notify_url' => route('payments.webhook', 'bank'),
        ])->throw();

        $url = $res->json($this->field('checkout_url'));
        if (! $url) {
            throw new \RuntimeException('البنك لم يُرجع رابط صفحة حفظ البطاقة.');
        }

        return new InitResult('redirect', $url, raw: (array) $res->json());
    }

    public function chargeSavedCard(Payment $payment, Order $order, CustomerCard $card): InitResult
    {
        $res = $this->client()->post($this->path('card_charge'), [
            ...$this->payloadCheckout($payment, $order),
            'token' => $card->token,
        ]);

        $data = (array) $res->json();
        if (! $res->successful()) {
            return new InitResult('failed', null, Arr::get($data, 'message', 'رفض البنك عملية الدفع.'), raw: $data);
        }

        // البنك طلب تحقق 3-D Secure: نوجّه العميل، والنتيجة النهائية تصل عبر webhook
        if ($redirect = Arr::get($data, $this->field('redirect_url'))) {
            return new InitResult('redirect', $redirect, raw: $data);
        }

        $status = $this->mapStatus(Arr::get($data, $this->field('status')));

        return match ($status) {
            'paid' => new InitResult('paid', txnId: (string) Arr::get($data, $this->field('txn')), raw: $data),
            'pending' => new InitResult('manual', null, 'عملية الدفع قيد المعالجة لدى البنك.', raw: $data),
            default => new InitResult('failed', null, Arr::get($data, 'message', 'رفض البنك عملية الدفع.'), raw: $data),
        };
    }

    public function deleteSavedCard(CustomerCard $card): void
    {
        if (! $card->token) {
            return;
        }

        try {
            $this->client()->delete(str_replace('{token}', urlencode($card->token), $this->path('card_delete')));
        } catch (\Throwable $e) {
            // حذف البطاقة عندنا لا يتوقف على نجاح الحذف عند البنك
            Log::warning('Bank: تعذّر حذف توكن البطاقة', ['card_id' => $card->id, 'error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ الإشعارات (Webhook)

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('payment.bank.webhook_secret');
        $sig = (string) $request->header(config('payment.bank.signature_header', 'X-Signature'), '');

        if ($secret === '' || $sig === '') {
            return false;
        }

        // TODO(BANK): بعض البنوك تضيف بادئة مثل "sha256=" أو ترسل التوقيع base64
        $sig = preg_replace('/^sha256=/i', '', $sig);
        $expected = hash_hmac(config('payment.bank.signature_algo', 'sha256'), $request->getContent(), $secret);

        return hash_equals($expected, $sig);
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        $d = $request->json()->all();
        $get = fn (string $key) => Arr::get($d, $this->field($key));

        $isCard = $get('event') === 'card' || ($get('token') !== null && $get('amount') === null);
        $amount = $get('amount');

        return new WebhookEvent(
            reference: (string) $get('reference'),
            status: $this->mapStatus($get('status')),
            txnId: $get('txn') !== null ? (string) $get('txn') : null,
            amount: $amount !== null ? $this->amountFromBank((float) $amount) : null,
            raw: $d,
            kind: $isCard ? 'card' : 'payment',
            cardToken: $get('token'),
            cardBrand: $get('brand') ? strtolower((string) $get('brand')) : null,
            cardLast4: $get('last4') ? substr((string) $get('last4'), -4) : null,
            cardExpMonth: $get('exp_month') ? (int) $get('exp_month') : null,
            cardExpYear: $get('exp_year') ? (int) $get('exp_year') : null,
            cardHolder: $get('holder'),
        );
    }

    // ------------------------------------------------------------------ أدوات

    private function payloadCheckout(Payment $payment, Order $order): array
    {
        return [
            'merchant_id' => config('payment.bank.merchant_id'),
            'reference' => $payment->reference,
            'amount' => $this->amountToBank((int) $payment->amount),
            'currency' => $payment->currency,
            'description' => 'طلب رقم '.$order->order_number,
            'customer_id' => (string) $order->user_id,
            'return_url' => route('payments.return', $payment->reference),
            'notify_url' => route('payments.webhook', 'bank'),
        ];
    }

    private function client(): PendingRequest
    {
        $cfg = config('payment.bank');
        if (! $cfg['url'] || ! $cfg['key']) {
            throw new \RuntimeException('بوابة البنك غير مضبوطة: املأ PAYMENT_GATEWAY_URL و PAYMENT_GATEWAY_KEY في .env');
        }

        // TODO(BANK): طريقة المصادقة (Bearer هو الأشيع؛ بعض البنوك تستخدم Basic أو ترويسة خاصة)
        return Http::baseUrl(rtrim($cfg['url'], '/'))
            ->withToken($cfg['key'])
            ->acceptJson()
            ->asJson()
            ->timeout($cfg['timeout'] ?? 20);
    }

    private function path(string $key): string
    {
        return '/'.ltrim((string) config("payment.bank.paths.$key"), '/');
    }

    private function field(string $key): string
    {
        return (string) config("payment.bank.fields.$key", $key);
    }

    private function mapStatus(mixed $status): string
    {
        $status = strtolower((string) $status);

        return config('payment.bank.status_map')[$status] ?? 'failed';
    }

    private function amountToBank(int $minor): int|float
    {
        return config('payment.bank.amount_in_minor_units') ? $minor : round($minor / 100, 2);
    }

    private function amountFromBank(float $amount): float
    {
        return config('payment.bank.amount_in_minor_units') ? $amount : round($amount * 100);
    }
}
