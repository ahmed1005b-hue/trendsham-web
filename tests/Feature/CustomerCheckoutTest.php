<?php

namespace Tests\Feature;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeLiveProduct(int $price = 10000, int $stock = 10): Product
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر الاختبار',
            'status' => 'approved',
        ]);

        return Product::create([
            'vendor_id' => $profile->id,
            'category_id' => Category::create(['name' => 'قسم', 'slug' => 'cat-'.uniqid()])->id,
            'name' => 'منتج اختبار',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'price' => $price,
            'stock_quantity' => $stock,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);
    }

    private function actingAsCustomerWithCart(Product $product, int $quantity = 1): User
    {
        $customer = User::factory()->create(['role' => 'customer']);
        CartItem::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        $this->actingAs($customer);

        return $customer;
    }

    private function checkoutPayload(): array
    {
        return [
            'shipping_name' => 'زبون تجريبي',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'دمشق - شارع الاختبار',
            'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ];
    }

    public function test_cash_on_delivery_order_is_created_and_stock_decremented(): void
    {
        $product = $this->makeLiveProduct(price: 10000, stock: 10);
        $customer = $this->actingAsCustomerWithCart($product, quantity: 2);

        $response = $this->post('/customer/checkout', $this->checkoutPayload());

        $response->assertRedirect(route('customer.orders.show', Order::firstOrFail()));

        $order = Order::firstOrFail();
        $this->assertSame(20000, $order->subtotal);       // 2 × 10000
        $this->assertSame(15000, $order->shipping_fee);
        $this->assertSame(35000, $order->total);
        $this->assertSame('pending', $order->status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash_on_delivery',
            'status' => 'pending',
            'amount' => 35000,
        ]);

        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->assertDatabaseMissing('cart_items', ['user_id' => $customer->id]);
    }

    public function test_card_payment_redirects_to_payment_page(): void
    {
        $product = $this->makeLiveProduct();
        $this->actingAsCustomerWithCart($product, quantity: 1);

        $payload = $this->checkoutPayload();
        $payload['payment_method'] = 'card';

        $response = $this->post('/customer/checkout', $payload);

        $response->assertRedirect(route('customer.checkout.pay', Order::firstOrFail()));
    }

    public function test_insufficient_stock_blocks_checkout(): void
    {
        $product = $this->makeLiveProduct(stock: 1);
        $this->actingAsCustomerWithCart($product, quantity: 5);

        $response = $this->post('/customer/checkout', $this->checkoutPayload());

        $response->assertSessionHas('error');
        $this->assertNull(Order::first());
        $this->assertSame(1, $product->refresh()->stock_quantity);
    }

    /** كوبون في محفظة العميل (النظام الجديد بلا رموز). */
    private function walletCoupon(User $user, array $campaign): CustomerCoupon
    {
        $c = CouponCampaign::create($campaign + [
            'funder' => 'platform', 'title' => 'خصم', 'scope' => 'all', 'audience' => 'specific',
            'starts_at' => now()->subDay(), 'per_customer_limit' => 1, 'status' => 'active',
        ]);

        return CustomerCoupon::create(['campaign_id' => $c->id, 'user_id' => $user->id, 'status' => 'available', 'source' => 'test']);
    }

    public function test_expired_coupon_is_not_applied(): void
    {
        $product = $this->makeLiveProduct(price: 10000);
        $customer = $this->actingAsCustomerWithCart($product, quantity: 1);
        $coupon = $this->walletCoupon($customer, ['type' => 'percentage', 'value' => 10, 'ends_at' => now()->subDay()]);

        // حتى لو حاول أحد فرضه في الجلسة يدوياً: يُرفض الطلب ويُبلَّغ العميل بدل تنفيذه بسعر آخر
        $this->withSession(['checkout_coupon' => $coupon->id])->post('/customer/checkout', $this->checkoutPayload())
            ->assertSessionHas('error');

        $this->assertNull(Order::first());
        $this->assertSame('available', $coupon->refresh()->status);
        $this->assertSame(0, $coupon->campaign->redeemed_count);
    }

    public function test_valid_coupon_discount_is_applied_and_usage_counted(): void
    {
        $product = $this->makeLiveProduct(price: 10000);
        $customer = $this->actingAsCustomerWithCart($product, quantity: 1);
        $coupon = $this->walletCoupon($customer, ['type' => 'percentage', 'value' => 10, 'total_limit' => 5]);

        $this->post('/customer/checkout', $this->checkoutPayload()); // الأفضل يُطبَّق تلقائياً

        $order = Order::firstOrFail();
        $this->assertSame(1000, $order->discount_amount);
        $this->assertSame($coupon->id, $order->customer_coupon_id);
        $this->assertSame(1, $coupon->campaign->refresh()->redeemed_count);
    }

    public function test_unconnected_wallet_payment_method_is_refused(): void
    {
        $product = $this->makeLiveProduct();
        $this->actingAsCustomerWithCart($product, quantity: 1);

        $payload = $this->checkoutPayload();
        $payload['payment_method'] = 'wallet';

        // لا محفظة إلكترونية مربوطة: كان الطلب يصل للبائع بلا أي دفع، ويتجاوز منع الدفع عند الاستلام
        $this->post('/customer/checkout', $payload)->assertSessionHasErrors('payment_method');

        $this->assertSame(0, Order::count());
    }

    public function test_order_items_and_shipment_are_created_correctly(): void
    {
        $product = $this->makeLiveProduct(price: 10000, stock: 10);
        $customer = $this->actingAsCustomerWithCart($product, quantity: 2);

        $this->post('/customer/checkout', $this->checkoutPayload());

        $order = Order::firstOrFail();

        // عنصر الطلب يحفظ لقطة المنتج وقت الشراء (الاسم والسعر والبائع)
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'product_name' => 'منتج اختبار',
            'unit_price' => 10000,
            'quantity' => 2,
            'subtotal' => 20000,
        ]);

        // شحنة قيد الانتظار تُنشأ تلقائياً مع كل طلب
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'status' => 'pending',
        ]);

        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('زبون تجريبي', $order->shipping_name);
    }

    public function test_customer_receives_order_created_notification(): void
    {
        $product = $this->makeLiveProduct();
        $customer = $this->actingAsCustomerWithCart($product);

        $this->post('/customer/checkout', $this->checkoutPayload());

        $order = Order::firstOrFail();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_created',
            'title' => "تم استلام طلبك #{$order->order_number}",
        ]);
    }

    public function test_coupon_below_min_order_amount_is_rejected(): void
    {
        $product = $this->makeLiveProduct(price: 5000); // السلة 5000 فقط
        $customer = $this->actingAsCustomerWithCart($product);
        $this->walletCoupon($customer, ['type' => 'fixed', 'value' => 1000, 'min_order_amount' => 50000]);

        $this->post('/customer/checkout', $this->checkoutPayload());

        $this->assertSame(0, Order::firstOrFail()->discount_amount);
    }

    public function test_guest_cannot_access_checkout(): void
    {
        $this->post('/customer/checkout', $this->checkoutPayload())
            ->assertRedirect(route('login'));
    }

    public function test_payment_method_is_validated(): void
    {
        $product = $this->makeLiveProduct();
        $this->actingAsCustomerWithCart($product);

        $payload = $this->checkoutPayload();
        $payload['payment_method'] = 'hacked_method';

        $this->post('/customer/checkout', $payload)
            ->assertSessionHasErrors('payment_method');

        $this->assertNull(Order::first());
    }

    /** الدفع عند الاستلام بلا تعهّد صريح من الزبون لا يُنشئ طلباً ولا يصل للبائع. */
    public function test_cash_on_delivery_needs_the_customer_to_confirm_paying_at_delivery(): void
    {
        $product = $this->makeLiveProduct();
        $customer = $this->actingAsCustomerWithCart($product);
        $payload = $this->checkoutPayload();
        unset($payload['cod_confirmed']);

        $this->post('/customer/checkout', $payload)->assertSessionHasErrors('cod_confirmed');
        $this->post('/customer/checkout', ['payment_method' => ''] + $payload)->assertSessionHasErrors('payment_method');

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/checkout', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('cod_confirmed');

        $this->assertNull(Order::first());
        $this->assertSame(1, $customer->cartItems()->count());
        $this->assertDatabaseMissing('notifications', ['type' => 'seller_new_order']);

        $this->actingAs($customer)->post('/customer/checkout', $payload + ['cod_confirmed' => '1'])->assertSessionHasNoErrors();
        $this->assertNotNull(Order::first());
    }

    public function test_checkout_page_does_not_pick_a_payment_method_for_the_customer(): void
    {
        $this->actingAsCustomerWithCart($this->makeLiveProduct());

        $this->get(route('customer.checkout.show'))
            ->assertOk()
            ->assertSee('اختر طريقة الدفع')
            ->assertSee('name="cod_confirmed"', false)
            ->assertDontSee('<option value="cash_on_delivery" selected', false);
    }
}
