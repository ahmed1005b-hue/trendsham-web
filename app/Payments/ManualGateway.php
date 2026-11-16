<?php

namespace App\Payments;

use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\InitResult;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\WebhookEvent;
use Illuminate\Http\Request;

class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function initiate(Payment $payment, Order $order): InitResult
    {
        return new InitResult('manual', null, 'الدفع الإلكتروني غير مفعّل حالياً. سيتم الدفع عند الاستلام، وسنؤكد طلبك قريباً.');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false; // لا توجد إشعارات في الدفع اليدوي
    }

    public function parseWebhook(Request $request): WebhookEvent
    {
        throw new \LogicException('Manual gateway has no webhooks');
    }

    public function supportsSavedCards(): bool
    {
        return false;
    }

    public function startCardSave(CustomerCard $card): InitResult
    {
        return new InitResult('failed', null, 'حفظ البطاقات غير متاح حتى يتم ربط بوابة البنك.');
    }

    public function chargeSavedCard(Payment $payment, Order $order, CustomerCard $card): InitResult
    {
        return new InitResult('failed', null, 'الدفع بالبطاقة غير متاح حالياً.');
    }

    public function deleteSavedCard(CustomerCard $card): void
    {
    }
}
