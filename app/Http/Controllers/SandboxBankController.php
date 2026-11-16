<?php

namespace App\Http\Controllers;

use App\Models\CustomerCard;
use App\Models\Payment;
use App\Payments\Contracts\WebhookEvent;
use App\Payments\SandboxGateway;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * "بنك" تجريبي داخل الموقع لاختبار الدفع وحفظ البطاقات قبل استلام مفاتيح البنك الحقيقي.
 * يحاكي ما سيفعله البنك: صفحة دفع، ثم إشعار بالنتيجة، ثم إعادة العميل لموقعنا.
 */
class SandboxBankController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
        abort_unless(SandboxGateway::enabled(), 404);
    }

    public function show(Request $request, string $kind, string $reference)
    {
        [$amount, $returnUrl] = $this->resolve($request, $kind, $reference);

        return view('payments.sandbox', [
            'kind' => $kind,
            'reference' => $reference,
            'amount' => $amount,
            'returnUrl' => $returnUrl,
            'cards' => SandboxGateway::TEST_CARDS,
        ]);
    }

    public function complete(Request $request, string $kind, string $reference)
    {
        [, $returnUrl] = $this->resolve($request, $kind, $reference);

        $data = $request->validate([
            'outcome' => ['required', 'in:approve,decline,cancel'],
            'card' => ['nullable', 'in:'.implode(',', array_keys(SandboxGateway::TEST_CARDS))],
            'holder_name' => ['nullable', 'string', 'max:60'],
        ]);

        $card = SandboxGateway::TEST_CARDS[$data['card'] ?? 'visa_ok'];
        $declined = $data['outcome'] === 'decline' || ($data['card'] ?? null) === 'visa_declined';
        $status = match (true) {
            $data['outcome'] === 'cancel' => 'cancelled',
            $declined => 'failed',
            default => 'paid',
        };

        if ($kind === 'card') {
            $this->payments->handle(new WebhookEvent(
                reference: $reference,
                status: $status,
                raw: ['sandbox' => true],
                kind: 'card',
                cardToken: $status === 'paid' ? 'sbx_tok_'.bin2hex(random_bytes(12)) : null,
                cardBrand: $card['brand'],
                cardLast4: $card['last4'],
                cardExpMonth: 12,
                cardExpYear: (int) now()->addYears(3)->format('Y'),
                cardHolder: $data['holder_name'] ?: $request->user()->name,
            ));
        } else {
            $payment = Payment::where('reference', $reference)->where('gateway', 'sandbox')->firstOrFail();
            $this->payments->handle(new WebhookEvent(
                reference: $reference,
                status: $status,
                txnId: $status === 'paid' ? 'SBX-'.strtoupper(substr(md5($reference), 0, 10)) : null,
                amount: (float) $payment->amount,
                raw: ['sandbox' => true, 'card' => $card['last4']],
            ));
        }

        return redirect()->to($returnUrl);
    }

    /** @return array{0: ?string, 1: string} المبلغ المعروض + رابط العودة */
    private function resolve(Request $request, string $kind, string $reference): array
    {
        if ($kind === 'card') {
            // فقط ما أنشأه البنك التجريبي نفسه — لا يمكن "دفع" عملية بنك حقيقي من هنا
            // حتى لو رُفع الموقع بالخطأ وAPP_ENV=local.
            $card = CustomerCard::where('reference', $reference)->where('gateway', 'sandbox')->firstOrFail();
            abort_unless($card->user_id === $request->user()->id, 403);
            abort_if($card->status !== 'pending', 410, 'انتهت هذه العملية.');

            return [null, route('customer.cards.return', $reference)];
        }

        $payment = Payment::where('reference', $reference)->where('gateway', 'sandbox')->with('order')->firstOrFail();
        abort_unless($payment->order?->user_id === $request->user()->id, 403);
        abort_if($payment->status !== 'pending', 410, 'انتهت هذه العملية.');

        return [$payment->order->formatted_total, route('payments.return', $reference)];
    }
}
