<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Coupon extends Model
{
    protected $fillable = [
        'seller_profile_id',
        'product_id',
        'code',
        'type',
        'value',
        'min_order_amount',
        'usage_limit',
        'used_count',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isPlatform(): bool
    {
        return $this->seller_profile_id === null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    public function scopeAvailableForProduct($query, Product $product)
    {
        return $query->active()->where('seller_profile_id', $product->vendor_id)
            ->where(function ($p) use ($product) {
                $p->whereNull('product_id')->orWhere('product_id', $product->id);
            });
    }

    public function eligibleItems(Collection $items): Collection
    {
        return $items->filter(function ($item) {
            $product = $item->product ?? null;
            $vendorId = $product->vendor_id ?? ($item->vendor_id ?? null);
            $productId = $product->id ?? ($item->product_id ?? null);
            if ($this->seller_profile_id !== null && (int) $vendorId !== (int) $this->seller_profile_id) {
                return false;
            }
            if ($this->product_id !== null && (int) $productId !== (int) $this->product_id) {
                return false;
            }
            return true;
        })->values();
    }

    public function eligibleSubtotal(Collection $items): int
    {
        return (int) $this->eligibleItems($items)->sum(fn ($item) => (int) ($item->subtotal ?? 0));
    }

    public function isValidFor(int $subtotal, ?Collection $items = null): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        $eligibleSubtotal = $items ? $this->eligibleSubtotal($items) : $subtotal;
        if ($items && $this->eligibleItems($items)->isEmpty()) {
            return false;
        }
        return $eligibleSubtotal >= (int) $this->min_order_amount;
    }

    public function calculateDiscount(int $subtotal, ?Collection $items = null): int
    {
        $eligibleSubtotal = $items ? $this->eligibleSubtotal($items) : $subtotal;
        if ($eligibleSubtotal <= 0) {
            return 0;
        }
        $discount = $this->type === 'percentage'
            ? (int) round($eligibleSubtotal * $this->value / 100)
            : (int) $this->value;
        return min(max($discount, 0), $eligibleSubtotal);
    }

    public function scopeLabel(): string
    {
        if ($this->isPlatform()) {
            return __('كوبون منصة');
        }
        if ($this->product_id) {
            return __('كوبون على منتج محدد');
        }
        return __('كوبون خاص بمتجر البائع');
    }
}