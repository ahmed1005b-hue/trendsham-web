<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    // Keep existing fields (method/transaction_ref) for backward compatibility + add spec fields.
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'paid_at' => 'datetime',
        'amount'  => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customerCard()
    {
        return $this->belongsTo(CustomerCard::class);
    }
}
