<?php

namespace App\Referrals\Models;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    public const PENDING = 'pending';

    public const REWARDED = 'rewarded';

    public const REVERSED = 'reversed';

    protected $fillable = ['referrer_id', 'referee_id', 'status', 'order_id', 'referrer_points', 'referee_points', 'rewarded_at'];

    protected function casts(): array
    {
        return ['referrer_points' => 'integer', 'referee_points' => 'integer', 'rewarded_at' => 'datetime'];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::REWARDED => 'تمت المكافأة',
            self::REVERSED => 'أُلغيت المكافأة (أُرجع الطلب)',
            default => 'بانتظار أول طلب',
        };
    }
}
