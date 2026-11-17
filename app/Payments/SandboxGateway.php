<?php

namespace App\Payments;

use App\GiftCards\Models\GiftCard;
use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\InitResult;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\WebhookEvent;
use Illuminate\Http\Request;

/**
 * بنك تجريبي محلي لاختبار كل مسارات الدفع وحفظ البطاقات قبل استلام مفاتيح البنك.
 * يعرض صفحة "بنك" داخل الموقع فيها أزرار نجاح/رفض وبطاقات تجريبية ثابتة — لا يُدخل أي رقم بطاقة حقيقي.
 * معطّل تلقائياً في production (راجع self::enabled()).
 */
class SandboxGateway implements PaymentGateway
{
    /** بطاقات تجريبية (أرقام اختبار عامة معروفة، ليست بطاقات حقيقية) */
    public const TEST_CARDS = [
        'visa_ok' => ['brand' => 'visa', 'last4' => '4242', 'label' => 'Visa — تنجح دائماً'],
        'mc_ok' => ['brand' => 'mastercard', 'last4' => '4444', 'label' => 'Mastercard — تنجح دائماً'],
        'visa_3ds' => ['brand' => 'visa', 'last4' => '3155', 'label' => 'Visa — تطلب تحقق إضافي من البنك'],
        'visa_declined' => ['brand' => 'visa', 'last4' => '0002', 'label' => 'Visa — مرفوضة (رصيد غير كافٍ)'],
    ];

    public static function enabled(): bool
    {
        return app()->environment(['local', 'testing']) || config('payment.sandbox_enabled');
    }

    public function name(): string
    {
        return 'sandbox';
    }

    public function initiate(Payment $payment, Order $order): InitResult
    {
        return new InitResult('redirect', route('payments.sandbox.show', ['payment', $payment->reference]));
    }

    public function initiateGiftCard(GiftCard $card): InitResult
    {
        return new InitResult('redirect', route('payments.sandbox.show', ['gift', $card->reference]));
    }

    public function verifyWebhook(Request $request): bool
    {
        return false; // النتيجة تُطبَّق مباشرة من صفحة البنك التجريبي، لا webhooks خارجية
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        throw new \LogicException('Sandbox gateway has no external webhooks');
    }

    public function supportsSavedCards(): bool
    {
        return true;
    }

    public function startCardSave(CustomerCard $card): InitResult
    {
        return new InitResult('redirect', route('payments.sandbox.show', ['card', $card->reference]));
    }

    public function chargeSavedCard(Payment $payment, Order $order, CustomerCard $card): InitResult
    {
        return match ($card->last4) {
            '0002' => new InitResult('failed', null, 'رفض البنك العملية: الرصيد غير كافٍ.'),
            '3155' => new InitResult('redirect', route('payments.sandbox.show', ['payment', $payment->reference])),
            default => new InitResult('paid', txnId: 'SBX-'.strtoupper(substr(md5($payment->reference), 0, 10))),
        };
    }

    public function deleteSavedCard(CustomerCard $card): void {}
}
