<?php

namespace App\Loyalty\Models;

use Illuminate\Database\Eloquent\Model;

/** كم نقطة أُخذت من أي دفعة في حركة استهلاك — لإرجاعها لنفس الدفعات بتواريخها. */
class LoyaltyAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['debit_transaction_id', 'lot_transaction_id', 'points'];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function lot()
    {
        return $this->belongsTo(LoyaltyTransaction::class, 'lot_transaction_id');
    }
}
