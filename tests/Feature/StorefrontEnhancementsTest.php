<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StorefrontVisit;
use App\Models\User;
use App\Services\StorefrontAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorefrontEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(string $name = 'متجر تجريبي'): SellerProfile
    {
        $seller = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => $name,
            'status' => 'approved',
        ]);
    }

    private function makeProduct(SellerProfile $store, string $name, bool $visible = true): Product
    {
        $category = Category::create([
            'name' => 'قسم تجريبي',
            'slug' => 'category-'.uniqid(),
        ]);

        return Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => 'product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'price' => 10000,
            'stock_quantity' => 10,
            'status' => 'active',
            'admin_approved_at' => $visible ? now() : null,
        ]);
    }

    private function makeOrderItem(Product $product, ?int $visitId, string $status, int $quantity = 1): Order
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => 'TEST-'.uniqid(),
            'user_id' => $customer->id,
            'status' => $status,
            'subtotal' => $product->price * $quantity,
            'shipping_fee' => 0,
            'total' => $product->price * $quantity,
            'shipping_name' => 'عميل',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'عنوان تجريبي',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'subtotal' => $product->price * $quantity,
            'storefront_visit_id' => $visitId,
        ]);

        return $order;
    }

    public function test_profile_fields_validate_persist_and_are_publicly_exposed_safely(): void
    {
        $store = $this->makeStore();
        $seller = $store->user;
        Sanctum::actingAs($seller);

        $this->putJson('/api/v1/seller/store', [
            'store_name' => 'متجر مميز',
            'store_description' => 'وصف المتجر',
            'store_contact_phone' => '+963 999 123 456',
            'store_whatsapp' => '+963 999 123 456',
            'store_instagram' => 'https://instagram.com/trendsham',
            'business_hours' => "يومياً\nمن 9 إلى 5",
            'shipping_policy' => 'الشحن خلال يومين.',
            'return_policy' => 'الاستبدال خلال أسبوع.',
        ])->assertOk()
            ->assertJsonPath('data.store_whatsapp', '+963 999 123 456')
            ->assertJsonPath('data.return_policy', 'الاستبدال خلال أسبوع.');

        $this->putJson('/api/v1/seller/store', [
            'store_name' => 'متجر مميز',
            'store_instagram' => 'javascript:alert(1)',
        ])->assertUnprocessable();

        $response = $this->getJson('/api/v1/stores/'.$store->id)
            ->assertOk()
            ->assertJsonPath('store.store_contact_phone', '+963 999 123 456')
            ->assertJsonPath('store.store_whatsapp', 'https://wa.me/963999123456')
            ->assertJsonPath('store.store_instagram', 'https://instagram.com/trendsham')
            ->assertJsonPath('store.business_hours', "يومياً\nمن 9 إلى 5")
            ->assertJsonPath('store.shipping_policy', 'الشحن خلال يومين.')
            ->assertJsonPath('store.return_policy', 'الاستبدال خلال أسبوع.');

        $token = $response->json('visit_token');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_featured_products_are_public_approved_and_sales_ranked(): void
    {
        $store = $this->makeStore();
        $best = $this->makeProduct($store, 'الأكثر مبيعاً');
        $newest = $this->makeProduct($store, 'الأحدث');
        $hidden = $this->makeProduct($store, 'غير معتمد', false);
        $best->forceFill(['created_at' => now()->subDays(10)])->save();
        $newest->forceFill(['created_at' => now()])->save();
        $this->makeOrderItem($best, null, 'processing', 4);
        $this->makeOrderItem($best, null, 'cancelled', 99);
        $this->makeOrderItem($hidden, null, 'processing', 200);

        $response = $this->getJson('/api/v1/stores/'.$store->id)->assertOk();
        $featured = $response->json('featured');

        $this->assertSame($best->id, $featured['best_selling'][0]['id']);
        $this->assertSame($newest->id, $featured['recent_arrivals'][0]['id']);
        $this->assertNotContains($hidden->id, array_column($featured['best_selling'], 'id'));
        $this->assertNotContains($hidden->id, array_column($featured['recent_arrivals'], 'id'));
        $this->assertSame(2, $response->json('products.meta.total'));
    }

    public function test_api_visit_is_private_deduped_and_attributed_through_checkout(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, 'منتج');
        $token = str_repeat('a', 64);

        $this->withHeader(StorefrontAttribution::TOKEN_HEADER, $token)
            ->getJson('/api/v1/stores/'.$store->id)
            ->assertOk();
        $this->withHeader(StorefrontAttribution::TOKEN_HEADER, $token)
            ->getJson('/api/v1/stores/'.$store->id)
            ->assertOk();

        $visit = StorefrontVisit::firstOrFail();
        $this->assertSame(
            hash_hmac('sha256', $store->id.':'.$token, (string) config('app.key')),
            $visit->visitor_hash
        );
        $this->assertStringNotContainsString($token, $visit->visitor_hash);

        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $this->withHeader(StorefrontAttribution::TOKEN_HEADER, $token)
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'storefront_visit_id' => $visit->id,
        ]);
        $otherStore = $this->makeStore('متجر آخر');
        app(StorefrontAttribution::class)->recordVisit($otherStore, str_repeat('e', 64));
        $this->withHeader(StorefrontAttribution::TOKEN_HEADER, str_repeat('e', 64))
            ->postJson('/api/v1/cart', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();
        $this->assertSame(
            $visit->id,
            CartItem::where('user_id', $customer->id)->firstOrFail()->storefront_visit_id
        );

        $this->postJson('/api/v1/checkout', [
            'shipping_name' => 'عميل',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'عنوان تجريبي',
            'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('order_items', [
            'vendor_id' => $store->id,
            'storefront_visit_id' => $visit->id,
        ]);
        $metrics = app(StorefrontAttribution::class)->dashboardMetrics($store->id);
        $this->assertSame(1, $metrics['total_views']);
        $this->assertSame(1, $metrics['attributed_converting_visits']);
        $this->assertSame(1, $metrics['attributed_orders']);
        $this->assertSame(100.0, $metrics['conversion_rate']);
    }

    public function test_conversion_excludes_unattributed_and_cancelled_orders_and_other_store_visits(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, 'منتج');
        $token = str_repeat('b', 64);
        $visit = app(StorefrontAttribution::class)->recordVisit($store, $token);
        $this->makeOrderItem($product, null, 'processing');
        $this->makeOrderItem($product, $visit->id, 'cancelled');

        $metrics = app(StorefrontAttribution::class)->dashboardMetrics($store->id);
        $this->assertSame(1, $metrics['total_views']);
        $this->assertSame(0, $metrics['attributed_converting_visits']);
        $this->assertSame(0, $metrics['attributed_orders']);
        $this->assertSame(0.0, $metrics['conversion_rate']);

        $otherStore = $this->makeStore('متجر آخر');
        $otherProduct = $this->makeProduct($otherStore, 'منتج آخر');
        $otherVisit = app(StorefrontAttribution::class)->recordVisit($otherStore, str_repeat('c', 64));
        $this->makeOrderItem($otherProduct, $otherVisit->id, 'processing');
        $this->assertSame(1, app(StorefrontAttribution::class)->dashboardMetrics($store->id)['total_views']);
        $this->assertSame(0, app(StorefrontAttribution::class)->dashboardMetrics($store->id)['attributed_converting_visits']);
    }

    public function test_web_and_api_seller_dashboards_show_real_attribution_metrics(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, 'منتج');
        $visit = app(StorefrontAttribution::class)->recordVisit($store, str_repeat('f', 64));
        $this->makeOrderItem($product, $visit->id, 'processing');

        $this->actingAs($store->user)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee('زيارة فريدة')
            ->assertSee('زيارة نتج عنها طلب')
            ->assertSee('100.00%');

        Sanctum::actingAs($store->user()->firstOrFail());
        $this->getJson('/api/v1/seller/dashboard')
            ->assertOk()
            ->assertJsonPath('total_views', 1)
            ->assertJsonPath('attributed_converting_visits', 1)
            ->assertJsonPath('attributed_orders', 1)
            ->assertJsonPath('conversion_rate', 100);
    }

    public function test_web_store_displays_configured_information_and_featured_sections(): void
    {
        $store = $this->makeStore();
        $store->update([
            'store_contact_phone' => '+963999123456',
            'store_whatsapp' => '+963999123456',
            'store_instagram' => 'https://instagram.com/trendsham',
            'business_hours' => 'كل يوم من 9 إلى 5',
            'shipping_policy' => 'الشحن خلال يومين.',
            'return_policy' => 'الإرجاع خلال أسبوع.',
        ]);
        $best = $this->makeProduct($store, 'الأكثر مبيعاً');
        $new = $this->makeProduct($store, 'وصل حديثاً');
        $this->makeOrderItem($best, null, 'processing', 3);

        $this->get(route('stores.show', $store))
            ->assertOk()
            ->assertSee('tel:+963999123456', false)
            ->assertSee('https://wa.me/963999123456', false)
            ->assertSee('https://instagram.com/trendsham', false)
            ->assertSee('كل يوم من 9 إلى 5')
            ->assertSee('الشحن خلال يومين.')
            ->assertSee('الإرجاع خلال أسبوع.')
            ->assertSee('الأكثر مبيعاً')
            ->assertSee('وصل حديثاً')
            ->assertSee($best->name)
            ->assertSee($new->name);
    }

    public function test_web_cart_carries_store_visit_into_order_item(): void
    {
        $store = $this->makeStore();
        $product = $this->makeProduct($store, 'منتج ويب');
        $token = str_repeat('d', 64);
        $this->withCookie('trendsham_store_visitor', $token)
            ->get(route('stores.show', $store))
            ->assertOk();

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)
            ->withCookie('trendsham_store_visitor', $token)
            ->post(route('customer.cart.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('customer.cart.index'));

        $visit = StorefrontVisit::firstOrFail();
        $this->assertSame($visit->id, CartItem::firstOrFail()->storefront_visit_id);
        $this->post(route('customer.checkout.store'), [
            'shipping_name' => 'عميل',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'عنوان',
            'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ]);
        $this->assertSame($visit->id, OrderItem::firstOrFail()->storefront_visit_id);
    }
}
