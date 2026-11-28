<?php

namespace Tests\Feature;

use App\GiftCards\Models\GiftCard;
use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\DailyDeal;
use App\Models\Order;
use App\Models\User;
use App\Payments\Contracts\WebhookEvent;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * نتائج فحص الدفع: السعر يُحسب عند الدفع لا عند الإضافة للسلة، وطلب الدفع عند الاستلام
 * لا يُدفع إلكترونياً فوقه، ودفعة بطاقة هدية متأخرة لا تضيع.
 */
class CheckoutPriceIntegrityTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private const PAYLOAD = ['shipping_name' => 'زبون', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true];

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox']);
    }

    /** @param  array<string, mixed>  $tenant */
    private function addToCart(array $tenant): void
    {
        CartItem::where('user_id', $tenant['customer']->id)->delete();
        $this->actingAs($tenant['customer'], 'sanctum')
            ->postJson('/api/v1/cart', ['product_id' => $tenant['product']->id, 'quantity' => 1])
            ->assertSuccessful();
    }

    public function test_an_ended_deal_price_is_not_charged_at_checkout(): void
    {
        $a = $this->tenant('a');
        DailyDeal::where('product_id', $a['product']->id)->delete();
        $deal = DailyDeal::create([
            'product_id' => $a['product']->id, 'discount_percentage' => 50, 'special_price' => null,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'max_quantity' => 0, 'is_active' => true,
        ]);
        $this->addToCart($a);
        $this->assertSame(5000, (int) CartItem::where('user_id', $a['customer']->id)->value('unit_price'));

        $deal->update(['ends_at' => now()->subMinute()]);

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', self::PAYLOAD)
            ->assertUnprocessable()->assertJsonPath('code', 'price_changed');
        $this->assertSame(10000, (int) CartItem::where('user_id', $a['customer']->id)->value('unit_price'));

        $orderId = $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', self::PAYLOAD)
            ->assertCreated()->json('data.id');
        $this->assertSame(10000, (int) Order::findOrFail($orderId)->subtotal);
    }

    public function test_a_price_the_seller_changed_after_adding_to_cart_is_shown_before_paying(): void
    {
        $a = $this->tenant('a');
        $this->addToCart($a);
        $a['product']->update(['price' => 15000]);

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), self::PAYLOAD)
            ->assertRedirect(route('customer.cart.index'));
        $this->assertSame(15000, (int) CartItem::where('user_id', $a['customer']->id)->value('unit_price'));
        $this->assertSame(1, Order::where('user_id', $a['customer']->id)->count()); // طلب الـ fixture فقط
    }

    public function test_a_cash_on_delivery_order_cannot_also_be_paid_online(): void
    {
        $a = $this->tenant('a');
        $this->addToCart($a);
        $orderId = $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', self::PAYLOAD)
            ->assertCreated()->json('data.id');

        $this->actingAs($a['customer'], 'sanctum')
            ->postJson('/api/v1/payments/start', ['order_id' => $orderId])
            ->assertUnprocessable();
    }

    public function test_a_gift_card_paid_after_it_timed_out_alerts_admins_to_refund(): void
    {
        $a = $this->tenant('a');
        $admin = User::factory()->create(['role' => 'admin']);
        $card = GiftCard::create([
            'purchaser_id' => $a['customer']->id, 'amount' => 500000, 'status' => GiftCard::FAILED,
            'reference' => (string) Str::uuid(), 'gateway' => 'sandbox',
        ]);

        app(PaymentService::class)->handle(new WebhookEvent($card->reference, 'paid', 'TXN-LATE', 500000));

        $this->assertSame(GiftCard::FAILED, $card->fresh()->status);
        $this->assertSame('TXN-LATE', $card->fresh()->gateway_txn_id);
        $this->assertTrue(AppNotification::where('user_id', $admin->id)->where('type', 'payment_duplicate')->exists());
    }
}
