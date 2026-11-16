<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn() => throw new \LogicException('قيود الدفتر لا يمكن تعديلها. أنشئ قيد تصحيح.'));
        static::deleting(fn() => throw new \LogicException('قيود الدفتر لا يمكن حذفها. أنشئ قيد تصحيح.'));
    }

    /** المدخل الوحيد المسموح لقراءة قيود بائع. */
    public function scopeForSeller($q, int $sellerId)
    {
        return $q->where('seller_id', $sellerId);
    }
}
