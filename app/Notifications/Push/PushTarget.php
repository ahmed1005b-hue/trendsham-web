<?php

namespace App\Notifications\Push;

/**
 * يستنتج الشاشة التي يفتحها الإشعار في التطبيق من رابط الويب المحفوظ فيه،
 * فلا نعدّل مواضع إنشاء الإشعارات. الرابط غير المعروف ← صفحة الإشعارات.
 */
final class PushTarget
{
    public const SCREENS = [
        'order', 'seller_order', 'conversation', 'support', 'product', 'store', 'coupons', 'points',
        'seller_wallet', 'seller_coupon', 'seller_products', 'return', 'seller_return', 'cart', 'referrals', 'gift_cards', 'notifications',
    ];

    /** نمط المسار ← [الشاشة، هل المعرّف رقمي]. الترتيب مهم. */
    private const PATTERNS = [
        '#^customer/orders/(\d+)$#' => ['order', true],
        '#^seller/orders/(\d+)$#' => ['seller_order', true],
        '#^customer/returns/(\d+)$#' => ['return', true],
        '#^seller/returns/(\d+)$#' => ['seller_return', true],
        '#^(?:customer|seller)/conversations/(\d+)$#' => ['conversation', true],
        '#^seller/support/(\d+)$#' => ['support', true],
        '#^product/([^/]+)$#' => ['product', false],
        '#^store/(\d+)$#' => ['store', true],
        '#^customer/coupons$#' => ['coupons', null],
        '#^customer/points$#' => ['points', null],
        '#^customer/cart$#' => ['cart', null],
        '#^customer/invite$#' => ['referrals', null],
        '#^customer/gift-cards$#' => ['gift_cards', null],
        '#^seller/wallet$#' => ['seller_wallet', null],
        '#^seller/coupons/(\d+)$#' => ['seller_coupon', true],
        '#^seller/products(?:/.*)?$#' => ['seller_products', null],
    ];

    public static function fromLink(?string $link): ?array
    {
        if ($link === null || trim($link) === '') {
            return null;
        }

        $path = parse_url($link, PHP_URL_PATH) ?: '';
        // يتجاهل البادئة حتى /public/ (localhost/trendsham/public/…)
        if (($pos = strpos($path, '/public/')) !== false) {
            $path = substr($path, $pos + strlen('/public'));
        }
        $path = trim($path, '/');

        foreach (self::PATTERNS as $pattern => [$screen, $numeric]) {
            if (preg_match($pattern, $path, $m)) {
                $id = null;
                if ($numeric !== null && isset($m[1])) {
                    $id = $numeric ? (int) $m[1] : urldecode($m[1]);
                }

                return ['screen' => $screen, 'id' => $id];
            }
        }

        return ['screen' => 'notifications', 'id' => null];
    }
}
