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
 * صفحات العميل بالإنكليزية والتركية لا يبقى فيها نص عربي من الواجهة.
 * البيانات هنا بأسماء لاتينية، فأي حرف عربي يظهر هو نص واجهة لم يُترجم
 * (ما عدا ما عليه translate="no" مثل اسم اللغة «العربية»).
 */
class WebCustomerPagesTranslatedTest extends TestCase
{
    use FindsUntranslatedArabic, RefreshDatabase;

    private User $customer;

    private Product $product;

    private SellerProfile $store;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $seller = User::factory()->create(['role' => 'seller', 'first_name' => 'Nora', 'last_name' => 'Store']);
        $this->store = SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'Nova Store', 'status' => 'approved']);
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $this->product = Product::create([
            'vendor_id' => $this->store->id, 'category_id' => $category->id,
            'name' => 'Blue Mug', 'description' => 'A blue mug.', 'slug' => 'blue-mug', 'sku' => 'SKU-MUG',
            'price' => 10000, 'stock_quantity' => 10, 'status' => 'active', 'admin_approved_at' => now(),
        ]);

        $this->customer = User::factory()->create([
            'role' => 'customer', 'first_name' => 'Sam', 'last_name' => 'Lee', 'email' => 'sam@example.test',
        ]);
        CartItem::create(['user_id' => $this->customer->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        $this->actingAs($this->customer)->post('/customer/checkout', [
            'shipping_name' => 'Sam Lee', 'shipping_phone' => '0999999999',
            'shipping_address' => 'Damascus', 'payment_method' => 'cash_on_delivery',
        ]);
        $this->order = Order::firstOrFail();
        CartItem::create(['user_id' => $this->customer->id, 'product_id' => $this->product->id, 'quantity' => 2]);
        auth()->logout();
    }

    /** @return array<string, string> */
    private function guestPages(): array
    {
        return [
            'home' => route('home'),
            'login' => route('login'),
            'register' => route('register'),
            'forgot password' => route('password.request'),
            'forgot password (phone)' => route('password.phone.request'),
            'search' => route('search', ['q' => 'mug']),
            'category' => route('categories.show', 'gadgets'),
            'product' => route('products.show', $this->product),
            'store' => route('stores.show', $this->store),
            'about' => route('pages.about'),
            'help' => route('pages.help'),
            'verify' => route('verify.index'),
        ];
    }

    /** @return array<string, string> */
    private function customerPages(): array
    {
        return [
            'dashboard' => route('customer.dashboard'),
            'profile' => route('profile.edit'),
            'cart' => route('customer.cart.index'),
            'checkout' => route('customer.checkout.show'),
            'orders' => route('customer.orders.index'),
            'order' => route('customer.orders.show', $this->order),
            'favorites' => route('customer.favorites.index'),
            'followed stores' => route('customer.followed-stores.index'),
            'coupons' => route('customer.coupons.index'),
            'points' => route('customer.loyalty.index'),
            'cards' => route('customer.cards.index'),
            'notifications' => route('notifications.index'),
            'conversations' => route('customer.conversations.index'),
            'become a seller' => route('customer.seller-application.create'),
            'invoice' => route('invoices.show', $this->order),
        ];
    }

    public function test_customer_pages_have_no_untranslated_arabic(): void
    {
        $problems = [];
        foreach (['en', 'tr'] as $locale) {
            foreach ($this->guestPages() as $name => $url) {
                $problems = [...$problems, ...$this->arabicLeftIn($locale, $name, $url)];
            }
            $this->actingAs($this->customer);
            foreach ($this->customerPages() as $name => $url) {
                $problems = [...$problems, ...$this->arabicLeftIn($locale, $name, $url)];
            }
            auth()->logout();
        }

        $this->assertSame([], $problems, "Untranslated Arabic:\n".implode("\n", $problems));
    }
}
