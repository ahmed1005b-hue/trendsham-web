<?php

namespace App\Services;

use App\Accounting\LedgerType;
use App\Models\LedgerEntry;
use App\Models\SellerAccount;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * يضيف قيداً. إن وُجد قيد بنفس (النوع + المصدر) يعيده دون تكرار.
     * يجب أن يكون المبلغ إما مديناً أو دائناً.
     */
    public function post(
        int $sellerId,
        string $type,
        float $debit,
        float $credit,
        string $description,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?int $createdBy = null,
        ?int $reversesEntryId = null,
    ): LedgerEntry {
        $debit  = round($debit, 2);
        $credit = round($credit, 2);

        if (($debit > 0) === ($credit > 0)) {
            throw new \InvalidArgumentException('يجب أن يكون القيد إما مديناً أو دائناً بمبلغ أكبر من صفر.');
        }

        return DB::transaction(function () use ($sellerId, $type, $debit, $credit, $description, $sourceType, $sourceId, $createdBy, $reversesEntryId) {
            SellerAccount::firstOrCreate(['seller_id' => $sellerId]);
            $acc = SellerAccount::where('seller_id', $sellerId)->lockForUpdate()->firstOrFail();

            if ($sourceType !== null && $sourceId !== null) {
                $existing = LedgerEntry::forSeller($sellerId)
                    ->where('entry_type', $type)
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->first();
                if ($existing) {
                    return $existing; // idempotent
                }
            }

            $balance = round((float) $acc->balance + $credit - $debit, 2);

            $entry = LedgerEntry::create([
                'seller_id' => $sellerId,
                'entry_type' => $type,
                'debit' => $debit,
                'credit' => $credit,
                'balance_after' => $balance,
                'currency' => $acc->currency,
                'description' => mb_substr($description, 0, 255),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'reverses_entry_id' => $reversesEntryId,
                'created_by' => $createdBy,
                'posted_at' => now(),
            ]);

            $acc->update(['balance' => $balance]);

            return $entry;
        });
    }

    // ---- اختصارات للأحداث الشائعة ----

    public function sale(int $sellerId, float $amount, string $desc, string $srcType, int $srcId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::SALE, 0, $amount, $desc, $srcType, $srcId);
    }

    public function commission(int $sellerId, float $amount, string $desc, string $srcType, int $srcId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::COMMISSION, $amount, 0, $desc, $srcType, $srcId);
    }

    public function refund(int $sellerId, float $amount, string $desc, string $srcType, int $srcId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::REFUND, $amount, 0, $desc, $srcType, $srcId);
    }

    public function commissionReversal(int $sellerId, float $amount, string $desc, string $srcType, int $srcId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::COMM_REVERSAL, 0, $amount, $desc, $srcType, $srcId);
    }

    public function payout(int $sellerId, float $amount, string $desc, int $withdrawalId, ?int $adminId = null): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::PAYOUT, $amount, 0, $desc, 'withdrawal', $withdrawalId, $adminId);
    }

    /** حوالة فشلت بعد خصمها: نعيد المبلغ لحساب البائع (دائن). */
    public function payoutReversal(int $sellerId, float $amount, string $desc, int $withdrawalId, ?int $adminId = null): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::PAYOUT_REVERSAL, 0, $amount, $desc, 'withdrawal', $withdrawalId, $adminId);
    }

    public function showcaseFee(int $sellerId, float $amount, string $desc, int $showcaseId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::SHOWCASE_FEE, $amount, 0, $desc, 'showcase', $showcaseId);
    }

    public function showcaseRefund(int $sellerId, float $amount, string $desc, int $showcaseId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::SHOWCASE_REFUND, 0, $amount, $desc, 'showcase_refund', $showcaseId);
    }

    /** أجرة شحن تحمّلها البائع بكوبون شحن مجاني (مدين عليه). المصدر سجل استخدام الكوبون. */
    public function couponShipping(int $sellerId, float $amount, string $desc, int $redemptionId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::COUPON_SHIPPING, $amount, 0, $desc, 'coupon_redemption', $redemptionId);
    }

    public function couponShippingReversal(int $sellerId, float $amount, string $desc, int $redemptionId): LedgerEntry
    {
        return $this->post($sellerId, LedgerType::COUPON_SHIPPING_REVERSAL, 0, $amount, $desc, 'coupon_redemption', $redemptionId);
    }

    /** تسوية يدوية من المدير، سببها إلزامي. */
    public function adjustment(int $sellerId, string $direction, float $amount, string $reason, int $adminId): LedgerEntry
    {
        abort_if(mb_strlen(trim($reason)) < 5, 422, 'سبب التسوية مطلوب (5 أحرف على الأقل).');

        return $this->post(
            $sellerId,
            LedgerType::ADJUSTMENT,
            $direction === 'debit' ? $amount : 0,
            $direction === 'credit' ? $amount : 0,
            'تسوية: ' . trim($reason),
            null,
            null,
            $adminId
        );
    }
}
