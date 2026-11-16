<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    /** أسماء أسباب الحركة للعرض (تُترجم للإنكليزية والتركية من lang/*.json) */
    public const REASON_LABELS = [
        'order_earning' => 'أرباح طلب',
        'withdrawal' => 'سحب أرباح',
        'withdrawal_reversal' => 'إرجاع حوالة فاشلة',
        'adjustment' => 'تعديل يدوي',
        'story_fee' => 'رسوم قصة',
        'showcase_booking' => 'رسوم مساحة عرض',
        'showcase_refund' => 'استرجاع رسوم مساحة عرض',
    ];

    protected $fillable = [
        'wallet_id',
        'type',
        'reason',
        'amount',
        'balance_after',
        'order_item_id',
        'withdrawal_request_id',
        'note',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function withdrawalRequest()
    {
        return $this->belongsTo(WithdrawalRequest::class);
    }

    public static function reasonLabel(?string $reason): string
    {
        return self::REASON_LABELS[$reason] ?? (string) $reason;
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount / 100).' ل.س';
    }
}
