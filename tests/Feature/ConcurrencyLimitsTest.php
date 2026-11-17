<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Story;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** الحدود المحدودة (كمية عرض اليوم، رصيد المحفظة) لا تُتجاوز. */
class ConcurrencyLimitsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function payload(): array
    {
        return ['shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery'];
    }

    /** بند السلة بسعر العرض (أرخص من السعر الأصلي). */
    private function dealPricedCart(array $a, int $quantity): void
    {
        $a['cartItem']->update([
            'quantity' => $quantity,
            'unit_price' => $a['product']->price - 100,
            'original_unit_price' => $a['product']->price,
        ]);
    }

    public function test_deal_quantity_is_counted_and_never_oversold(): void
    {
        $a = $this->tenant('a');
        $a['deal']->update(['max_quantity' => 3, 'sold_quantity' => 2]);
        $this->dealPricedCart($a, 2);
        $ordersBefore = Order::count();

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())
            ->assertStatus(422)->assertJsonPath('code', 'deal_sold_out');
        $this->assertSame(2, $a['deal']->fresh()->sold_quantity);
        $this->assertSame($ordersBefore, Order::count());

        $this->dealPricedCart($a, 1);
        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertCreated();
        $this->assertSame(3, $a['deal']->fresh()->sold_quantity);
    }

    public function test_cancelling_an_order_returns_its_deal_units(): void
    {
        $a = $this->tenant('a');
        $a['deal']->update(['max_quantity' => 3, 'sold_quantity' => 0]);
        $this->dealPricedCart($a, 3);

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertCreated();
        $order = Order::where('user_id', $a['customer']->id)->latest('id')->firstOrFail();
        $this->assertSame(3, $a['deal']->fresh()->sold_quantity);
        $this->assertSame($a['deal']->id, $order->items()->first()->daily_deal_id);

        $order->changeStatus('cancelled');
        $order->changeStatus('cancelled');
        $this->assertSame(0, $a['deal']->fresh()->sold_quantity);
    }

    public function test_web_checkout_counts_deal_units_too(): void
    {
        $a = $this->tenant('a');
        $a['deal']->update(['max_quantity' => 5, 'sold_quantity' => 0]);
        $this->dealPricedCart($a, 2);

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload())->assertRedirect();
        $this->assertSame(2, $a['deal']->fresh()->sold_quantity);
    }

    public function test_regular_price_items_do_not_use_the_deal_quota(): void
    {
        $a = $this->tenant('a');
        $a['deal']->update(['max_quantity' => 1, 'sold_quantity' => 1]);
        // السعر الأصلي (أُضيف قبل العرض): لا يُحسب من كمية العرض ولا يُرفض
        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertCreated();
        $this->assertSame(1, $a['deal']->fresh()->sold_quantity);
    }

    public function test_wallet_debit_checks_balance_under_the_lock(): void
    {
        $a = $this->tenant('a');
        $wallet = Wallet::updateOrCreate(['vendor_id' => $a['store']->id], ['balance' => 1000]);

        $this->assertNotNull($wallet->debitIfAvailable(1000, 'story_fee'));
        // الطلب «المتزامن» الثاني رأى الرصيد قبل الخصم، لكنه يُفحص من جديد تحت القفل
        $this->assertNull($wallet->debitIfAvailable(1000, 'story_fee'));
        $this->assertSame(0, $wallet->fresh()->balance);
    }

    public function test_story_is_not_published_when_balance_ran_out_meanwhile(): void
    {
        Storage::fake('public');
        $a = $this->tenant('a');
        $a['store']->update(['status' => 'approved']);
        PlatformSetting::updateOrCreate([], ['story_fee' => 1000, 'commission_rate' => 5]);
        $wallet = Wallet::updateOrCreate(['vendor_id' => $a['store']->id], ['balance' => 1000]);
        $storiesBefore = Story::count();

        $this->actingAs($a['seller'], 'sanctum')->postJson('/api/v1/seller/stories', ['image' => UploadedFile::fake()->image('a.jpg', 300, 600)])
            ->assertCreated();
        $this->actingAs($a['seller'], 'sanctum')->postJson('/api/v1/seller/stories', ['image' => UploadedFile::fake()->image('b.jpg', 300, 600)])
            ->assertStatus(422);

        $this->assertSame($storiesBefore + 1, Story::count());
        $this->assertSame(0, $wallet->fresh()->balance);
    }
}
