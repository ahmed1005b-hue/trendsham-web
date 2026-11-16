<?php

namespace App\Payments\Payouts;

use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

interface PayoutGateway
{
    public function name(): string;

    /** هل ترسل البوابة الحوالة تلقائياً؟ (false = يدوي من المدير) */
    public function isAutomatic(): bool;

    public function send(WithdrawalRequest $withdrawal): PayoutResult;

    public function verifyWebhook(Request $request): bool;

    /** @return array{id:int,status:string,reference:?string,reason:?string,raw:array} */
    public function parseWebhook(Request $request): array;
}
