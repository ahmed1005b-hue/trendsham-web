<?php

namespace App\Accounting;

use App\Models\AppNotification;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل تسديد متجر لدينه للمنصة (من لوحة المدير في الموقع أو التطبيق):
 * يُضاف المبلغ لمحفظته، ويُقيَّد في الدفتر، ويُسجَّل في سجل التدقيق، ويُبلَّغ البائع.
 */
class SellerDebtPayments
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @return int المبلغ المسجَّل بالقروش */
    public function record(Request $request, User $admin, SellerProfile $seller, float $amount, ?string $note): int
    {
        $minor = (int) round($amount * 100);
        $note = trim((string) $note);
        $text = 'تسديد دين للمنصة'.($note !== '' ? ': '.$note : '');

        DB::transaction(function () use ($seller, $minor, $text, $admin) {
            $tx = $seller->walletOrCreate()->applyTransaction('credit', $minor, 'adjustment', ['note' => $text]);
            $this->ledger->post($seller->user_id, LedgerType::DEBT_PAYMENT, 0, $minor / 100,
                $text, 'wallet_transaction', $tx->id, $admin->id);
        });
        Audit::log($request, 'debt_payment', $seller->user_id);

        AppNotification::send($seller->user_id, 'debt_paid', __('استلمنا تسديدك'),
            __('سجّلنا :amount ل.س تسديداً لدينك للمنصة. شكراً لك.', ['amount' => number_format($minor / 100)]),
            route('seller.wallet.index'));

        return $minor;
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000000'],
            'note' => ['nullable', 'string', 'max:150'],
        ];
    }
}
