<?php

namespace App\Payments\Payouts;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

/** المدير يحوّل من حساب المنصة بنفسه (تطبيق البنك/الفرع) ثم يسجّل رقم الحوالة والإيصال. */
class ManualPayoutGateway implements PayoutGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function isAutomatic(): bool
    {
        return false;
    }

    public function send(WithdrawalRequest $withdrawal): PayoutResult
    {
        return new PayoutResult('manual');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        throw new \LogicException('Manual payouts have no webhooks');
    }
}
