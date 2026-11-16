<?php

namespace App\Models;

use App\Support\AppCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    protected $fillable = ['commission_rate', 'story_fee', 'loyalty_enabled', 'loyalty_spend_per_point',
        'loyalty_point_value', 'loyalty_min_redeem', 'loyalty_expiry_months', 'loyalty_coupon_valid_days',
        'returns_window_days', 'returns_seller_response_days', 'returns_dispute_days', 'returns_ship_back_days'];

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

    public function getFormattedStoryFeeAttribute(): string
    {
        return number_format($this->story_fee / 100).' ل.س';
    }
}
