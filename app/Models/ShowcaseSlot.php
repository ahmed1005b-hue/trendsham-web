<?php

namespace App\Models;

use App\Models\Concerns\FreesUniqueValuesWhenTrashed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowcaseSlot extends Model
{
    use FreesUniqueValuesWhenTrashed, SoftDeletes;

    /** أعمدة فريدة تتحرر عند الحذف المؤقت */
    public const UNIQUE_ON_TRASH = ['key'];

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean', 'price_per_day' => 'decimal:2'];

    public function showcases()
    {
        return $this->hasMany(Showcase::class, 'slot_id');
    }
}
