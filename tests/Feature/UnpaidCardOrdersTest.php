<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Contracts\WebhookEvent;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * طلب البطاقة لا يصل للبائع قبل تأكيد البنك، ويُلغى تلقائياً إن لم يُدفع خلال المهلة.
 * الدفع عند الاستلام يصل للبائع فوراً.
 */
class UnpaidCardOrdersTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox']);
    }

    /** @param  array<string, mixed>  $tenant */
    private function checkout(array $tenant, string $method): Order
    {
        CartItem::firstOrCreate(
            ['user_id' => $tenant['customer']->id, 'product_id' => $tenant['product']->id],
            ['quantity' => 1, 'unit_price' => $tenant['product']->price, 'original_unit_price' => $tenant['product']->price],
        );

        $orderId = $this->actingAs($tenant['customer'], 'sanctum')->postJson('/api/v1/checkout', [
            'shipping_name' => 'زبون', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق',
            'payment_method' => $method, 'cod_confirmed' => true,
        ])->assertCreated()->json('data.id');

        return Order::findOrFail($orderId);
    }

    /** يبدأ العميل الدفع من التطبيق (صفحة البنك) ويعيد مرجع المحاولة. */
    private function startPayment(User $customer, Order $order): string
    {
        return $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/payments/start', ['order_id' => $order->id])
            ->assertOk()->json('reference');
    }

    private function bankConfirms(string $reference): void
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        app(PaymentService::class)->apply($reference, new WebhookEvent($reference, 'paid', 'TXN-'.$payment->id, (float) $payment->amount));
    }

    /** @return list<int> */
    private function sellerOrderIds(User $seller): array
    {
        return collect($this->actingAs($seller, 'sanctum')->getJson('/api/v1/seller/orders')->assertOk()->json('data'))
            ->pluck('order_id')->unique()->values()->all();
    }

    private function newOrderNotices(User $seller, Order $order): int
    {
        return AppNotification::where('user_id', $seller->id)->where('type', 'seller_new_order')
            ->where('title', 'like', "%{$order->order_number}%")->count();
    }

    public function test_unpaid_card_order_reaches_the_seller_only_after_the_bank_confirms(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');

        $this->assertNotContains($order->id, $this->sellerOrderIds($a['seller']));
        $this->actingAs($a['seller'], 'sanctum')->getJson("/api/v1/seller/orders/{$order->id}")->assertForbidden();
        $this->actingAs($a['seller'], 'sanctum')->putJson("/api/v1/seller/orders/{$order->id}/status", ['status' => 'processing'])->assertForbidden();
        $this->assertSame(0, $this->newOrderNotices($a['seller'], $order));
        $this->assertSame('pending', $order->fresh()->shipments()->value('status'));

        $this->bankConfirms($this->startPayment($a['customer'], $order));

        $this->assertContains($order->id, $this->sellerOrderIds($a['seller']));
        $this->actingAs($a['seller'], 'sanctum')->getJson("/api/v1/seller/orders/{$order->id}")->assertOk();
        $this->assertSame(1, $this->newOrderNotices($a['seller'], $order));
    }

    public function test_cash_on_delivery_order_reaches_the_seller_at_once(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'cash_on_delivery');

        $this->assertContains($order->id, $this->sellerOrderIds($a['seller']));
        $this->assertSame(1, $this->newOrderNotices($a['seller'], $order));
    }

    public function test_seller_website_hides_unpaid_card_orders(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');

        $this->actingAs($a['seller'])->get(route('seller.orders.index'))->assertOk()->assertDontSee($order->order_number);
        $this->actingAs($a['seller'])->get(route('seller.orders.show', $order))->assertForbidden();
        $this->actingAs($a['seller'])->post(route('seller.orders.updateStatus', $order), ['status' => 'processing'])->assertForbidden();
        $this->assertSame('pending', $order->fresh()->shipments()->value('status'));
    }

    public function test_unpaid_card_order_is_cancelled_after_the_timeout_and_its_stock_returns(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');
        $this->assertSame(9, $a['product']->fresh()->stock_quantity);

        $this->travel(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES - 1)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->status);

        $this->travel(2)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('cancelled', $order->payment_status);
        $this->assertSame(10, $a['product']->fresh()->stock_quantity);
        $this->assertTrue(AppNotification::where('user_id', $a['customer']->id)->where('type', 'order_cancelled')->exists());
    }

    public function test_timeout_counts_from_the_latest_payment_attempt_and_spares_paid_and_cash_orders(): void
    {
        $a = $this->tenant('a');
        $inProgress = $this->checkout($a, 'card');
        $cash = $this->checkout($a, 'cash_on_delivery');
        $paid = $this->checkout($a, 'card');
        $this->bankConfirms($this->startPayment($a['customer'], $paid));

        // العميل فتح صفحة البنك قبل نهاية المهلة بقليل
        $this->travel(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES - 5)->minutes();
        $this->startPayment($a['customer'], $inProgress);
        $this->travel(10)->minutes();

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('pending', $inProgress->fresh()->status);
        $this->assertSame('pending', $cash->fresh()->status);
        $this->assertSame('pending', $paid->fresh()->status);
    }

    public function test_bank_confirmation_after_cancellation_keeps_the_order_cancelled_and_flags_a_refund(): void
    {
        $a = $this->tenant('a');
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->checkout($a, 'card');
        $reference = $this->startPayment($a['customer'], $order);

        $this->travel(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES + 1)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);

        $this->bankConfirms($reference);

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame('duplicate', Payment::where('reference', $reference)->value('status'));
        $this->assertTrue(AppNotification::where('user_id', $admin->id)->where('type', 'payment_duplicate')->exists());
        $this->assertNotContains($order->id, $this->sellerOrderIds($a['seller']));
    }

    public function test_an_unpaid_card_order_the_admin_moves_forward_reaches_the_seller(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');

        // الإدارة تأكدت من الدفع بنفسها ونقلت الطلب للتجهيز: البائع يحتاجه ليشحنه
        $order->changeStatus('processing');

        $this->assertContains($order->id, $this->sellerOrderIds($a['seller']));
    }

    public function test_the_manual_gateway_does_not_release_an_unpaid_card_order(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');

        config(['payment.driver' => 'manual']);
        $this->startPayment($a['customer'], $order);

        $this->assertTrue($order->isAwaitingOnlinePayment());
        $this->assertNotContains($order->id, $this->sellerOrderIds($a['seller']));
    }

    public function test_a_late_failure_notice_keeps_the_cancelled_payment_status(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');
        $reference = $this->startPayment($a['customer'], $order);
        $this->travel(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES + 1)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        app(PaymentService::class)->apply($reference, new WebhookEvent($reference, 'failed'));

        $this->assertSame('cancelled', $order->fresh()->payment_status);
    }

    public function test_the_website_pay_page_sends_an_expired_order_back_with_a_message(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');
        $this->travel(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES + 1)->minutes();

        $this->actingAs($a['customer'])->get(route('customer.checkout.pay', $order))
            ->assertRedirect(route('customer.orders.show', $order))->assertSessionHas('error');
        $this->actingAs($a['customer'])->post(route('customer.checkout.pay.process', $order))
            ->assertRedirect(route('customer.orders.show', $order));
    }

    public function test_payment_attempts_cannot_extend_the_stock_hold_past_the_window(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');

        $this->travel(Order::ONLINE_PAYMENT_TIMEOUT_MINUTES + 1)->minutes();

        $this->actingAs($a['customer'], 'sanctum')
            ->postJson('/api/v1/payments/start', ['order_id' => $order->id])
            ->assertUnprocessable();
        $this->assertSame(0, Payment::where('order_id', $order->id)->whereNotNull('reference')->count());
    }

    public function test_a_customer_cannot_hold_stock_with_many_unpaid_card_orders(): void
    {
        $a = $this->tenant('a');
        for ($i = 0; $i < Order::MAX_OPEN_CARD_ORDERS; $i++) {
            $this->checkout($a, 'card');
        }

        CartItem::firstOrCreate(
            ['user_id' => $a['customer']->id, 'product_id' => $a['product']->id],
            ['quantity' => 1, 'unit_price' => $a['product']->price, 'original_unit_price' => $a['product']->price],
        );
        $payload = ['shipping_name' => 'زبون', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق'];

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $payload + ['payment_method' => 'card'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        // الدفع عند الاستلام يبقى متاحاً
        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $payload + ['payment_method' => 'cash_on_delivery', 'cod_confirmed' => true])
            ->assertCreated();
    }

    public function test_unconnected_wallet_and_card_without_online_payment_are_refused(): void
    {
        $a = $this->tenant('a');
        $payload = ['shipping_name' => 'زبون', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق'];
        $ordersBefore = Order::count();

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $payload + ['payment_method' => 'wallet'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        config(['payment.driver' => 'manual']);
        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $payload + ['payment_method' => 'card'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->assertSame($ordersBefore, Order::count());
    }
}
