<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Shipment;
use App\Returns\ReturnService;
use App\Returns\ReturnSettings;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * من الاختبار الشامل قبل الإطلاق:
 * - طلب الدفع عند الاستلام يصير «مدفوعاً» عند التسليم (المندوب قبض المبلغ).
 * - أرباح البائع تدخل رصيده عند التسليم لكنها لا تُسحب حتى تنتهي مدة الإرجاع.
 */
class DeliveryPaymentAndEarningsHoldTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox']);
    }

    /** @param  array<string, mixed>  $tenant */
    private function deliveredCashOrder(array $tenant): Order
    {
        CartItem::firstOrCreate(
            ['user_id' => $tenant['customer']->id, 'product_id' => $tenant['product']->id],
            ['quantity' => 1, 'unit_price' => $tenant['product']->price, 'original_unit_price' => $tenant['product']->price],
        );
        $order = Order::findOrFail($this->actingAs($tenant['customer'], 'sanctum')->postJson('/api/v1/checkout', [
            'shipping_name' => 'زبون', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق',
            'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ])->assertCreated()->json('data.id'));

        $this->assertNotSame('paid', $order->payment_status);
        $order->changeStatus('delivered');

        return $order->fresh();
    }

    public function test_a_cash_on_delivery_order_is_paid_once_delivered(): void
    {
        $order = $this->deliveredCashOrder($this->tenant('a'));

        $this->assertSame('paid', $order->payment_status);
        $cash = $order->payments()->where('method', 'cash_on_delivery')->sole();
        $this->assertSame('paid', $cash->status);
        $this->assertNotNull($cash->paid_at);
        $this->assertSame((int) $order->total, (int) $cash->amount, 'the collected amount is what the order totals now');
    }

    /** التوصيل الخاص بالدفع عند الاستلام: البائع قبض المال بيده، فلا يُحجز ربحه ولا يُجمَّد رصيده القديم. */
    public function test_self_delivered_cash_orders_are_not_held(): void
    {
        $a = $this->tenant('a');
        $a['withdrawal']->delete();
        $order = $this->deliveredCashOrder($a);
        Shipment::query()->where('order_id', $order->id)->update(['delivery_mode' => 'self', 'cod_amount' => $order->total]);

        $this->assertSame(0, app(PayoutService::class)->heldForReturns($a['store']));
        $this->assertNull(app(PayoutService::class)->nextRelease($a['store']));
    }

    public function test_earnings_stay_in_the_balance_but_are_not_withdrawable_until_the_return_window_ends(): void
    {
        $a = $this->tenant('a');
        $a['withdrawal']->delete(); // طلب سحب معلّق في البيانات الجاهزة يخفي الفرق
        $payouts = app(PayoutService::class);
        $before = $payouts->available($a['store']);

        $order = $this->deliveredCashOrder($a);
        $earning = (int) $order->items()->sum('vendor_earning');
        $this->assertGreaterThan(0, $earning);

        $this->assertSame($before, $payouts->available($a['store']), 'the new earning must not be withdrawable yet');
        $this->assertGreaterThanOrEqual($earning, $payouts->heldForReturns($a['store']));
        $this->assertNotNull($payouts->nextRelease($a['store']));

        $this->actingAs($a['seller'], 'sanctum')->getJson('/api/v1/seller/wallet')->assertOk()
            ->assertJsonPath('available', $before / 100)
            ->assertJsonPath('held_for_returns', $payouts->heldForReturns($a['store']) / 100);
        $this->actingAs($a['seller'])->get(route('seller.wallet.index'))->assertOk()
            ->assertSee('data-held-for-returns', false);

        $this->travel(app(ReturnSettings::class)->windowDays() + 1)->days();

        $this->assertSame(0, $payouts->heldForReturns($a['store']));
        $this->assertSame($before + $earning, $payouts->available($a['store']));
    }

    public function test_an_open_return_keeps_its_earnings_held_after_the_window_ends(): void
    {
        $a = $this->tenant('a');
        $a['withdrawal']->delete();
        $payouts = app(PayoutService::class);
        $before = $payouts->available($a['store']);

        $order = $this->deliveredCashOrder($a);
        $item = $order->items()->firstOrFail();
        $return = app(ReturnService::class)->create($a['customer'], $order, [$item->id => 1], 'damaged', 'وصل مكسوراً');

        $this->travel(app(ReturnSettings::class)->windowDays() + 1)->days();
        $this->assertSame($before, $payouts->available($a['store']), 'earnings under an open return must stay held');
        $this->assertNull($payouts->nextRelease($a['store']), 'an open return has no release date');

        $return->update(['status' => 'closed', 'closed_at' => now()]);
        $this->assertSame($before + (int) $item->vendor_earning, $payouts->available($a['store']));
    }
}
