<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\ShippingCenter;
use App\Models\User;
use App\Shipping\ShippingDebt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** الشحن مع كرم: طرد لكل متجر، أجرة أعلى من 10 كغ، استلام من مركز، ودين رفض الاستلام. */
class ShippingTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::current()->update(['shipping_parcel_fee' => 1300000, 'shipping_heavy_parcel_fee' => 2600000, 'shipping_home_extra' => 200000]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['shipping_name' => 'Sam', 'shipping_phone' => '0999999999', 'shipping_address' => 'Damascus', 'payment_method' => 'cash_on_delivery'];
    }

    private function lastOrder(User $user): Order
    {
        return Order::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_centers_from_the_company_list_are_seeded(): void
    {
        $this->assertSame(72, ShippingCenter::count());
        $this->assertSame(71, ShippingCenter::active()->count());
        $this->assertFalse(ShippingCenter::where('name', 'اللاذقية طريق الحرش')->value('is_active'));
    }

    public function test_one_parcel_per_store_with_home_extra_and_heavy_fee(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $customer = $a['customer'];
        // منتج من متجر ثانٍ في نفس السلة، ووزن طرد المتجر الأول 12 كغ
        $a['product']->update(['weight_grams' => 4000]);
        $a['cartItem']->update(['quantity' => 3]);
        CartItem::create(['user_id' => $customer->id, 'product_id' => $b['product']->id, 'quantity' => 1, 'unit_price' => $b['product']->price]);

        $this->actingAs($customer)->get(route('customer.checkout.show'))->assertOk()
            ->assertSee('2 طرود', false)
            ->assertSee('طرد وزنه 10 كغ أو أكثر', false);

        $this->actingAs($customer)->post(route('customer.checkout.store'), $this->payload())->assertRedirect();

        $order = $this->lastOrder($customer);
        $this->assertSame('home', $order->delivery_method);
        $this->assertSame(2600000 + 1300000 + 2 * 200000, (int) $order->shipping_cost);
        $this->assertSame((int) $order->shipping_cost, (int) $order->shipping_fee);
    }

    public function test_center_pickup_needs_no_address_and_has_no_home_extra(): void
    {
        $a = $this->tenant('a');
        $center = ShippingCenter::active()->where('governorate', 'حلب')->firstOrFail();

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload([
            'shipping_address' => '', 'delivery_method' => 'center', 'pickup_center_id' => $center->id,
        ]))->assertSessionHasNoErrors();

        $order = $this->lastOrder($a['customer']);
        $this->assertSame('center', $order->delivery_method);
        $this->assertSame($center->id, $order->pickup_center_id);
        $this->assertSame(1300000, (int) $order->shipping_fee);
        $this->assertStringContainsString($center->name, $order->shipping_address);
    }

    public function test_center_pickup_requires_an_active_center(): void
    {
        $a = $this->tenant('a');
        $stopped = ShippingCenter::where('is_active', false)->firstOrFail();

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload(['delivery_method' => 'center']))
            ->assertSessionHasErrors('pickup_center_id');
        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload(['delivery_method' => 'center', 'pickup_center_id' => $stopped->id]))
            ->assertSessionHasErrors('pickup_center_id');
        $this->assertSame(1, Order::where('user_id', $a['customer']->id)->count()); // طلب المستأجر فقط
    }

    public function test_refusal_cancels_the_order_charges_the_next_one_and_blocks_cash_on_delivery(): void
    {
        $a = $this->tenant('a');
        $customer = $a['customer'];
        $this->actingAs($customer)->post(route('customer.checkout.store'), $this->payload())->assertRedirect();
        $refused = $this->lastOrder($customer);
        $refused->changeStatus('shipped');

        $added = app(ShippingDebt::class)->recordRefusal($refused);

        $this->assertSame(1500000, $added);
        $this->assertSame('cancelled', $refused->fresh()->status);
        $this->assertNotNull($refused->fresh()->refused_at);
        $customer->refresh();
        $this->assertSame(1500000, $customer->shipping_debt);
        $this->assertTrue($customer->cod_blocked_until->isAfter(now()->addDays(29)));
        $this->expectRefusalTwiceFails($refused);

        // الدفع عند الاستلام ممنوع شهراً
        CartItem::create(['user_id' => $customer->id, 'product_id' => $a['product']->id, 'quantity' => 1, 'unit_price' => $a['product']->price]);
        $this->actingAs($customer)->get(route('customer.checkout.show'))->assertOk()
            ->assertDontSee('value="cash_on_delivery"', false)
            ->assertSee('أجرة شحن طلب رفضت استلامه', false);
        $this->actingAs($customer)->post(route('customer.checkout.store'), $this->payload())->assertSessionHasErrors('payment_method');

        // الطلب التالي يحمل الدين، وإلغاؤه يعيد الدين على الزبون
        $this->actingAs($customer)->post(route('customer.checkout.store'), $this->payload(['payment_method' => 'wallet']))->assertRedirect();
        $next = $this->lastOrder($customer);
        $this->assertSame(1500000, (int) $next->shipping_debt_charged);
        $this->assertSame((int) $next->subtotal + (int) $next->shipping_fee + 1500000, (int) $next->total);
        $this->assertSame(0, $customer->fresh()->shipping_debt);

        $next->changeStatus('cancelled');
        $this->assertSame(1500000, $customer->fresh()->shipping_debt);
    }

    private function expectRefusalTwiceFails(Order $order): void
    {
        try {
            app(ShippingDebt::class)->recordRefusal($order);
            $this->fail('a refusal must be recorded once');
        } catch (\DomainException) {
            $this->assertSame(1500000, $order->user->fresh()->shipping_debt);
        }
    }

    public function test_admin_records_refusal_from_the_order_page(): void
    {
        $customer = $this->tenant('a')['customer'];
        $this->actingAs($customer)->post(route('customer.checkout.store'), $this->payload())->assertRedirect();
        $order = $this->lastOrder($customer);
        $order->changeStatus('shipped');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('الزبون رفض الاستلام');
        $this->actingAs($admin)->post(route('admin.orders.refused', $order))->assertSessionHas('success');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(1500000, $order->user->fresh()->shipping_debt);
        $this->actingAs($admin)->post(route('admin.orders.refused', $order))->assertSessionHas('error');
        $this->actingAs($this->tenant('b')['customer'])->post(route('admin.orders.refused', $order))->assertForbidden();
    }

    public function test_admin_sets_shipping_fees_in_lira(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'commission_rate' => 10, 'story_fee' => 5000,
            'shipping_parcel_fee' => 13000, 'shipping_heavy_parcel_fee' => 26000, 'shipping_home_extra' => 0,
        ])->assertSessionHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertSame([1300000, 2600000, 0], [(int) $settings->shipping_parcel_fee, (int) $settings->shipping_heavy_parcel_fee, (int) $settings->shipping_home_extra]);
    }

    public function test_seller_saves_product_weight_and_drop_off_center(): void
    {
        $a = $this->tenant('a');
        $center = ShippingCenter::active()->firstOrFail();
        $product = $a['product'];

        $this->actingAs($a['seller'])->put(route('seller.products.update', $product), [
            'category_id' => $product->category_id, 'name' => $product->name, 'sku' => $product->sku,
            'price' => $product->price / 100, 'stock_quantity' => $product->stock_quantity, 'status' => $product->status,
            'compare_at_price' => null, 'weight_kg' => '2.5',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2500, $product->fresh()->weight_grams);

        $this->actingAs($a['seller'])->put(route('seller.store.update'), [
            'store_name' => $a['store']->store_name, 'dropoff_center_id' => $center->id, 'courier_pickup' => '1',
        ])->assertSessionHasNoErrors();
        $store = $a['store']->fresh();
        $this->assertSame($center->id, $store->dropoff_center_id);
        $this->assertTrue($store->courier_pickup);
    }

    public function test_api_lists_centers_by_governorate(): void
    {
        $response = $this->getJson('/api/v1/shipping-centers')->assertOk();

        $this->assertCount(12, $response->json('data'));
        $this->assertSame(71, collect($response->json('data'))->sum(fn ($g) => count($g['centers'])));
        $this->getJson('/api/v1/shipping-centers', ['X-App-Locale' => 'en'])
            ->assertJsonFragment(['governorate_label' => 'Aleppo']);
    }

    public function test_api_summary_and_checkout_with_center_pickup(): void
    {
        $a = $this->tenant('a');
        $customer = $a['customer'];
        $center = ShippingCenter::active()->firstOrFail();

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/checkout/summary?delivery_method=center')->assertOk()
            ->assertJsonPath('delivery_method', 'center')
            ->assertJsonPath('shipping_fee', 13000)
            ->assertJsonPath('shipping_by_method.home', 15000)
            ->assertJsonPath('shipping_by_method.center', 13000)
            ->assertJsonPath('cod_blocked', false)
            ->assertJsonCount(1, 'parcels');

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/checkout', $this->payload([
            'shipping_address' => null, 'delivery_method' => 'center', 'pickup_center_id' => $center->id,
        ]))->assertCreated()
            ->assertJsonPath('data.delivery_method', 'center')
            ->assertJsonPath('data.pickup_center.id', $center->id)
            ->assertJsonPath('data.shipping_fee', 13000);
    }

    public function test_api_refuses_cash_on_delivery_while_blocked(): void
    {
        $customer = $this->tenant('a')['customer'];
        $customer->forceFill(['shipping_debt' => 1500000, 'cod_blocked_until' => now()->addDays(10)])->save();

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/checkout/summary')->assertOk()
            ->assertJsonPath('cod_blocked', true)
            ->assertJsonPath('shipping_debt', 15000);
        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/checkout', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    public function test_refusal_only_for_shipped_orders(): void
    {
        $order = $this->tenant('a')['order'];
        $order->update(['status' => 'processing']);

        $this->expectException(\DomainException::class);
        app(ShippingDebt::class)->recordRefusal($order);
    }
}
