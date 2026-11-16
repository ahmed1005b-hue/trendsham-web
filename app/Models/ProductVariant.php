<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'color',
        'color_name',
        'size',
        'sku',
        'price_adjustment',
        'stock_quantity',
        'is_active',
    ];

    protected $casts = [
        'price_adjustment' => 'integer',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->where('color', $this->color)
            ->orderBy('sort_order');
    }

    public function getEffectivePriceAttribute(): int
    {
        return $this->product->price + $this->price_adjustment;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->effective_price / 100, 0, '.', ',').' ل.س';
    }

    public function getLabelAttribute(): string
    {
        $parts = [];
        if ($this->color_name) {
            $parts[] = $this->color_name;
        } elseif ($this->color) {
            $parts[] = $this->color;
        }
        if ($this->size) {
            $parts[] = $this->size;
        }

        return $parts ? implode(' / ', $parts) : 'النوع الافتراضي';
    }
}
