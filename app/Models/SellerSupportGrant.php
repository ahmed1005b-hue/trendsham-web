<?php

namespace App\Models;

use Database\Factories\SellerSupportGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إذن البائع لفريق الدعم بفتح لوحة متجره لحل مشكلة.
 * لا مدة له: يبقى فعّالاً حتى يُنهيه البائع، وعندها يُغلق عند الموظف مع طلبه التالي.
 */
class SellerSupportGrant extends Model
{
    /** @use HasFactory<SellerSupportGrantFactory> */
    use HasFactory;

    protected $fillable = [
        'seller_profile_id',
        'granted_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'seller_profile_id');
    }

    /** @param  Builder<SellerSupportGrant>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
