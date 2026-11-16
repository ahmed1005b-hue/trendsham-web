<?php

namespace App\Models;

use App\Services\ProductThumbnailService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = [
        'vendor_id',
        'image',
        'caption',
        'amount_paid',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // نسخة WebP مصغّرة لدوائر القصص والقوائم؛ الأصل يبقى لعارض القصة.
        // المستمع لا يُرجع قيمة: إرجاع false يوقف بقية المستمعين (مثل StoryObserver).
        static::created(function (self $story): void {
            if ($story->image) {
                app(ProductThumbnailService::class)->generate($story->image);
            }
        });
        static::deleted(function (self $story): void {
            if ($story->image) {
                app(ProductThumbnailService::class)->delete($story->image);
            }
        });
    }

    /** رابط النسخة المصغّرة، ويعود للأصل إن لم تُنشأ. */
    protected function imageThumbUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? app(ProductThumbnailService::class)->url($this->image) : null);
    }

    public function vendor()
    {
        return $this->belongsTo(SellerProfile::class, 'vendor_id');
    }

    public function isFromPlatform(): bool
    {
        return $this->vendor_id === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeVisible($query)
    {
        return $query->where('is_active', true)->where('expires_at', '>', now());
    }
}
