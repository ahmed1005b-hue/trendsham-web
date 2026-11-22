<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** أجرة توصيل المتجر بنفسه إلى محافظة واحدة (بالوحدة الصغرى ×100). */
class StoreShippingRate extends Model
{
    protected $fillable = ['seller_profile_id', 'governorate', 'fee'];

    protected function casts(): array
    {
        return ['fee' => 'integer'];
    }
}
