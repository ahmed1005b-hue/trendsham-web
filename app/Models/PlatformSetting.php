<?php

namespace App\Models;

use App\Support\AppCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = ['commission_rate', 'story_fee', 'loyalty_enabled', 'loyalty_spend_per_point',
        'loyalty_point_value', 'loyalty_min_redeem', 'loyalty_expiry_months', 'loyalty_coupon_valid_days',
        'returns_window_days', 'returns_seller_response_days', 'returns_dispute_days', 'returns_ship_back_days',
        'social_instagram', 'social_facebook', 'social_tiktok', 'social_x', 'social_whatsapp',
        'shipping_parcel_fee', 'shipping_heavy_parcel_fee', 'shipping_home_extra'];

    /**
     * حسابات المتجر على مواقع التواصل بالترتيب الظاهر أسفل الموقع.
     *
     * @var array<string, array{label: string, icon: string, host: string}>
     */
    public const SOCIAL = [
        'social_instagram' => ['label' => 'إنستغرام', 'icon' => 'bi-instagram', 'host' => 'instagram.com'],
        'social_facebook' => ['label' => 'فيسبوك', 'icon' => 'bi-facebook', 'host' => 'facebook.com'],
        'social_tiktok' => ['label' => 'تيك توك', 'icon' => 'bi-tiktok', 'host' => 'tiktok.com'],
        'social_x' => ['label' => 'X', 'icon' => 'bi-twitter-x', 'host' => 'x.com'],
    ];

    /** الإعدادات من الكاش؛ تُمسح فور حفظ أي تعديل عليها. */
    public static function current(): self
    {
        return Cache::rememberForever(AppCache::SETTINGS,
            fn () => static::query()->firstOrCreate([], ['commission_rate' => 10]));
    }

    protected static function booted(): void
    {
        static::saved(fn () => AppCache::forgetSettings());
        static::deleted(fn () => AppCache::forgetSettings());
    }

    /**
     * الروابط المكتوبة فقط؛ الحساب الفارغ لا تظهر أيقونته.
     *
     * @return list<array{label: string, icon: string, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];
        foreach (self::SOCIAL as $field => $meta) {
            if ($this->{$field}) {
                $links[] = ['label' => $meta['label'], 'icon' => $meta['icon'], 'url' => $this->{$field}];
            }
        }
        if ($this->social_whatsapp) {
            $links[] = ['label' => 'واتساب', 'icon' => 'bi-whatsapp', 'url' => 'https://wa.me/'.$this->social_whatsapp];
        }

        return $links;
    }

    public function getFormattedStoryFeeAttribute(): string
    {
        return number_format($this->story_fee / 100).' ل.س';
    }
}
