<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreFollower extends Model
{
    protected $fillable = ['user_id', 'seller_profile_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }
}
