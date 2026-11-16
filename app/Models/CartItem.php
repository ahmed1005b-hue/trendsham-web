<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'variant_id', 'quantity', 'unit_price', 'original_unit_price', 'storefront_visit_id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function storefrontVisit()
    {
        return $this->belongsTo(StorefrontVisit::class);
    }

    public function getSubtotalAttribute(): int
    {
        $unitPrice = $this->unit_price ?? ($this->product ? $this->product->price : 0);

        return $unitPrice * $this->quantity;
    }
}
