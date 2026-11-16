<?php

namespace App\Support;

use App\Models\Category;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * الكاش المركزي للبيانات العامة التي تُقرأ في كل صفحة تقريباً وتتغير نادراً،
 * حتى لا نسأل قاعدة البيانات عنها مع كل طلب.
 *
 * يعمل مع أي مخزن كاش (file الآن، وRedis حين يُشغَّل) عبر CACHE_STORE في .env.
 * البيانات التي تتغيّر بقرار إداري (الأقسام، الإعدادات) تُمسح فور تعديلها؛
 * وبيانات الصفحة الرئيسية (عروض، منتجات جديدة) تُحدَّث تلقائياً كل دقيقة.
 */
class AppCache
{
    public const SETTINGS = 'app:platform_settings';

    public const NAV_CATEGORIES = 'app:categories:nav';

    public const CATEGORY_TREE = 'app:categories:tree';

    public const HOME_WEB = 'app:home:web';

    /** مدة كاش الصفحة الرئيسية بالثواني — قصيرة لأن المخزون والعروض تتغير. */
    public const HOME_TTL = 60;

    /** الأقسام الرئيسية الفعّالة مرتبة (شريط الأقسام في رأس كل صفحة). */
    public static function navCategories(): Collection
    {
        return Cache::rememberForever(self::NAV_CATEGORIES,
            fn () => Category::active()->roots()->orderBy('sort_order')->get());
    }

    /** شجرة الأقسام الفعّالة مع الأقسام الفرعية (واجهة التطبيق). */
    public static function categoryTree(): Collection
    {
        return Cache::rememberForever(self::CATEGORY_TREE, fn () => Category::active()
            ->roots()
            ->with(['children' => fn ($query) => $query->active()->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get());
    }

    /**
     * بيانات الصفحة الرئيسية لمدة قصيرة.
     *
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function home(Closure $build): array
    {
        return Cache::remember(self::HOME_WEB, self::HOME_TTL, $build);
    }

    public static function forgetCategories(): void
    {
        Cache::forget(self::NAV_CATEGORIES);
        Cache::forget(self::CATEGORY_TREE);
        Cache::forget(self::HOME_WEB);
    }

    public static function forgetSettings(): void
    {
        Cache::forget(self::SETTINGS);
    }
}
