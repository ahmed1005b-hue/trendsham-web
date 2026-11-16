<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'slug',
        'description',
        'sku',
        'price',
        'compare_at_price',
        'stock_quantity',
        'low_stock_threshold',
        'status',
        'admin_approved_at',
        'rejection_note',
    ];

    protected function casts(): array
    {
        return ['admin_approved_at' => 'datetime'];
    }

    public function vendor()
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function seller()
    {
        return $this->vendor();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * يدعم الربط بالـ id أو الـ slug معاً، لأن تطبيق الموبايل
     * يرسل أحياناً الـ id وأحياناً الـ slug حسب الشاشة.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? (is_numeric($value) && ! str_contains((string) $value, '-') ? 'id' : 'slug');

        return $this->where($field, $value)->first();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function questions()
    {
        return $this->hasMany(ProductQuestion::class)->latest();
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function dailyDeals()
    {
        return $this->hasMany(DailyDeal::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('color')->orderBy('size');
    }

    public function activeDailyDeal()
    {
        $now = now();

        return $this->hasOne(DailyDeal::class)
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->where(function ($q) {
                $q->where('max_quantity', 0)
                    ->orWhereColumn('sold_quantity', '<', 'max_quantity');
            })
            ->latestOfMany();
    }

    public function availableCoupons()
    {
        return $this->hasMany(Coupon::class, 'seller_profile_id', 'vendor_id')
            ->where(function ($q) {
                $q->whereNull('product_id')->orWhere('product_id', $this->id);
            })
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    public function getEffectivePriceAttribute(): int
    {
        $deal = $this->relationLoaded('activeDailyDeal')
            ? $this->activeDailyDeal
            : $this->activeDailyDeal()->first();

        if ($deal) {
            return max(0, (int) round($deal->discounted_price));
        }

        return (int) $this->price;
    }

    public function getFormattedEffectivePriceAttribute(): string
    {
        return number_format($this->effective_price / 100).' ل.س';
    }

    public function getAverageRatingAttribute(): ?float
    {
        if (array_key_exists('reviews_avg_rating', $this->attributes)) {
            $average = $this->attributes['reviews_avg_rating'];

            return $average === null ? null : round((float) $average, 1);
        }

        $avg = $this->reviews()->avg('rating');

        return $avg ? round($avg, 1) : null;
    }

    public function getReviewsCountAttribute(): int
    {
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) $this->attributes['reviews_count'];
        }

        return $this->reviews()->count();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('status', 'active')->whereNotNull('admin_approved_at')
            ->whereDoesntHave('vendor', fn ($v) => $v->where('status', 'suspended')); // متجر موقوف
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'active')->whereNull('admin_approved_at');
    }

    public function isLive(): bool
    {
        return $this->status === 'active' && $this->admin_approved_at !== null
            && $this->vendor?->status !== 'suspended';
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function scopeApplySort($query, ?string $sort)
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'rating' => $query->withAvg('reviews', 'rating')->orderByDesc('reviews_avg_rating'),
            default => $query->latest(),
        };
    }

    public function scopePriceBetween($query, $min, $max)
    {
        if ($min !== null && $min !== '') {
            $query->where('price', '>=', (int) round(((float) $min) * 100));
        }

        if ($max !== null && $max !== '') {
            $query->where('price', '<=', (int) round(((float) $max) * 100));
        }

        return $query;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price / 100).' ل.س';
    }

    public function getFormattedCompareAtPriceAttribute(): ?string
    {
        return $this->compare_at_price ? number_format($this->compare_at_price / 100).' ل.س' : null;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }

    public function adjustStock(int $change, string $reason, ?string $note = null): InventoryMovement
    {
        return \DB::transaction(function () use ($change, $reason, $note) {
            $product = static::whereKey($this->id)->lockForUpdate()->first();

            $newQuantity = max(0, $product->stock_quantity + $change);
            $product->update(['stock_quantity' => $newQuantity]);

            return $product->inventoryMovements()->create([
                'quantity_change' => $change,
                'reason' => $reason,
                'quantity_after' => $newQuantity,
                'note' => $note,
            ]);
        });
    }
}