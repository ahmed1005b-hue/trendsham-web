<?php

namespace App\Payments\Contracts;

use App\GiftCards\Models\GiftCard;
use App\Models\CustomerCard;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /** يبدأ دفع طلب: عادة يعيد رابط صفحة الدفع لدى البنك. */
    public function initiate(Payment $payment, Order $order): InitResult;

    /** يبدأ دفع شراء بطاقة هدية (مرجعها card->reference، والنتيجة تصل كإشعار دفع عادي). */
    public function initiateGiftCard(GiftCard $card): InitResult;

    public function verifyWebhook(Request $request): bool;

    public function parseWebhook(Request $request): WebhookEvent;

    /** هل تدعم البوابة حفظ البطاقات (Tokenization)؟ */
    public function supportsSavedCards(): bool;

    /** يفتح صفحة البنك لإدخال بطاقة وحفظها؛ النتيجة تصل عبر webhook (kind=card). */
    public function startCardSave(CustomerCard $card): InitResult;

    /** دفع طلب ببطاقة محفوظة (قد يعيد redirect لتحقق 3-D Secure). */
    public function chargeSavedCard(Payment $payment, Order $order, CustomerCard $card): InitResult;

    /** حذف التوكن لدى البنك عند حذف العميل لبطاقته. */
    public function deleteSavedCard(CustomerCard $card): void;
}
