<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FindsUntranslatedArabic;
use Tests\TestCase;

/**
 * لوحة الإدارة بالإنكليزية والتركية لا يبقى فيها نص عربي من الواجهة.
 * البيانات بأسماء لاتينية، فأي حرف عربي يظهر هو نص واجهة لم يُترجم.
 */
class WebAdminPagesTranslatedTest extends TestCase
{
    use FindsUntranslatedArabic, RefreshDatabase;

    public function test_admin_pages_have_no_untranslated_arabic(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'first_name' => 'Nora', 'last_name' => 'Store']);
        $store = SellerProfile::create([
            'user_id' => $seller->id, 'store_name' => 'Nova Store', 'status' => 'approved', 'identity_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $product = Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id,
            'name' => 'Blue Mug', 'description' => 'A blue mug.', 'slug' => 'blue-mug', 'sku' => 'SKU-MUG',
            'price' => 10000, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
        $customer = User::factory()->create(['role' => 'customer', 'first_name' => 'Sam', 'last_name' => 'Lee']);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($customer)->post('/customer/checkout', [
            'shipping_name' => 'Sam Lee', 'shipping_phone' => '0999999999',
            'shipping_address' => 'Damascus', 'payment_method' => 'cash_on_delivery',
        ]);
        $order = Order::firstOrFail();

        $admin = User::factory()->create(['role' => 'admin', 'first_name' => 'Ada', 'last_name' => 'Admin']);
        $names = [
            'accounting.dashboard', 'accounting.sellers', 'assistant-chats.index', 'assistant-faqs.index',
            'assistant-faqs.create', 'categories.index', 'coupon-campaigns.index', 'coupon-campaigns.create',
            'daily-deals.index', 'daily-deals.create', 'dashboard', 'employees.index',
            'loyalty.index', 'orders.index', 'products.index', 'push-broadcasts.index', 'reports.index',
            'seller-coupons.index', 'sellers.index', 'settings.edit', 'shippingCompanies.index',
            'showcase-slots.index', 'showcase-slots.create', 'showcases.index', 'stories.index',
            'support.index', 'withdrawals.index',
        ];
        $pages = [];
        foreach ($names as $name) {
            $pages[$name] = route('admin.'.$name);
        }
        $pages['accounting.seller'] = route('admin.accounting.seller', $seller);
        $pages['loyalty.show'] = route('admin.loyalty.show', $customer);
        $pages['orders.show'] = route('admin.orders.show', $order);

        $this->actingAs($admin);
        $problems = [];
        foreach (['en', 'tr'] as $locale) {
            foreach ($pages as $name => $url) {
                $problems = [...$problems, ...$this->arabicLeftIn($locale, $name, $url)];
            }
        }

        $this->assertSame([], $problems, "Untranslated Arabic:\n".implode("\n", $problems));
    }
}
