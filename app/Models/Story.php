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
        'link_url',
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

    /**
     * قواعد التحقق لرابط الستوري: اختياري، وبروتوكول http/https فقط حتى لا يُحقن javascript:.
     *
     * @return array<int, string>
     */
    public static function linkRules(): array
    {
        return ['nullable', 'string', 'max:500', 'url:http,https'];
    }

    /**
     * شكل الستوري الموحّد لعارض القصص في الموقع والتطبيق.
     *
     * @return array{id: int, image: string, thumb: ?string, caption: ?string, link_url: ?string, is_from_platform: bool, vendor: ?array{id: int, store_name: string, store_logo: ?string}, expires_at: mixed}
     */
    public function toViewerArray(): array
    {
        return [
            'id' => $this->id,
            'image' => asset('storage/'.$this->image),
            'thumb' => $this->image_thumb_url,
            'caption' => $this->caption,
            'link_url' => $this->link_url,
            'is_from_platform' => $this->isFromPlatform(),
            'vendor' => $this->vendor ? [
                'id' => $this->vendor->id,
                'store_name' => $this->vendor->store_name,
                'store_logo' => $this->vendor->store_logo ? asset('storage/'.$this->vendor->store_logo) : null,
            ] : null,
            'expires_at' => $this->expires_at,
        ];
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
