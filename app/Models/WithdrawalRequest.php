<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * طلب سحب أرباح البائع.
 *  pending    → بانتظار مراجعة الإدارة (المبلغ محجوز من الرصيد المتاح)
 *  approved   → وافقت الإدارة وخُصم المبلغ؛ بانتظار تنفيذ الحوالة
 *  processing → أُرسلت الحوالة عبر API البنك؛ بانتظار تأكيده
 *  paid       → وصل المال (رقم الحوالة محفوظ)
 *  failed     → فشلت الحوالة وأُعيد المبلغ لمحفظة البائع
 *  rejected   → رفضت الإدارة الطلب
 */
class WithdrawalRequest extends Model
{
    public const STATUS_LABELS = [
        'pending' => 'بانتظار المراجعة',
        'approved' => 'تمت الموافقة — بانتظار التحويل',
        'processing' => 'قيد التحويل لدى البنك',
        'paid' => 'تم التحويل',
        'failed' => 'فشل التحويل — أُعيد المبلغ',
        'rejected' => 'مرفوض',
    ];

    protected $fillable = [
        'vendor_id',
        'payment_method_id',
        'amount',
        'payout_method_type',
        'payout_provider',
        'payout_account_number',
        'payout_account_holder_name',
        'payout_gateway',
        'transfer_reference',
        'receipt_path',
        'failure_reason',
        'gateway_payload',
        'status',
        'note',
        'admin_note',
        'processed_at',
        'processed_by',
        'paid_at',
    ];

    protected $hidden = [
        'payout_account_number',
        'payout_account_holder_name',
        'gateway_payload',
    ];

    protected function casts(): array
    {
        return [
            'payout_account_number' => 'encrypted',
            'payout_account_holder_name' => 'encrypted',
            'gateway_payload' => 'array',
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100).' ل.س';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getPayoutTypeLabelAttribute(): string
    {
        return PaymentMethod::TYPES[$this->payout_method_type] ?? (string) $this->payout_method_type;
    }

    public function getMaskedAccountAttribute(): string
    {
        return '•••• '.substr(preg_replace('/\s+/', '', (string) $this->payout_account_number), -4);
    }

    /** للبائع: بدون رقم الحساب الكامل */
    public function toSellerArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount / 100,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'payout_method_type' => $this->payout_method_type,
            'payout_type_label' => $this->payout_type_label,
            'payout_provider' => $this->payout_provider,
            'masked_account' => $this->masked_account,
            'transfer_reference' => $this->transfer_reference,
            'has_receipt' => (bool) $this->receipt_path,
            'admin_note' => $this->admin_note,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
        ];
    }

    /** للإدارة: مع بيانات التحويل كاملة لتنفيذ الحوالة */
    public function toAdminArray(): array
    {
        return [
            ...$this->toSellerArray(),
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->vendor?->store_name,
            'payout_account_number' => $this->payout_account_number,
            'payout_account_holder_name' => $this->payout_account_holder_name,
            'payout_gateway' => $this->payout_gateway,
            'processed_at' => $this->processed_at?->toISOString(),
        ];
    }
}
