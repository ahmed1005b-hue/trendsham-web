<?php

namespace Tests\Feature\Coupons;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Str;

/** أدوات مشتركة لاختبارات الكوبونات. المبالغ بالوحدة الصغرى (×100). */
trait CouponTestHelpers
{
    protected function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'customer', 'phone_verified_at' => now()]);
    }

    protected function vendor(int $commission = 10): SellerProfile
    {
        $user = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create(['user_id' => $user->id, 'store_name' => 'S'.Str::random(4), 'status' => 'approved', 'commission_rate' => $commission]);
    }

    protected function product(SellerProfile $vendor, int $price = 10_000_000, ?Category $category = null): Product
    {
        $category ??= Category::firstOrCreate(['slug' => 'c'], ['name' => 'c']);

        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'p', 'slug' => 'p-'.Str::random(6),
            'sku' => 'S-'.Str::random(8), 'price' => $price, 'stock_quantity' => 50, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    protected function campaign(array $attrs = []): CouponCampaign
    {
        return CouponCampaign::create($attrs + [
            'funder' => 'platform', 'title' => 'خصم', 'type' => 'fixed', 'value' => 2_000_000,
            'scope' => 'all', 'audience' => 'all_customers', 'starts_at' => now()->subDay(),
            'per_customer_limit' => 1, 'status' => 'active',
        ]);
    }

    protected function walletCoupon(CouponCampaign $campaign, User $user, array $attrs = []): CustomerCoupon
    {
        return CustomerCoupon::create($attrs + [
            'campaign_id' => $campaign->id, 'user_id' => $user->id, 'status' => 'available', 'source' => 'test',
        ]);
    }

    /** سطر سلة كما تستعمله الخدمات: product + subtotal */
    protected function line(Product $product, int $qty = 1): object
    {
        return (object) ['product' => $product, 'product_id' => $product->id, 'subtotal' => $product->price * $qty, 'quantity' => $qty];
    }
}
