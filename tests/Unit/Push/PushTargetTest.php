<?php

namespace Tests\Unit\Push;

use App\Notifications\Push\PushTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PushTargetTest extends TestCase
{
    public static function links(): array
    {
        return [
            'customer order' => ['/customer/orders/14', ['screen' => 'order', 'id' => 14]],
            'full url with public prefix' => ['http://localhost/trendsham/public/customer/orders/14', ['screen' => 'order', 'id' => 14]],
            'https host' => ['https://trendsham.com/customer/orders/9?x=1', ['screen' => 'order', 'id' => 9]],
            'seller order' => ['/seller/orders/7', ['screen' => 'seller_order', 'id' => 7]],
            'customer conversation' => ['/customer/conversations/3', ['screen' => 'conversation', 'id' => 3]],
            'seller conversation' => ['/seller/conversations/5', ['screen' => 'conversation', 'id' => 5]],
            'seller support' => ['/seller/support/8', ['screen' => 'support', 'id' => 8]],
            'product slug' => ['/product/red-shirt-12', ['screen' => 'product', 'id' => 'red-shirt-12']],
            'store with fragment' => ['/store/4#offer-2', ['screen' => 'store', 'id' => 4]],
            'coupons' => ['/customer/coupons', ['screen' => 'coupons', 'id' => null]],
            'points' => ['/customer/points', ['screen' => 'points', 'id' => null]],
            'cart' => ['/customer/cart', ['screen' => 'cart', 'id' => null]],
            'referrals' => ['/customer/invite', ['screen' => 'referrals', 'id' => null]],
            'gift cards' => ['/customer/gift-cards', ['screen' => 'gift_cards', 'id' => null]],
            'seller wallet' => ['/seller/wallet', ['screen' => 'seller_wallet', 'id' => null]],
            'seller coupon' => ['/seller/coupons/11', ['screen' => 'seller_coupon', 'id' => 11]],
            'seller products' => ['/seller/products', ['screen' => 'seller_products', 'id' => null]],
            'seller product edit' => ['/seller/products/6/edit', ['screen' => 'seller_products', 'id' => null]],
            'customer return' => ['/customer/returns/21', ['screen' => 'return', 'id' => 21]],
            'seller return' => ['/seller/returns/22', ['screen' => 'seller_return', 'id' => 22]],
            'admin return' => ['/admin/returns/23', ['screen' => 'notifications', 'id' => null]],
            'unknown' => ['/seller/dashboard', ['screen' => 'notifications', 'id' => null]],
            'admin page' => ['/admin/orders/3', ['screen' => 'notifications', 'id' => null]],
        ];
    }

    #[DataProvider('links')]
    public function test_link_maps_to_screen(string $link, array $expected): void
    {
        $this->assertSame($expected, PushTarget::fromLink($link));
    }

    public function test_empty_link_has_no_target(): void
    {
        $this->assertNull(PushTarget::fromLink(null));
        $this->assertNull(PushTarget::fromLink(''));
    }
}
