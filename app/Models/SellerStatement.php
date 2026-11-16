<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerStatement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'totals_by_type' => 'array',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
