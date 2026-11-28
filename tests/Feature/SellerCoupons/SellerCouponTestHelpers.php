<?php

namespace Tests\Feature\SellerCoupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Str;

/** أدوات اختبارات كوبونات البائع. المبالغ بالوحدة الصغرى (×100). */
trait SellerCouponTestHelpers
{
    protected int $shipping = 15000; // CheckoutController::SHIPPING_FEE

    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'customer', 'phone_verified_at' => now()]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function seller(string $status = 'approved', bool $verified = true, int $commission = 10): SellerProfile
    {
        $user = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create([
            'user_id' => $user->id, 'store_name' => 'متجر '.Str::random(4), 'status' => $status, 'commission_rate' => $commission,
            'identity_verified_at' => $verified ? now() : null,
        ]);
    }

    protected function product(SellerProfile $seller, int $price = 10_000_000, int $stock = 50): Product
    {
        $category = Category::firstOrCreate(['slug' => 'c'], ['name' => 'c']);

        return Product::create([
            'vendor_id' => $seller->id, 'category_id' => $category->id, 'name' => 'منتج '.Str::random(3), 'slug' => 'p-'.Str::random(6),
            'sku' => 'S-'.Str::random(8), 'price' => $price, 'stock_quantity' => $stock, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    protected function campaign(SellerProfile $seller, array $attrs = []): CouponCampaign
    {
        return CouponCampaign::create($attrs + [
            'funder' => 'seller', 'seller_profile_id' => $seller->id, 'title' => 'خصم المتجر', 'type' => 'fixed', 'value' => 2_000_000,
            'scope' => 'all', 'audience' => 'collectable', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(14),
            'per_customer_limit' => 1, 'total_limit' => 100, 'status' => 'active',
        ]);
    }

    protected function walletCoupon(CouponCampaign $campaign, User $user): CustomerCoupon
    {
        $c = CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $user->id, 'status' => 'available', 'source' => 'collect']);
        $campaign->increment('issued_count');

        return $c;
    }

    protected function cartLine(User $user, Product $product, int $qty = 1): CartItem
    {
        return CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $product->price]);
    }

    /** دفع من الموقع؛ $choice: null = أفضل كوبون تلقائياً */
    protected function checkout(User $user, $choice = null): ?Order
    {
        $this->actingAs($user)->withSession($choice === null ? [] : ['checkout_coupon' => $choice])
            ->post(route('customer.checkout.store'), [
                'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
            ]);

        return $user->orders()->latest('id')->first();
    }

    protected function deliver(Order $order): Order
    {
        $order->changeStatus('delivered');

        return $order->fresh();
    }

    /** بيانات نموذج إنشاء صحيحة (بالليرة) */
    protected function formData(array $over = []): array
    {
        return $over + [
            'title' => 'خصم الافتتاح', 'type' => 'percentage', 'value' => 10, 'max_discount' => 20000,
            'min_order_amount' => 50000, 'scope' => 'all', 'audience' => 'collectable',
            'ends_at' => now()->addDays(14)->format('Y-m-d'), 'total_limit' => 100, 'per_customer_limit' => 1,
        ];
    }
}
