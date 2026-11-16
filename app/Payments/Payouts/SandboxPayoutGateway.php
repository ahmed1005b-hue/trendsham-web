<?php

namespace App\Payments\Payouts;

use App\Models\WithdrawalRequest;

/** تحويل تجريبي ينجح فوراً — لاختبار المسار التلقائي فقط (معطّل في production). */
class SandboxPayoutGateway extends ManualPayoutGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function isAutomatic(): bool
    {
        return true;
    }

    public function send(WithdrawalRequest $withdrawal): PayoutResult
    {
        // رقم حساب ينتهي بـ 0000 = محاكاة رفض البنك
        if (str_ends_with(preg_replace('/\s+/', '', (string) $withdrawal->payout_account_number), '0000')) {
            return new PayoutResult('failed', null, 'رفض البنك التجريبي الحوالة: الحساب غير صحيح.');
        }

        return new PayoutResult('paid', 'SBX-TR-'.str_pad((string) $withdrawal->id, 6, '0', STR_PAD_LEFT));
    }
}
