<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * طريقة استلام أرباح البائع (حساب بنكي/IBAN، بطاقة، محفظة إلكترونية).
 * رقم الحساب واسم صاحبه مشفّران في قاعدة البيانات؛ last4 للعرض فقط.
 */
class PaymentMethod extends Model
{
    public const TYPES = [
        'bank_account' => 'حساب بنكي (IBAN)',
        'bank_card' => 'بطاقة بنكية',
        'electronic_wallet' => 'محفظة إلكترونية',
    ];

    protected $fillable = [
        'vendor_id',
        'type',
        'provider',
        'account_number',
        'account_holder_name',
        'last4',
        'is_default',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $hidden = ['account_number', 'account_holder_name'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'account_holder_name' => 'encrypted',
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PaymentMethod $method) {
            if ($method->isDirty('account_number')) {
                $method->last4 = substr(preg_replace('/\s+/', '', (string) $method->account_number), -4);
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function scopeBankCards($query)
    {
        return $query->where('type', 'bank_card');
    }

    public function scopeElectronicWallets($query)
    {
        return $query->where('type', 'electronic_wallet');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getMaskedNumberAttribute(): string
    {
        return '•••• '.($this->last4 ?: '----');
    }

    /** للبائع نفسه: بدون الرقم الكامل */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'provider' => $this->provider,
            'masked_number' => $this->masked_number,
            'account_holder_name' => $this->account_holder_name,
            'is_default' => (bool) $this->is_default,
            'is_verified' => (bool) $this->is_verified,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
