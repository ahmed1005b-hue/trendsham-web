<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Contracts\WebhookEvent;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'TS-TEST-'.strtoupper(Str::random(6)),
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 10000,
            'shipping_fee' => 1500,
            'discount_amount' => 0,
            'total' => 11500,
            'shipping_name' => 'عميل',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'دمشق',
        ], $overrides));
    }

    public function test_start_payment_for_other_users_order_returns_404(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson('/api/v1/payments/start', ['order_id' => $order->id])
            ->assertNotFound();
    }

    public function test_start_payment_for_paid_order_returns_422(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user, ['payment_status' => 'paid']);
        Payment::create(['order_id' => $order->id, 'method' => 'cash_on_delivery', 'status' => 'paid', 'amount' => $order->total, 'reference' => (string) Str::uuid()]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/start', ['order_id' => $order->id])
            ->assertUnprocessable();
    }

    public function test_manual_start_returns_manual_type_and_creates_pending_payment(): void
    {
        config(['payment.driver' => 'manual', 'payment.currency' => 'SYP']);
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/payments/start', ['order_id' => $order->id])
            ->assertOk()
            ->assertJsonPath('type', 'manual')
            ->assertJsonStructure(['reference', 'type', 'url', 'message']);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'reference' => $res->json('reference'),
            'status' => 'pending',
            'gateway' => 'manual',
        ]);
    }

    public function test_webhook_with_bad_signature_returns_401_and_changes_nothing(): void
    {
        config(['payment.driver' => 'bank', 'payment.bank.webhook_secret' => 'secret123']);
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $p = Payment::create(['order_id' => $order->id, 'method' => 'card', 'gateway' => 'bank', 'status' => 'pending', 'amount' => $order->total, 'reference' => (string) Str::uuid(), 'currency' => 'SYP']);

        $this->postJson('/api/v1/payments/webhook/bank', ['reference' => $p->reference, 'status' => 'paid', 'amount' => $order->total], ['X-Signature' => 'bad'])
            ->assertUnauthorized();

        $p->refresh(); $order->refresh();
        $this->assertSame('pending', $p->status);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_valid_webhook_marks_payment_and_order_paid(): void
    {
        config(['payment.driver' => 'bank', 'payment.bank.webhook_secret' => 'secret123']);
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $p = Payment::create(['order_id' => $order->id, 'method' => 'card', 'gateway' => 'bank', 'status' => 'pending', 'amount' => $order->total, 'reference' => (string) Str::uuid(), 'currency' => 'SYP']);
        $body = json_encode(['reference' => $p->reference, 'status' => 'paid', 'transaction_id' => 'TX1', 'amount' => $order->total]);
        $sig = hash_hmac('sha256', $body, 'secret123');

        $this->call('POST', '/api/v1/payments/webhook/bank', [], [], [], [
            'HTTP_X_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $p->refresh(); $order->refresh();
        $this->assertSame('paid', $p->status);
        $this->assertSame('paid', $order->payment_status);
    }

    public function test_duplicate_webhook_does_not_change_paid_payment(): void
    {
        config(['payment.driver' => 'bank', 'payment.bank.webhook_secret' => 'secret123']);
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $p = Payment::create(['order_id' => $order->id, 'method' => 'card', 'gateway' => 'bank', 'status' => 'paid', 'amount' => $order->total, 'reference' => (string) Str::uuid(), 'currency' => 'SYP', 'paid_at' => now()->subMinute(), 'gateway_txn_id' => 'TX1']);
        $body = json_encode(['reference' => $p->reference, 'status' => 'paid', 'transaction_id' => 'TX2', 'amount' => $order->total]);
        $sig = hash_hmac('sha256', $body, 'secret123');

        $this->call('POST', '/api/v1/payments/webhook/bank', [], [], [], ['HTTP_X_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $p->refresh();
        $this->assertSame('TX1', $p->gateway_txn_id);
    }

    public function test_webhook_amount_mismatch_fails_payment_and_does_not_pay_order(): void
    {
        config(['payment.driver' => 'bank', 'payment.bank.webhook_secret' => 'secret123']);
        $user = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($user);
        $p = Payment::create(['order_id' => $order->id, 'method' => 'card', 'gateway' => 'bank', 'status' => 'pending', 'amount' => $order->total, 'reference' => (string) Str::uuid(), 'currency' => 'SYP']);
        $body = json_encode(['reference' => $p->reference, 'status' => 'paid', 'transaction_id' => 'TX1', 'amount' => 1]);
        $sig = hash_hmac('sha256', $body, 'secret123');

        $this->call('POST', '/api/v1/payments/webhook/bank', [], [], [], ['HTTP_X_SIGNATURE' => $sig, 'CONTENT_TYPE' => 'application/json'], $body)->assertOk();
        $p->refresh(); $order->refresh();
        $this->assertSame('failed', $p->status);
        $this->assertNotSame('paid', $order->payment_status);
    }

    public function test_payment_status_requires_owner(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($owner);

        $this->actingAs($other, 'sanctum')->getJson("/api/v1/orders/{$order->id}/payment-status")->assertForbidden();
        $this->actingAs($owner, 'sanctum')->getJson("/api/v1/orders/{$order->id}/payment-status")->assertOk()->assertJsonStructure(['status','paid']);
    }
}
