<?php

namespace App\GiftCards\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCard extends Model
{
    public const PENDING = 'pending_payment';

    public const ACTIVE = 'active';

    public const REDEEMED = 'redeemed';

    public const FAILED = 'failed';

    protected $fillable = [
        'purchaser_id', 'amount', 'status', 'reference', 'gateway', 'gateway_txn_id', 'code_hash', 'code',
        'recipient_name', 'message', 'paid_at', 'expires_at', 'redeemed_by', 'redeemed_at',
    ];

    protected $hidden = ['code', 'code_hash'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'code' => 'encrypted',
            'paid_at' => 'datetime', 'expires_at' => 'datetime', 'redeemed_at' => 'datetime',
        ];
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchaser_id');
    }

    public function redeemer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::PENDING => 'بانتظار الدفع',
            $this->status === self::FAILED => 'لم يكتمل الدفع',
            $this->status === self::REDEEMED => 'استُخدمت',
            $this->expires_at?->isPast() => 'انتهت صلاحيتها',
            default => 'جاهزة للإرسال',
        };
    }
}
