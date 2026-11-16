<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\DailyDeal;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\ShippingCompany;
use App\Models\ShowcaseSlot;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * واجهات التطبيق التي تقابل صفحات الويب الخاصة بالمدير + الفاتورة + الصورة الرئيسية للمنتج.
 */
class AdminMobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeProduct(): Product
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
            'price' => 10000,
            'stock_quantity' => 10,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);
    }

    /** @return array{User, Order} */
    private function placeOrder(): array
    {
        $product = $this->makeProduct();
        $customer = User::factory()->create(['role' => 'customer']);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($customer)->post('/customer/checkout', [
            'shipping_name' => 'زبون تجريبي',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'دمشق',
            'payment_method' => 'cash_on_delivery',
        ]);

        return [$customer, Order::firstOrFail()];
    }

    public function test_non_admin_cannot_use_admin_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        foreach (['/api/v1/admin/shipping-companies', '/api/v1/admin/daily-deals', '/api/v1/admin/showcases',
            '/api/v1/admin/reports', '/api/v1/admin/accounting'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_admin_manages_shipping_companies(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/v1/admin/shipping-companies', ['name' => 'شحن سريع', 'phone' => '011'])
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/shipping-companies/{$id}/toggle")
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/v1/admin/shipping-companies')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/admin/shipping-companies/{$id}")->assertOk();
        $this->assertDatabaseMissing('shipping_companies', ['id' => $id]);
    }

    public function test_admin_order_exposes_shipments_and_updates_shipping_and_payment(): void
    {
        [$customer, $order] = $this->placeOrder();
        $company = ShippingCompany::create(['name' => 'شحن', 'is_active' => true]);
        $shipment = $order->shipments()->firstOrFail();
        Sanctum::actingAs($this->admin());

        $this->getJson("/api/v1/admin/orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.shipments.0.id', $shipment->id);

        $this->putJson("/api/v1/admin/orders/{$order->id}/shipments/{$shipment->id}", [
            'shipping_company_id' => $company->id,
            'tracking_number' => 'TRK-1',
            'status' => 'shipped',
        ])->assertOk()
            ->assertJsonPath('data.shipments.0.tracking_number', 'TRK-1')
            ->assertJsonPath('data.shipments.0.shipping_company', 'شحن');
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->id, 'type' => 'shipment_updated']);

        $this->postJson("/api/v1/admin/orders/{$order->id}/payment/mark-paid", ['transaction_ref' => 'CASH-1'])
            ->assertOk()->assertJsonPath('data.payment_status', 'paid');
        // لا يمكن تأكيد نفس الدفعة مرتين
        $this->postJson("/api/v1/admin/orders/{$order->id}/payment/mark-failed")->assertStatus(409);
    }

    public function test_customer_order_api_shows_tracking(): void
    {
        [$customer, $order] = $this->placeOrder();
        $order->shipments()->firstOrFail()->update(['tracking_number' => 'TRK-9', 'status' => 'shipped']);
        Sanctum::actingAs($customer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()->assertJsonPath('data.shipments.0.tracking_number', 'TRK-9');
    }

    public function test_admin_manages_daily_deals_from_api_and_web(): void
    {
        $product = $this->makeProduct();
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/daily-deals/products')->assertOk()->assertJsonPath('data.0.id', $product->id);

        $deal = [
            'product_id' => $product->id,
            'discount_percentage' => 20,
            'starts_at' => now()->toDateTimeString(),
            'ends_at' => now()->addDay()->toDateTimeString(),
        ];
        $id = $this->postJson('/api/v1/admin/daily-deals', $deal)->assertCreated()->json('data.id');
        $this->putJson("/api/v1/admin/daily-deals/{$id}", ['discount_percentage' => 30] + $deal)
            ->assertOk()->assertJsonPath('data.discount_percentage', 30);
        $this->postJson("/api/v1/admin/daily-deals/{$id}/toggle")->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/admin/daily-deals/{$id}")->assertOk();
        $this->assertDatabaseMissing('daily_deals', ['id' => $id]);

        // صفحة الويب: كان ربط {daily_deal} بـ $deal معطلاً فيتم التعديل على سجل فارغ
        $webDeal = DailyDeal::create($deal);
        $this->actingAs($admin)->put(route('admin.daily-deals.update', $webDeal), ['discount_percentage' => 45] + $deal)
            ->assertRedirect(route('admin.daily-deals.index'));
        $this->assertSame(45.0, (float) $webDeal->fresh()->discount_percentage);
    }

    public function test_admin_manages_showcase_slots_and_lists_showcases(): void
    {
        Sanctum::actingAs($this->admin());

        $slot = [
            'key' => 'home_top', 'name' => 'أعلى الرئيسية', 'position' => 1, 'price_per_day' => 5000,
            'min_days' => 1, 'max_days' => 30, 'capacity' => 2, 'min_products' => 5, 'max_products' => 10,
            'is_active' => true,
        ];
        $id = $this->postJson('/api/v1/admin/showcase-slots', $slot)->assertCreated()->json('data.id');
        $this->putJson("/api/v1/admin/showcase-slots/{$id}", ['name' => 'معدّلة'] + $slot)
            ->assertOk()->assertJsonPath('data.name', 'معدّلة');
        $this->getJson('/api/v1/admin/showcase-slots')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/showcases')->assertOk()->assertJsonPath('data', []);
        $this->deleteJson("/api/v1/admin/showcase-slots/{$id}")->assertOk();
        $this->assertNull(ShowcaseSlot::find($id));
    }

    public function test_admin_reports_and_accounting(): void
    {
        $this->placeOrder();
        $seller = User::where('role', 'seller')->firstOrFail();
        app(LedgerService::class)->sale($seller->id, 150, 'بيع', 'manual_test', 1);
        Storage::fake('local');
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/admin/reports')->assertOk()->assertJsonStructure(['orders_count', 'total_sales', 'orders']);

        $this->getJson('/api/v1/admin/accounting')->assertOk()->assertJsonPath('totals.sales', 150);
        $this->getJson('/api/v1/admin/accounting/sellers')->assertOk()->assertJsonPath('data.0.balance', 150);
        $this->getJson("/api/v1/admin/accounting/sellers/{$seller->id}")
            ->assertOk()->assertJsonPath('entries.0.credit', 150);

        $this->postJson("/api/v1/admin/accounting/sellers/{$seller->id}/adjustments", [
            'direction' => 'debit', 'amount' => 50, 'reason' => 'تسوية تجريبية',
        ])->assertOk()->assertJsonPath('balance', 100);

        $statement = $this->postJson("/api/v1/admin/accounting/sellers/{$seller->id}/statements", [
            'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString(),
        ])->assertCreated()->json('data');
        $this->assertSame(100.0, (float) $statement['closing_balance']);
        $this->get("/api/v1/admin/accounting/statements/{$statement['id']}/download")->assertOk();
    }

    public function test_invoice_is_available_to_owner_only(): void
    {
        [$customer, $order] = $this->placeOrder();

        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));
        $this->getJson("/api/v1/orders/{$order->id}/invoice")->assertForbidden();

        Sanctum::actingAs($customer);
        $this->get("/api/v1/orders/{$order->id}/invoice")->assertOk();
    }

    public function test_seller_sets_primary_image(): void
    {
        $product = $this->makeProduct();
        $first = $product->images()->create(['path' => 'a.jpg', 'is_primary' => true, 'sort_order' => 0]);
        $second = $product->images()->create(['path' => 'b.jpg', 'is_primary' => false, 'sort_order' => 1]);

        Sanctum::actingAs(User::factory()->create(['role' => 'seller']));
        $this->postJson("/api/v1/seller/products/{$product->id}/images/{$second->id}/primary")->assertForbidden();

        Sanctum::actingAs($product->vendor->user);
        $this->postJson("/api/v1/seller/products/{$product->id}/images/{$second->id}/primary")->assertOk();
        $this->assertTrue((bool) $second->fresh()->is_primary);
        $this->assertFalse((bool) $first->fresh()->is_primary);
    }
}
