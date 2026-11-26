<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\SellerAccount;
use App\Models\SellerProfile;
use App\Models\StoreShippingRate;
use App\Models\StoreShippingSetting;
use App\Models\User;
use App\Shipping\Governorates;
use App\Shipping\SelfDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * شحن مستقل لكل متجر: المتجر يوصل بنفسه بأسعاره لكل محافظة (والباقي عبر شركة الشحن)،
 * أو يستلم الزبون من محله. ما يحصّله البائع نقداً يُخصم من محفظته فتبقى عليه العمولة فقط.
 */
class StoreShippingTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::current()->update([
            'shipping_parcel_fee' => 1300000, 'shipping_heavy_parcel_fee' => 2600000, 'shipping_home_extra' => 200000,
            'self_delivery_min_orders' => 0, 'seller_debt_limit' => 10000000, 'commission_rate' => 10,
        ]);
    }

    /** متجر يوصل بنفسه إلى دمشق بـ 5,000 ل.س. */
    private function selfDelivering(SellerProfile $store, array $setting = []): void
    {
        StoreShippingSetting::create(['seller_profile_id' => $store->id, 'mode' => 'self', 'prep_days' => 1, 'delivery_days' => 1] + $setting);
        StoreShippingRate::create(['seller_profile_id' => $store->id, 'governorate' => 'دمشق', 'fee' => 500000]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'shipping_name' => 'Sam', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق - المزة',
            'shipping_governorate' => 'دمشق', 'payment_method' => 'cash_on_delivery',
        ];
    }

    private function lastOrder(User $user): Order
    {
        return Order::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_a_self_delivering_store_charges_its_own_rate_while_other_stores_use_the_company(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->selfDelivering($a['store']);
        CartItem::create(['user_id' => $a['customer']->id, 'product_id' => $b['product']->id, 'quantity' => 1, 'unit_price' => $b['product']->price]);

        $this->actingAs($a['customer'])->get(route('customer.checkout.show', ['shipping_governorate' => 'دمشق']))->assertOk()
            ->assertSee('توصيل من المتجر نفسه')
            ->assertSee('عبر شركة الشحن')
            ->assertSee('يصل خلال 2 إلى 3 أيام')
            ->assertSee('يصل خلال 3 إلى 5 أيام');

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload())->assertRedirect();
        $order = $this->lastOrder($a['customer']);
        $parcels = $order->shipments()->get()->keyBy('seller_profile_id');

        $this->assertSame('دمشق', $order->shipping_governorate);
        $this->assertSame('self', $parcels[$a['store']->id]->delivery_mode);
        $this->assertSame(500000, (int) $parcels[$a['store']->id]->shipping_fee);
        $this->assertSame('platform', $parcels[$b['store']->id]->delivery_mode);
        $this->assertSame(1500000, (int) $parcels[$b['store']->id]->shipping_fee);
        $this->assertSame(500000 + 1500000, (int) $order->shipping_fee);
        // كل من يسلّم طرداً يحصّل ثمن بنوده وأجرة شحنه
        $this->assertSame(10000 + 500000, $parcels[$a['store']->id]->cod_amount);
        $this->assertSame(10000 + 1500000, $parcels[$b['store']->id]->cod_amount);
    }

    public function test_a_governorate_the_store_does_not_cover_ships_with_the_company(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload(['shipping_governorate' => 'حلب', 'shipping_address' => 'حلب']))->assertRedirect();
        $parcel = $this->lastOrder($a['customer'])->shipments()->firstOrFail();

        $this->assertSame('platform', $parcel->delivery_mode);
        $this->assertSame(1500000, (int) $parcel->shipping_fee);
    }

    public function test_the_governorate_is_guessed_from_the_address_when_not_chosen(): void
    {
        $this->assertSame('ريف دمشق', Governorates::guess('جرمانا - شارع الروضة'));
        $this->assertSame('دمشق', Governorates::guess('دمشق - المزة'));
        $this->assertSame('الحسكة', Governorates::guess('القامشلي'));
        $this->assertNull(Governorates::guess('عنوان غير معروف'));

        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);
        $payload = $this->payload();
        unset($payload['shipping_governorate']);
        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $payload)->assertRedirect();

        $this->assertSame('self', $this->lastOrder($a['customer'])->shipments()->value('delivery_mode'));
    }

    public function test_free_shipping_over_the_store_amount(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store'], ['free_over' => 20000]);
        $a['cartItem']->update(['quantity' => 2]); // 2 × 100 ل.س = الحد

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload())->assertRedirect();

        $this->assertSame(0, (int) $this->lastOrder($a['customer'])->shipments()->value('shipping_fee'));
    }

    public function test_pickup_from_the_store_is_free(): void
    {
        $a = $this->tenant('a');
        StoreShippingSetting::create(['seller_profile_id' => $a['store']->id, 'mode' => 'platform', 'allow_pickup' => true, 'pickup_address' => 'دمشق - الحمرا']);

        $this->actingAs($a['customer'])->get(route('customer.checkout.show', ['pickup_stores' => [$a['store']->id]]))->assertOk()
            ->assertSee('دمشق - الحمرا')
            ->assertSee('جاهز للاستلام خلال');

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload(['pickup_stores' => [$a['store']->id]]))->assertRedirect();
        $parcel = $this->lastOrder($a['customer'])->shipments()->firstOrFail();

        $this->assertSame('pickup', $parcel->delivery_mode);
        $this->assertSame(0, (int) $parcel->shipping_fee);
        $this->assertSame(10000, $parcel->cod_amount);
    }

    public function test_pickup_is_ignored_for_a_store_that_does_not_offer_it(): void
    {
        $a = $this->tenant('a');

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload(['pickup_stores' => [$a['store']->id]]))->assertRedirect();

        $this->assertSame('platform', $this->lastOrder($a['customer'])->shipments()->value('delivery_mode'));
    }

    public function test_cash_the_seller_collects_leaves_only_the_commission_as_a_debt_and_admin_records_payment(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);
        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload())->assertRedirect();
        $order = $this->lastOrder($a['customer']);

        $this->actingAs($a['seller'])->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('حصّل من الزبون عند التسليم')
            ->assertSee('5,100');
        $this->actingAs($a['seller'])->post(route('seller.orders.updateStatus', $order), ['status' => 'delivered'])->assertRedirect();

        // أرباح 9,000 + أجرة توصيل 500,000 − ما حصّله 510,000 = عمولة 1,000 ديناً عليه
        $store = $a['store']->fresh('wallet');
        $this->assertSame(-1000, (int) $store->wallet->balance);
        $this->assertSame(1000, SelfDelivery::debt($store));
        $this->assertSame(-10.0, (float) SellerAccount::where('seller_id', $a['seller']->id)->value('balance'));

        // التسليم مرتين لا يكرر القيود
        $this->actingAs($a['seller'])->post(route('seller.orders.updateStatus', $order), ['status' => 'delivered']);
        $this->assertSame(-1000, (int) $store->wallet->fresh()->balance);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.accounting.debts'))->assertOk()->assertSee('Store a');
        $this->actingAs($admin)->post(route('admin.accounting.debts.pay', $a['store']), ['amount' => 10, 'note' => 'نقداً'])->assertRedirect();

        $this->assertSame(0, (int) $store->wallet->fresh()->balance);
        $this->assertSame(0.0, (float) SellerAccount::where('seller_id', $a['seller']->id)->value('balance'));
    }

    public function test_a_prepaid_self_delivered_parcel_pays_the_seller_the_delivery_fee(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);
        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload(['payment_method' => 'card']))->assertRedirect();
        $order = $this->lastOrder($a['customer']);
        $this->assertSame(0, $order->shipments()->value('cod_amount'));
        // البنك أكّد الدفع: الطلب يصل للبائع الآن
        $order->payments()->update(['status' => 'paid']);
        $order->update(['payment_status' => 'paid']);

        $this->actingAs($a['seller'])->post(route('seller.orders.updateStatus', $order), ['status' => 'delivered'])->assertRedirect();

        $this->assertSame(9000 + 500000, (int) $a['store']->fresh('wallet')->wallet->balance);
    }

    public function test_self_delivery_waits_for_successful_orders_and_stops_at_the_debt_limit(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);
        $selfDelivery = fn () => (new SelfDelivery)->status($a['store']->fresh(['shippingSetting', 'wallet']));

        PlatformSetting::current()->update(['self_delivery_min_orders' => 5]);
        $this->assertFalse($selfDelivery()['active']);
        $this->assertStringContainsString('5', $selfDelivery()['reason']);

        PlatformSetting::current()->update(['self_delivery_min_orders' => 0]);
        $this->assertTrue($selfDelivery()['active']);

        $a['store']->walletOrCreate()->update(['balance' => -10000000]);
        $this->assertFalse($selfDelivery()['active']);
        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload())->assertRedirect();
        $this->assertSame('platform', $this->lastOrder($a['customer'])->shipments()->value('delivery_mode'));

        $a['store']->update(['identity_verified_at' => null]);
        $a['store']->walletOrCreate()->update(['balance' => 0]);
        $this->assertFalse($selfDelivery()['active']);
    }

    public function test_seller_saves_shipping_settings(): void
    {
        $a = $this->tenant('a');

        $this->actingAs($a['seller'])->get(route('seller.shipping.edit'))->assertOk()->assertSee('من يوصل طلباتك؟');

        $this->actingAs($a['seller'])->put(route('seller.shipping.update'), ['mode' => 'self', 'prep_days' => 1, 'delivery_days' => 2])
            ->assertSessionHasErrors('rates');

        $this->actingAs($a['seller'])->put(route('seller.shipping.update'), [
            'mode' => 'self', 'prep_days' => 0, 'delivery_days' => 1, 'free_over' => 150000,
            'rates' => ['دمشق' => ['enabled' => 1, 'fee' => 5000], 'حلب' => ['fee' => 9000], 'غير موجودة' => ['enabled' => 1, 'fee' => 1]],
            'allow_pickup' => 1, 'pickup_address' => 'دمشق - الحمرا',
        ])->assertRedirect(route('seller.shipping.edit'));

        $setting = $a['store']->shippingSetting()->first();
        $this->assertSame('self', $setting->mode);
        $this->assertSame(15000000, $setting->free_over);
        $this->assertTrue($setting->offersPickup());
        $this->assertSame(['دمشق' => 500000], $a['store']->shippingRates()->pluck('fee', 'governorate')->all());

        $this->actingAs($a['seller'])->put(route('seller.shipping.update'), ['mode' => 'platform', 'prep_days' => 1, 'delivery_days' => 2, 'allow_pickup' => 1])
            ->assertSessionHasErrors('pickup_address');
    }

    public function test_seller_reads_and_saves_shipping_settings_from_the_app(): void
    {
        $a = $this->tenant('a');
        $api = $this->actingAs($a['seller'], 'sanctum');

        $api->getJson('/api/v1/seller/shipping')->assertOk()
            ->assertJsonPath('mode', 'platform')
            ->assertJsonPath('allow_pickup', false)
            ->assertJsonCount(count(Governorates::ALL), 'rates')
            ->assertJsonPath('rates.0.enabled', false)
            ->assertJsonPath('status.debt', 0);

        $api->putJson('/api/v1/seller/shipping', ['mode' => 'self', 'prep_days' => 1, 'delivery_days' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('rates');

        $api->putJson('/api/v1/seller/shipping', [
            'mode' => 'self', 'prep_days' => 0, 'delivery_days' => 1, 'free_over' => 150000,
            'rates' => ['دمشق' => ['enabled' => true, 'fee' => 5000], 'حلب' => ['enabled' => false, 'fee' => 9000]],
            'allow_pickup' => true, 'pickup_address' => 'دمشق - الحمرا',
        ])->assertOk()
            ->assertJsonPath('mode', 'self')
            ->assertJsonPath('free_over', 150000)
            ->assertJsonPath('allow_pickup', true)
            ->assertJsonPath('pickup_address', 'دمشق - الحمرا');

        $this->assertSame(['دمشق' => 500000], $a['store']->shippingRates()->pluck('fee', 'governorate')->all());
        $damascus = collect($api->getJson('/api/v1/seller/shipping')->json('rates'))->firstWhere('governorate', 'دمشق');
        $this->assertTrue($damascus['enabled']);
        $this->assertEquals(5000, $damascus['fee']);

        $api->putJson('/api/v1/seller/shipping', ['mode' => 'platform', 'prep_days' => 1, 'delivery_days' => 2, 'allow_pickup' => true])
            ->assertStatus(422)->assertJsonValidationErrors('pickup_address');
    }

    public function test_the_app_shows_the_store_debt_and_why_self_delivery_is_paused(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);
        $a['store']->walletOrCreate()->update(['balance' => -2500000]);

        $this->actingAs($a['seller'], 'sanctum')->getJson('/api/v1/seller/shipping')->assertOk()
            ->assertJsonPath('mode', 'self')
            ->assertJsonPath('status.debt', 25000);
    }

    public function test_customers_cannot_open_store_shipping_settings_in_the_app(): void
    {
        $a = $this->tenant('a');

        $this->actingAs($a['customer'], 'sanctum')->getJson('/api/v1/seller/shipping')->assertForbidden();
    }

    public function test_the_app_gets_each_parcel_with_its_mode_and_arrival_time(): void
    {
        $a = $this->tenant('a');
        $this->selfDelivering($a['store']);

        $this->actingAs($a['customer'])->getJson('/api/v1/checkout/summary?shipping_governorate='.urlencode('دمشق'))->assertOk()
            ->assertJsonPath('shipping_governorate', 'دمشق')
            ->assertJsonPath('parcels.0.mode', 'self')
            ->assertJsonPath('parcels.0.fee', 5000)
            ->assertJsonPath('parcels.0.eta_min_days', 2);

        $this->actingAs($a['customer'])->postJson('/api/v1/checkout', $this->payload())->assertSuccessful();
        $this->assertSame('self', $this->lastOrder($a['customer'])->shipments()->value('delivery_mode'));
    }
}
