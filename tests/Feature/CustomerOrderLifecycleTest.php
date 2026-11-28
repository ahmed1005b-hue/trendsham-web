<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderLifecycleTest extends TestCase
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

    /**
     * ينشئ زبوناً مع سلة، ينفذ الشراء، ويرجع [الزبون، المنتج، الطلب].
     */
    private function placeOrder(int $quantity = 2): array
    {
        $product = $this->makeLiveProduct();
        $customer = User::factory()->create(['role' => 'customer']);
        CartItem::create([
            'user_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        $this->actingAs($customer)->post('/customer/checkout', [
            'shipping_name' => 'زبون تجريبي',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'دمشق - شارع الاختبار',
            'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ]);

        return [$customer, $product, Order::firstOrFail()];
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        [$customer, $product, $order] = $this->placeOrder(quantity: 2);
        $stockAfterSale = $product->refresh()->stock_quantity; // 8

        $response = $this->from(route('customer.orders.show', $order))
            ->post(route('customer.orders.cancel', $order));

        $response->assertRedirect()
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);

        // الكمية رجعت للمخزون (8 + 2 = 10)
        $this->assertSame($stockAfterSale + 2, $product->refresh()->stock_quantity);

        // حركة مخزون "return" مسجلة للتدقيق
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'reason' => 'return',
            'quantity_change' => 2,
        ]);

        // الدفعة المعلقة صارت فاشلة
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'failed',
        ]);

        // الزبون استلم إشعار الإلغاء
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'order_cancelled',
        ]);
    }

    public function test_customer_cannot_cancel_processing_order(): void
    {
        [,$product, $order] = $this->placeOrder();
        $order->update(['status' => 'processing']);
        $stockBefore = $product->refresh()->stock_quantity;

        $this->post(route('customer.orders.cancel', $order))
            ->assertStatus(409);

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame($stockBefore, $product->refresh()->stock_quantity);
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        [,, $order] = $this->placeOrder();

        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($otherCustomer)
            ->post(route('customer.orders.cancel', $order))
            ->assertForbidden();

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_admin_marking_delivered_settles_commission_once(): void
    {
        [$customer, $product, $order] = $this->placeOrder(quantity: 1);

        // الستوري تيست غيّر نسبة العمولة العامة - نرجّعها 10% لهذا الاختبار
        PlatformSetting::query()->update(['commission_rate' => 10]);

        // لا عمولة قبل التسليم
        $itemBefore = $order->items()->firstOrFail();
        $this->assertNull($itemBefore->commission_settled_at);

        // التسليم أول مرة: تُسوّى العمولة وتودع أرباح البائع بمحفظته
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->put(route('admin.orders.updateStatus', $order), ['status' => 'delivered'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $item = $order->items()->firstOrFail()->refresh();
        $this->assertNotNull($item->commission_settled_at);
        $this->assertSame(1000, $item->commission_amount); // 10% من 10000
        $this->assertSame(9000, $item->vendor_earning);

        // المحفظة استلمت الأرباح مرة واحدة
        $wallet = $product->vendor->wallet()->firstOrFail();
        $this->assertSame(9000, $wallet->refresh()->balance);

        // إعادة تعيين الحالة إلى delivered مرة ثانية: لا تسوية مزدوجة
        $this->actingAs($admin)
            ->put(route('admin.orders.updateStatus', $order), ['status' => 'delivered']);

        $wallet->refresh();
        $this->assertSame(9000, $wallet->balance, 'العمولة تسوت مرتين!');
    }
}
