<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class DailyDeal extends Model
{
    protected $fillable = [
        'product_id',
        'discount_percentage',
        'special_price',
        'starts_at',
        'ends_at',
        'max_quantity',
        'sold_quantity',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'max_quantity' => 'integer',
        'sold_quantity' => 'integer',
        'discount_percentage' => 'decimal:2',
        'special_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->is_active &&
            $this->starts_at <= $now &&
            $this->ends_at >= $now &&
            ($this->max_quantity === 0 || $this->sold_quantity < $this->max_quantity);
    }

    public function getRemainingTimeAttribute(): int
    {
        $now = Carbon::now();
        if ($now < $this->starts_at) {
            return 0;
        }
        if ($now > $this->ends_at) {
            return 0;
        }
        return $now->diffInSeconds($this->ends_at);
    }

    public function getIsExpiredAttribute(): bool
    {
        return Carbon::now() > $this->ends_at || ($this->max_quantity > 0 && $this->sold_quantity >= $this->max_quantity);
    }

    public function getDiscountedPriceAttribute(): int
    {
        $basePrice = (int) ($this->product?->price ?? 0);

        if ($this->special_price !== null && $this->special_price !== '') {
            // special_price is stored as major currency units (decimal 10,2).
            return max(0, (int) round(((float) $this->special_price) * 100));
        }

        $discount = max(0, min(100, (float) $this->discount_percentage));
        return max(0, (int) round($basePrice * (1 - $discount / 100)));
    }

    public function getFormattedDiscountedPriceAttribute(): string
    {
        return number_format($this->discounted_price / 100).' ل.س';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
