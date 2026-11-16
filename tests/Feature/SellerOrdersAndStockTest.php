<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * مشاكل كشفها الفحص الهندسي لصفحات البائع: حالة الطلب، الأرباح، المخزون، الألوان والمقاسات، حذف البائع.
 */
class SellerOrdersAndStockTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'c', 'slug' => 'c']);
    }

    private function seller(): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'S'.Str::random(4), 'status' => 'approved', 'commission_rate' => 10]);

        return [$user, $vendor];
    }

    private function product(SellerProfile $vendor, int $stock = 10): Product
    {
        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'p', 'slug' => 'p-'.Str::random(6),
            'sku' => 'SKU-'.Str::random(8), 'price' => 10000, 'stock_quantity' => $stock, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    private function order(User $customer, array $lines, string $status = 'pending'): Order
    {
        $order = Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(6)), 'user_id' => $customer->id,
            'status' => $status, 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
        foreach ($lines as [$product, $qty, $variant]) {
            OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'variant_id' => $variant?->id, 'vendor_id' => $product->vendor_id,
                'product_name' => 'p', 'unit_price' => 10000, 'quantity' => $qty, 'subtotal' => 10000 * $qty,
            ]);
        }

        return $order;
    }

    // ------------------------------------------------------------ حالة الطلب من البائع

    public function test_seller_cannot_change_status_of_order_shared_with_another_seller(): void
    {
        [$userA, $vendorA] = $this->seller();
        [, $vendorB] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendorA), 1, null], [$this->product($vendorB), 1, null]]);

        $this->actingAs($userA)->post(route('seller.orders.updateStatus', $order), ['status' => 'shipped']);

        $this->assertSame('pending', $order->fresh()->status, 'بائع واحد لا يغيّر حالة طلب فيه منتجات بائعين آخرين');
    }

    public function test_seller_cannot_mark_order_as_paid(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null]]);

        $this->actingAs($user)->post(route('seller.orders.updateStatus', $order), ['status' => 'paid']);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_seller_marking_delivered_credits_earnings(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null]], 'shipped');

        $this->actingAs($user)->post(route('seller.orders.updateStatus', $order), ['status' => 'delivered']);

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(9000, $vendor->wallet()->first()?->balance, 'أرباح الطلب المسلَّم (بعد عمولة 10%) تدخل المحفظة');
    }

    public function test_seller_cannot_move_delivered_order_backwards(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null]], 'delivered');

        $this->actingAs($user)->post(route('seller.orders.updateStatus', $order), ['status' => 'cancelled']);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    // ------------------------------------------------------------ طلبات البائع في التطبيق (API)

    public function test_api_seller_orders_lists_orders_for_his_products_not_his_purchases(): void
    {
        [$user, $vendor] = $this->seller();
        $sale = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null]]);
        [, $other] = $this->seller();
        $purchase = $this->order($user, [[$this->product($other), 1, null]]); // البائع اشترى كعميل

        $rows = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/seller/orders')->assertOk()->json('data'));

        // التطبيق يعرض صفاً لكل منتج من منتجات البائع، ويحدّث الحالة بـ order_id
        $this->assertTrue($rows->pluck('order_id')->contains($sale->id));
        $this->assertFalse($rows->pluck('order_id')->contains($purchase->id));
        $this->assertSame($sale->order_number, $rows->first()['order_number']);
        $this->assertSame('pending', $rows->first()['order_status']);
        $this->assertSame(['processing', 'shipped', 'delivered', 'cancelled'], $rows->first()['next_statuses']);
    }

    public function test_api_seller_orders_hide_other_sellers_items_in_shared_order(): void
    {
        [$user, $vendor] = $this->seller();
        [, $other] = $this->seller();
        $mine = $this->product($vendor);
        $this->order(User::factory()->create(), [[$mine, 1, null], [$this->product($other), 1, null]]);

        $rows = collect($this->actingAs($user, 'sanctum')->getJson('/api/v1/seller/orders')->assertOk()->json('data'));

        $this->assertSame([$mine->id], $rows->pluck('product_id')->all());
        $this->assertSame([], $rows->first()['next_statuses'], 'الطلب المشترك تديره الإدارة');
    }

    public function test_api_seller_can_update_status_of_his_order(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null]]);

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/seller/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();

        $this->assertSame('processing', $order->fresh()->status);
    }

    // ------------------------------------------------------------ بائع بلا متجر

    public function test_seller_without_store_is_redirected_not_crashed(): void
    {
        $user = User::factory()->create(['role' => 'seller']);

        $this->actingAs($user)->get(route('seller.dashboard'))
            ->assertRedirect(route('seller.identity-verification.create'));
        $this->actingAs($user)->get(route('seller.wallet.index'))
            ->assertRedirect(route('seller.identity-verification.create'));
        $this->actingAs($user)->get(route('seller.identity-verification.create'))->assertOk();
    }

    // ------------------------------------------------------------ حذف البائع

    public function test_admin_cannot_delete_seller_with_wallet_balance_or_withdrawals(): void
    {
        [$user, $vendor] = $this->seller();
        $vendor->walletOrCreate()->update(['balance' => 50000]);
        WithdrawalRequest::create(['vendor_id' => $vendor->id, 'amount' => 10000, 'status' => 'pending']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('admin.sellers.destroy', $vendor))->assertSessionHas('error');

        $this->assertNotNull($vendor->fresh(), 'المتجر لا يُحذف');
        $this->assertSame(50000, $vendor->wallet()->first()->balance);
        $this->assertSame(1, WithdrawalRequest::where('vendor_id', $vendor->id)->count());
    }

    public function test_admin_can_still_delete_seller_without_any_history(): void
    {
        [$user, $vendor] = $this->seller();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('admin.sellers.destroy', $vendor))->assertSessionHas('success');

        $this->assertNull($vendor->fresh());
    }

    // ------------------------------------------------------------ المخزون

    public function test_customer_cancel_returns_variant_stock_too(): void
    {
        [, $vendor] = $this->seller();
        $product = $this->product($vendor, 8);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'أحمر', 'size' => 'M', 'stock_quantity' => 3, 'is_active' => true]);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer, [[$product, 2, $variant]]);

        $this->actingAs($customer)->post(route('customer.orders.cancel', $order));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(5, $variant->fresh()->stock_quantity, 'كمية اللون/المقاس الملغى ترجع للمخزون');
    }

    public function test_admin_cancel_returns_stock(): void
    {
        [, $vendor] = $this->seller();
        $product = $this->product($vendor, 8);
        $order = $this->order(User::factory()->create(), [[$product, 2, null]], 'processing');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'cancelled']);
        $this->actingAs($admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'cancelled']); // تكرار

        $this->assertSame(10, $product->fresh()->stock_quantity, 'إلغاء الإدارة يرجع الكمية مرة واحدة فقط');
    }

    public function test_cancelled_order_cannot_be_reopened(): void
    {
        [, $vendor] = $this->seller();
        $product = $this->product($vendor, 10);
        $order = $this->order(User::factory()->create(), [[$product, 2, null]], 'cancelled');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.orders.updateStatus', $order), ['status' => 'processing']);

        $this->assertSame('cancelled', $order->fresh()->status, 'الكمية رجعت للمخزون، فلا يُعاد فتح الطلب');
    }

    public function test_cancel_after_moving_delivered_order_backwards_still_reverses_earnings(): void
    {
        [, $vendor] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null]], 'shipped');
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['delivered', 'processing', 'cancelled'] as $status) {
            $this->actingAs($admin)->put(route('admin.orders.updateStatus', $order), ['status' => $status]);
        }

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $vendor->wallet()->first()->balance, 'بضاعة رجعت للمخزون = لا تبقى أرباحها للبائع');
    }

    public function test_customer_double_cancel_returns_stock_once(): void
    {
        [, $vendor] = $this->seller();
        $product = $this->product($vendor, 8);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer, [[$product, 2, null]]);

        $this->actingAs($customer)->post(route('customer.orders.cancel', $order));
        $this->actingAs($customer)->post(route('customer.orders.cancel', $order))->assertStatus(409);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_api_admin_cancel_returns_stock(): void
    {
        [, $vendor] = $this->seller();
        $product = $this->product($vendor, 8);
        $order = $this->order(User::factory()->create(), [[$product, 2, null]], 'processing');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }

    public function test_seller_cancel_before_shipping_returns_product_and_variant_stock(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 8);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color' => 'أحمر', 'size' => 'M', 'stock_quantity' => 1, 'is_active' => true]);
        $order = $this->order(User::factory()->create(), [[$product, 2, $variant]], 'processing');

        $this->actingAs($user)->post(route('seller.orders.updateStatus', $order), ['status' => 'cancelled']);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
    }

    public function test_api_seller_gets_422_with_message_for_shared_order(): void
    {
        [$user, $vendor] = $this->seller();
        [, $other] = $this->seller();
        $order = $this->order(User::factory()->create(), [[$this->product($vendor), 1, null], [$this->product($other), 1, null]]);

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/seller/orders/{$order->id}/status", ['status' => 'shipped'])
            ->assertStatus(422)->assertJsonPath('message', 'هذا الطلب مشترك مع متاجر أخرى، وتدير الإدارة حالته.');
    }

    public function test_api_seller_without_store_gets_403_not_500(): void
    {
        $user = User::factory()->create(['role' => 'seller']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/seller/orders')->assertForbidden()->assertJsonPath('code', 'no_store');
    }

    public function test_inventory_log_matches_stock_after_creating_product_with_variants(): void
    {
        [$user, $vendor] = $this->seller();

        $this->actingAs($user)->post(route('seller.products.store'), [
            'category_id' => $this->category->id, 'name' => 'جديد', 'sku' => 'NEW-1', 'price' => 50, 'compare_at_price' => '',
            'stock_quantity' => 99, 'status' => 'active',
            'variant_color' => ['أحمر', 'أزرق'], 'variant_size' => ['M', 'L'], 'variant_stock_quantity' => [2, 3],
        ]);

        $product = Product::where('sku', 'NEW-1')->firstOrFail();
        $this->assertSame(5, $product->stock_quantity);
        $this->assertSame(5, (int) $product->inventoryMovements()->sum('quantity_change'), 'سجل حركات المخزون يطابق الرصيد الفعلي');
    }

    // ------------------------------------------------------------ الألوان والمقاسات

    private function productForm(Product $product, array $variants, array $extra = []): array
    {
        return [
            'category_id' => $this->category->id, 'name' => 'p', 'sku' => $product->sku, 'price' => 100, 'compare_at_price' => '',
            'stock_quantity' => $product->stock_quantity, 'status' => 'active', 'replace_variants' => '1',
            'variant_color' => array_column($variants, 0), 'variant_size' => array_column($variants, 1),
            'variant_stock_quantity' => array_column($variants, 2),
        ] + $extra;
    }

    public function test_editing_product_keeps_variant_ids_so_carts_keep_chosen_color_and_size(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $red = ProductVariant::create(['product_id' => $product->id, 'color' => 'أحمر', 'size' => 'M', 'stock_quantity' => 5, 'is_active' => true]);
        $cart = CartItem::create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id, 'variant_id' => $red->id, 'quantity' => 1, 'unit_price' => 10000]);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->productForm($product, [['أحمر', 'M', 4]]));

        $this->assertNotNull($red->fresh(), 'تعديل المنتج لا يحذف اللون/المقاس ويعيد إنشاءه');
        $this->assertSame(4, $red->fresh()->stock_quantity);
        $this->assertSame($red->id, $cart->fresh()->variant_id);
    }

    public function test_removed_variant_is_removed_from_carts_instead_of_silently_becoming_base_product(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $red = ProductVariant::create(['product_id' => $product->id, 'color' => 'أحمر', 'size' => 'M', 'stock_quantity' => 2, 'is_active' => true]);
        ProductVariant::create(['product_id' => $product->id, 'color' => 'أزرق', 'size' => 'M', 'stock_quantity' => 3, 'is_active' => true]);
        $cart = CartItem::create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id, 'variant_id' => $red->id, 'quantity' => 1, 'unit_price' => 10000]);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->productForm($product, [['أزرق', 'M', 3]]));

        $this->assertNull($red->fresh());
        $this->assertNull($cart->fresh(), 'السلة لا تشتري "المنتج بلا لون" بدل اللون المحذوف');
    }

    public function test_api_product_edit_keeps_variant_ids_and_syncs_stock(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 50);
        $red = ProductVariant::create(['product_id' => $product->id, 'color' => 'أحمر', 'size' => 'M', 'stock_quantity' => 5, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/seller/products/{$product->id}", $this->productForm($product, [['أحمر', 'M', 6], ['أزرق', 'L', 1]]))
            ->assertOk();

        $this->assertSame(6, $red->fresh()?->stock_quantity);
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    public function test_quick_stock_is_refused_for_product_with_variants(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        ProductVariant::create(['product_id' => $product->id, 'color' => 'أحمر', 'size' => 'M', 'stock_quantity' => 5, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')->putJson("/api/v1/seller/products/{$product->id}/quick-stock", ['change' => 10])
            ->assertStatus(422);

        $this->assertSame(5, $product->fresh()->stock_quantity, 'الكمية تُعدَّل لكل لون/مقاس، لا للمنتج مباشرة');
    }

    public function test_product_stock_equals_sum_of_variants(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 99);

        $this->actingAs($user)->put(route('seller.products.update', $product),
            $this->productForm($product, [['أحمر', 'M', 2], ['أزرق', 'L', 3]]));

        $this->assertSame(5, $product->fresh()->stock_quantity, 'مخزون المنتج = مجموع ألوانه ومقاساته');
    }
}
