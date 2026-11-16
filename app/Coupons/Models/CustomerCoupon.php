<?php

namespace App\Coupons\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** كوبون في محفظة عميل واحد. */
class CustomerCoupon extends Model
{
    protected $fillable = ['campaign_id', 'user_id', 'status', 'expires_at', 'source', 'used_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return ($this->expires_at && $this->expires_at->lte(now()))
            || ($this->campaign?->ends_at && $this->campaign->ends_at->lte(now()));
    }

    public function isUsable(): bool
    {
        return $this->status === 'available' && ! $this->isExpired() && (bool) $this->campaign?->isRunning();
    }

    /** آخر لحظة صلاحية (للعد التنازلي). */
    public function effectiveExpiry(): ?\Illuminate\Support\Carbon
    {
        $dates = array_filter([$this->expires_at, $this->campaign?->ends_at]);

        return $dates ? collect($dates)->min() : null;
    }
}
