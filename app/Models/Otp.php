<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = [
        'phone',
        'code',
        'expires_at',
        'is_used',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
        ];
    }

    public static function generateFor(string $phone, int $ttlMinutes = 5): self
    {
        static::where('phone', $phone)->where('is_used', false)->update(['is_used' => true]);

        return static::create([
            'phone' => $phone,
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }

    private const MAX_ATTEMPTS = 5;

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLockedOut(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    public function verify(string $code): bool
    {
        if ($this->isLockedOut()) {
            return false;
        }

        $this->increment('attempts');

        if ($this->is_used || $this->isExpired() || $this->isLockedOut() || $this->code !== $code) {
            return false;
        }

        $this->update(['is_used' => true]);

        return true;
    }
}
