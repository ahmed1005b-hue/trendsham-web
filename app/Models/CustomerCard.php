<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بطاقة عميل محفوظة. لا نحفظ رقم البطاقة ولا CVV أبداً — فقط توكن البنك (مشفّر) وبيانات العرض.
 */
class CustomerCard extends Model
{
    protected $fillable = [
        'user_id', 'gateway', 'reference', 'token', 'brand', 'last4',
        'exp_month', 'exp_year', 'holder_name', 'status', 'is_default',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'is_default' => 'boolean',
            'exp_month' => 'integer',
            'exp_year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isExpired(): bool
    {
        if (! $this->exp_month || ! $this->exp_year) {
            return false;
        }

        return now()->startOfMonth()->gt(now()->setDate($this->exp_year, $this->exp_month, 1)->startOfMonth());
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && filled($this->token) && ! $this->isExpired();
    }

    public function getBrandLabelAttribute(): string
    {
        return match ($this->brand) {
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            null, '' => 'بطاقة',
            default => ucfirst($this->brand),
        };
    }

    public function getMaskedAttribute(): string
    {
        return '•••• '.($this->last4 ?: '----');
    }

    public function getExpiryAttribute(): ?string
    {
        return $this->exp_month && $this->exp_year
            ? sprintf('%02d/%02d', $this->exp_month, $this->exp_year % 100)
            : null;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'brand_label' => $this->brand_label,
            'last4' => $this->last4,
            'masked' => $this->masked,
            'expiry' => $this->expiry,
            'holder_name' => $this->holder_name,
            'is_default' => (bool) $this->is_default,
            'is_expired' => $this->isExpired(),
            'status' => $this->status,
        ];
    }
}
