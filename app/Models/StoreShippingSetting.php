<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعدادات شحن المتجر: platform = كل الطرود عبر شركة المنصة (كرم)،
 * self = المتجر يوصل بنفسه إلى المحافظات التي حدد أسعارها، والباقي عبر شركة المنصة.
 */
class StoreShippingSetting extends Model
{
    public const MODES = ['platform', 'self'];

    protected $fillable = ['seller_profile_id', 'mode', 'free_over', 'prep_days', 'delivery_days', 'allow_pickup', 'pickup_address'];

    protected function casts(): array
    {
        return [
            'free_over' => 'integer',
            'prep_days' => 'integer',
            'delivery_days' => 'integer',
            'allow_pickup' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    public function offersPickup(): bool
    {
        return $this->allow_pickup && trim((string) $this->pickup_address) !== '';
    }
}
