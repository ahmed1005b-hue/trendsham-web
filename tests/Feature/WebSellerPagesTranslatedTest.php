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
 * لوحة البائع والموظف بالإنكليزية والتركية لا يبقى فيها نص عربي من الواجهة.
 * البيانات بأسماء لاتينية، فأي حرف عربي يظهر هو نص واجهة لم يُترجم.
 */
class WebSellerPagesTranslatedTest extends TestCase
{
    use FindsUntranslatedArabic, RefreshDatabase;

    public function test_seller_and_employee_pages_have_no_untranslated_arabic(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'first_name' => 'Nora', 'last_name' => 'Store', 'status' => 'active']);
        $store = SellerProfile::create([
            'user_id' => $seller->id, 'store_name' => 'Nova Store', 'status' => 'approved', 'identity_verified_at' => now(),
        ]);
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $product = Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id,
            'name' => 'Blue Mug', 'description' => 'A blue mug.', 'slug' => 'blue-mug', 'sku' => 'SKU-MUG',
            'price' => 10000, 'stock_quantity' => 2, 'status' => 'active', 'admin_approved_at' => now(),
        ]);

        $customer = User::factory()->create(['role' => 'customer', 'first_name' => 'Sam', 'last_name' => 'Lee']);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($customer)->post('/customer/checkout', [
            'shipping_name' => 'Sam Lee', 'shipping_phone' => '0999999999',
            'shipping_address' => 'Damascus', 'payment_method' => 'cash_on_delivery',
        ]);
        $order = Order::firstOrFail();
        auth()->logout();

        $employee = User::factory()->create([
            'role' => 'employee', 'employee_status' => 'approved', 'status' => 'active',
            'first_name' => 'Eli', 'last_name' => 'Ray',
        ]);

        $sellerPages = [
            'dashboard' => route('seller.dashboard'),
            'products' => route('seller.products.index'),
            'new product' => route('seller.products.create'),
            'edit product' => route('seller.products.edit', $product),
            'orders' => route('seller.orders.index'),
            'order' => route('seller.orders.show', $order),
            'coupons' => route('seller.coupons.index'),
            'new coupon' => route('seller.coupons.create'),
            'daily deals' => route('seller.daily-deals.index'),
            'new daily deal' => route('seller.daily-deals.create'),
            'questions' => route('seller.questions.index'),
            'showcases' => route('seller.showcases.index'),
            'book showcase' => route('seller.showcases.create'),
            'stories' => route('seller.stories.index'),
            'new story' => route('seller.stories.create'),
            'support' => route('seller.support.index'),
            'new support message' => route('seller.support.create'),
            'conversations' => route('seller.conversations.index'),
            'wallet' => route('seller.wallet.index'),
            'payout methods' => route('seller.wallet.payment-methods'),
        ];
        $employeePages = [
            'employee dashboard' => route('employee.dashboard'),
            'employee orders' => route('employee.orders.index'),
            'employee products' => route('employee.products.index'),
        ];

        $problems = [];
        foreach (['en', 'tr'] as $locale) {
            $this->actingAs($seller);
            foreach ($sellerPages as $name => $url) {
                $problems = [...$problems, ...$this->arabicLeftIn($locale, $name, $url)];
            }
            $this->actingAs($employee);
            foreach ($employeePages as $name => $url) {
                $problems = [...$problems, ...$this->arabicLeftIn($locale, $name, $url)];
            }
        }

        $this->assertSame([], $problems, "Untranslated Arabic:\n".implode("\n", $problems));
    }
}
