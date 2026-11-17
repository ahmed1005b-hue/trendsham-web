<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Favorite;
use App\Models\Product;

/**
 * ينبّه من أضاف المنتج إلى مفضلته حين ينخفض سعره انخفاضاً ملموساً.
 * تنبيه واحد لكل منتج كل بضعة أيام، فلا يُزعج تغيير السعر المتكرر.
 */
class PriceDropNotifier
{
    public const TYPE = 'price_drop';

    /** أقل نسبة انخفاض تستحق تنبيهاً. */
    public const MIN_DROP_PERCENT = 5;

    public const COOLDOWN_DAYS = 7;

    public function handle(Product $product, int $oldPrice, int $newPrice): int
    {
        if ($oldPrice <= 0 || $newPrice >= $oldPrice || ! $product->isLive()) {
            return 0;
        }
        if (($oldPrice - $newPrice) * 100 < $oldPrice * self::MIN_DROP_PERCENT) {
            return 0;
        }

        $link = route('products.show', $product);
        $alreadyNotified = AppNotification::where('type', self::TYPE)
            ->where('link', $link)
            ->where('created_at', '>=', now()->subDays(self::COOLDOWN_DAYS))
            ->pluck('user_id');

        $userIds = Favorite::where('product_id', $product->id)
            ->whereNotIn('user_id', $alreadyNotified)
            ->whereHas('user', fn ($q) => $q->where('role', 'customer')->where('status', 'active'))
            ->pluck('user_id');

        $body = "«{$product->name}» نزل سعره من ".self::money($oldPrice).' إلى '.self::money($newPrice).'.';
        foreach ($userIds as $userId) {
            AppNotification::send((int) $userId, self::TYPE, 'انخفض سعر منتج في مفضلتك', $body, $link);
        }

        return $userIds->count();
    }

    private static function money(int $minor): string
    {
        return number_format($minor / 100).' ل.س';
    }
}
