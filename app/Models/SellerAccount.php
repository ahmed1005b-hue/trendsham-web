<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerAccount extends Model
{
    protected $guarded = [];
    protected $casts = ['balance' => 'decimal:2'];

    /** حساب البائع مربوط بمستخدم البائع (وليس ملف المتجر) لتوحيد الهوية مع الدفتر. */
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
