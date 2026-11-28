<?php

namespace Tests\Feature;

use App\GiftCards\GiftCardService;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Shipment;
use App\Models\User;
use App\Services\SellerOrderService;
use App\Shipping\ShippingDebt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** طرد لكل متجر: كل بائع يدير طرده، والأرباح والإلغاء والرفض لكل طرد وحده. */
class StoreParcelsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private array $a;

    private array $b;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::current()->update(['shipping_parcel_fee' => 1000, 'shipping_heavy_parcel_fee' => 2000, 'shipping_home_extra' => 0]);
        $this->a = $this->tenant('a');
        $this->b = $this->tenant('b');
        $this->customer = $this->a['customer'];
    }

    /** سلة من متجرين: منتج A بـ 100 ل.س ومنتج B بـ 100 ل.س، وأجرة كل طرد 10 ل.س. */
    private function orderFromTwoStores(string $payment = 'cash_on_delivery'): Order
    {
        CartItem::create(['user_id' => $this->customer->id, 'product_id' => $this->b['product']->id, 'quantity' => 1,
            'unit_price' => $this->b['product']->price, 'original_unit_price' => $this->b['product']->price]);

        $this->actingAs($this->customer)->post(route('customer.checkout.store'), [
            'shipping_name' => 'Sam', 'shipping_phone' => '0999999999', 'shipping_address' => 'Aleppo', 'payment_method' => $payment, 'cod_confirmed' => true,
        ])->assertRedirect();

        return Order::where('user_id', $this->customer->id)->latest('id')->firstOrFail();
    }

    private function parcel(Order $order, array $tenant): Shipment
    {
        return $order->shipments()->where('seller_profile_id', $tenant['store']->id)->firstOrFail();
    }

    private function move(array $tenant, Order $order, string $to): void
    {
        app(SellerOrderService::class)->updateStatus($tenant['store'], $order, $to);
    }

    public function test_checkout_creates_one_parcel_per_store_with_its_fee(): void
    {
        $order = $this->orderFromTwoStores();

        $this->assertSame(2, $order->shipments()->count());
        $this->assertSame([1000, 1000], $order->shipments()->orderBy('id')->pluck('shipping_fee')->map(fn ($f) => (int) $f)->all());
        $this->assertSame(20000 + 2000, (int) $order->total);
    }

    public function test_each_seller_is_paid_when_his_own_parcel_is_delivered(): void
    {
        $order = $this->orderFromTwoStores();

        $this->move($this->a, $order, 'shipped');
        $this->assertSame('shipped', $order->fresh()->status);
        $this->move($this->a, $order, 'delivered');

        $items = $order->items()->get()->keyBy('vendor_id');
        $this->assertNotNull($items[$this->a['store']->id]->commission_settled_at, 'بائع A أخذ أرباحه');
        $this->assertNull($items[$this->b['store']->id]->commission_settled_at, 'بائع B لم يسلّم بعد');
        $this->assertSame('shipped', $order->fresh()->status, 'لم يكتمل الطلب');

        $this->move($this->b, $order, 'delivered');
        $this->assertNotNull($items[$this->b['store']->id]->fresh()->commission_settled_at);
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_customer_cancels_one_store_parcel_and_pays_only_for_the_rest(): void
    {
        $order = $this->orderFromTwoStores();
        $stockB = $this->b['product']->fresh()->stock_quantity;

        $this->actingAs($this->customer)->post(route('customer.orders.parcels.cancel', [$order, $this->parcel($order, $this->b)]))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('cancelled', $this->parcel($order, $this->b)->status);
        $this->assertSame('pending', $this->parcel($order, $this->a)->status);
        $this->assertSame('pending', $order->status, 'بقية الطلب مستمرة');
        $this->assertSame(10000 + 1000, (int) $order->total, 'يدفع عند الاستلام ثمن طرد A وشحنه فقط');
        $this->assertSame($stockB + 1, $this->b['product']->fresh()->stock_quantity);
    }

    public function test_customer_cannot_cancel_a_parcel_already_being_prepared(): void
    {
        $order = $this->orderFromTwoStores();
        $this->move($this->b, $order, 'processing');

        $this->actingAs($this->customer)->post(route('customer.orders.parcels.cancel', [$order, $this->parcel($order, $this->b)]))
            ->assertStatus(409);
        $this->assertSame('processing', $this->parcel($order, $this->b)->status);
    }

    public function test_paid_order_gets_the_cancelled_parcel_back_as_gift_balance(): void
    {
        $order = $this->orderFromTwoStores();
        $order->update(['payment_status' => 'paid', 'status' => 'paid']);

        $this->move($this->b, $order, 'cancelled');

        $this->assertSame(10000 + 1000, app(GiftCardService::class)->balance($this->customer->id));
        $this->assertSame(11000, (int) $this->parcel($order, $this->b)->refund_amount);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_cancelling_the_last_open_parcel_cancels_the_whole_order(): void
    {
        $order = $this->orderFromTwoStores();

        $this->move($this->a, $order, 'cancelled');
        $this->move($this->b, $order, 'cancelled');

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $this->a['product']->fresh()->stock_quantity);
        $this->assertSame(10, $this->b['product']->fresh()->stock_quantity);
    }

    public function test_refusing_one_parcel_charges_only_its_shipping_and_the_other_continues(): void
    {
        $order = $this->orderFromTwoStores();
        $this->move($this->a, $order, 'shipped');
        $this->move($this->b, $order, 'shipped');

        $debt = app(ShippingDebt::class)->recordRefusal($this->parcel($order, $this->b));

        $this->assertSame(1000, $debt);
        $this->assertSame(1000, $this->customer->fresh()->shipping_debt);
        $this->assertSame('shipped', $this->parcel($order, $this->a)->status);
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertSame(10000 + 1000, (int) $order->fresh()->total);
    }

    public function test_order_pages_show_each_parcel(): void
    {
        $order = $this->orderFromTwoStores();
        $this->move($this->a, $order, 'shipped');

        $this->actingAs($this->customer)->get(route('customer.orders.show', $order))->assertOk()
            ->assertSee('شُحن جزئياً')
            ->assertSee($this->a['store']->store_name)
            ->assertSee($this->b['store']->store_name)
            ->assertSee('إلغاء هذا الطرد');
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders/'.$order->id)->assertOk()
            ->assertJsonCount(2, 'data.shipments')
            ->assertJsonPath('data.shipments.0.status', 'shipped');
        $this->actingAs($this->a['seller'])->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('أنت تدير طردك فقط');
    }
}
