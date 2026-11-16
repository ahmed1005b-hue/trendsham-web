<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerReview extends Model
{
    protected $fillable = ['vendor_id', 'user_id', 'order_item_id', 'rating', 'comment'];

    public function vendor()
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
