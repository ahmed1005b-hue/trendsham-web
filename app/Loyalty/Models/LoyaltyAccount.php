<?php

namespace App\Loyalty\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** رصيد نقاط عميل واحد. يُعدَّل فقط عبر LoyaltyLedger وتحت قفل الصف. */
class LoyaltyAccount extends Model
{
    protected $fillable = ['user_id', 'balance', 'debt', 'lifetime_earned'];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'debt' => 'integer', 'lifetime_earned' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
