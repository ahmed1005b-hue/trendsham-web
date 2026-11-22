<?php

namespace App\Shipping;

use App\Models\PlatformSetting;
use App\Models\SellerProfile;
use App\Models\Shipment;

/**
 * هل يعمل «التوصيل الخاص» للمتجر الآن؟ يعمل فقط إذا اختاره البائع وكان:
 *  - متجره موثّقاً،
 *  - وعنده عدد كافٍ من الطرود المسلّمة بنجاح (عبر شركة المنصة أولاً)،
 *  - ودينه للمنصة أقل من الحد الأعلى.
 * إن لم يعمل تُشحن طروده عبر شركة المنصة كالمعتاد. المبالغ بالوحدة الصغرى ×100.
 */
class SelfDelivery
{
    /** @var array<int, array{active: bool, reason: ?string, debt: int, limit: int, delivered: int, min_orders: int}> */
    private array $cache = [];

    /**
     * @return array{active: bool, reason: ?string, debt: int, limit: int, delivered: int, min_orders: int}
     */
    public function status(SellerProfile $seller): array
    {
        if (isset($this->cache[$seller->id])) {
            return $this->cache[$seller->id];
        }

        $settings = PlatformSetting::current();
        $limit = (int) ($settings->seller_debt_limit ?? 10000000);
        $minOrders = (int) ($settings->self_delivery_min_orders ?? 5);
        $debt = self::debt($seller);
        $delivered = Shipment::where('seller_profile_id', $seller->id)->where('status', 'delivered')->count();

        $reason = match (true) {
            ($seller->shippingSetting?->mode ?? 'platform') !== 'self' => null,
            ! $seller->isIdentityVerified() => __('وثّق متجرك أولاً حتى يعمل التوصيل الخاص.'),
            $delivered < $minOrders => __('يعمل التوصيل الخاص بعد :min طلبات ناجحة عبر شركة الشحن (عندك الآن :done).', ['min' => $minOrders, 'done' => $delivered]),
            $limit > 0 && $debt >= $limit => __('التوصيل الخاص متوقف: عليك :debt ل.س للمنصة، والحد الأعلى :limit ل.س. سدّد المبلغ ليعود للعمل.', [
                'debt' => number_format($debt / 100), 'limit' => number_format($limit / 100),
            ]),
            default => null,
        };
        $chosen = ($seller->shippingSetting?->mode ?? 'platform') === 'self';

        return $this->cache[$seller->id] = [
            'active' => $chosen && $reason === null,
            'reason' => $reason,
            'debt' => $debt,
            'limit' => $limit,
            'delivered' => $delivered,
            'min_orders' => $minOrders,
        ];
    }

    /** دين البائع للمنصة = رصيد محفظته إن كان بالسالب. */
    public static function debt(SellerProfile $seller): int
    {
        return max(0, -(int) ($seller->wallet?->balance ?? 0));
    }
}
