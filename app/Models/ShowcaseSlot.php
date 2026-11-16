<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowcaseSlot extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'price_per_day' => 'decimal:2'];

    public function showcases()
    {
        return $this->hasMany(Showcase::class, 'slot_id');
    }
}