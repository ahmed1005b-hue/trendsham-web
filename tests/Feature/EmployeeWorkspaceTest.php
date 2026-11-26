<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\ChatConversation;
use App\Models\Order;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * لوحة الموظف (الموقع والتطبيق): شغل اليوم، وقواعد تغيير حالة الطلب، ودعم البائعين ومحادثات العملاء.
 */
class EmployeeWorkspaceTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox']);
        $this->employee = User::factory()->create(['role' => 'employee', 'employee_status' => 'approved', 'status' => 'active']);
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
            'payment_method' => $method,
        ])->assertCreated()->json('data.id');

        return Order::findOrFail($orderId);
    }

    /** @return array<string, int> */
    private function appCounts(): array
    {
        return $this->actingAs($this->employee, 'sanctum')->getJson('/api/v1/employee/dashboard')->assertOk()->json('data');
    }

    public function test_the_employee_cannot_mark_an_order_paid_by_hand(): void
    {
        $order = $this->checkout($this->tenant('a'), 'cash_on_delivery');

        $this->actingAs($this->employee, 'sanctum')->putJson("/api/v1/employee/orders/{$order->id}/status", ['status' => 'paid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($this->employee)->put(route('employee.orders.updateStatus', $order), ['status' => 'paid'])
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_employee_cannot_release_an_unpaid_card_order_but_can_cancel_it(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'card');

        $this->actingAs($this->employee, 'sanctum')->putJson("/api/v1/employee/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertUnprocessable();
        $this->actingAs($this->employee)->put(route('employee.orders.updateStatus', $order), ['status' => 'shipped'])
            ->assertSessionHas('error');
        $this->assertTrue($order->fresh()->isAwaitingOnlinePayment());

        $this->actingAs($this->employee, 'sanctum')->putJson("/api/v1/employee/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_the_app_and_website_tell_the_customer_the_new_status_in_words(): void
    {
        $a = $this->tenant('a');
        $order = $this->checkout($a, 'cash_on_delivery');

        $this->actingAs($this->employee, 'sanctum')->putJson("/api/v1/employee/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()->assertJsonPath('data.status', 'processing');

        $notice = AppNotification::where('user_id', $a['customer']->id)->where('type', 'order_status_changed')->latest('id')->firstOrFail();
        $this->assertStringContainsString(Order::STATUS_LABELS['processing'], $notice->body);
        $this->assertStringNotContainsString('processing', $notice->body);
    }

    public function test_dashboard_counts_released_orders_and_waiting_work_on_web_and_app(): void
    {
        $a = $this->tenant('a');
        $before = $this->appCounts();

        $this->checkout($a, 'cash_on_delivery');
        $this->checkout($a, 'card'); // غير مدفوع: ليس شغلاً بعد
        ChatConversation::create(['user_id' => $a['customer']->id, 'status' => 'escalated', 'escalated_at' => now()]);
        StaffMessage::create(['conversation_id' => $a['supportConversation']->id, 'sender_id' => $a['seller']->id, 'body' => 'مرحبا']);

        $after = $this->appCounts();
        $this->assertSame($before['new_orders'] + 1, $after['new_orders']);
        $this->assertSame($before['customer_chats'] + 1, $after['customer_chats']);
        $this->assertGreaterThanOrEqual(1, $after['seller_support']);
        $this->assertSame(
            ['new_orders', 'late_orders', 'pending_products', 'seller_support', 'customer_chats', 'returns'],
            array_keys($after),
        );

        $this->actingAs($this->employee)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('data-employee-tile="employee.assistant-chats.index"', false)
            ->assertSee('data-employee-tile="employee.returns.index"', false);
    }

    public function test_the_employee_answers_sellers_from_the_website_and_the_app(): void
    {
        $a = $this->tenant('a');
        $conversation = $a['supportConversation'];

        $this->actingAs($this->employee)->get(route('employee.support.index'))->assertOk()
            ->assertSee(route('employee.support.show', $conversation), false)
            ->assertDontSee(route('admin.dashboard'), false);
        $this->actingAs($this->employee)->get(route('employee.support.show', $conversation))->assertOk();
        $this->actingAs($this->employee)->post(route('employee.support.message', $conversation), ['body' => 'أهلاً، نتابع طلبك'])
            ->assertRedirect();

        $this->actingAs($this->employee, 'sanctum')->getJson('/api/v1/employee/support')->assertOk()
            ->assertJsonFragment(['id' => $conversation->id]);
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/support/{$conversation->id}/message", ['body' => 'تم الحل'])
            ->assertOk();
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/support/{$conversation->id}/close")->assertOk();

        $this->assertSame(2, StaffMessage::where('conversation_id', $conversation->id)->where('sender_id', $this->employee->id)->count());
        $this->assertSame('closed', $conversation->fresh()->status);
    }

    public function test_the_employee_answers_customers_handed_over_by_the_assistant(): void
    {
        $a = $this->tenant('a');
        $chat = ChatConversation::create(['user_id' => $a['customer']->id, 'status' => 'escalated', 'escalated_at' => now()]);
        $chat->messages()->create(['role' => 'user', 'content' => 'أريد موظفاً']);

        $this->actingAs($this->employee)->get(route('employee.assistant-chats.index'))->assertOk()
            ->assertSee(route('employee.assistant-chats.show', $chat), false)
            ->assertDontSee(route('admin.assistant-faqs.index'), false);
        $this->actingAs($this->employee)->post(route('employee.assistant-chats.reply', $chat), ['message' => 'أنا معك'])->assertRedirect();

        $this->actingAs($this->employee, 'sanctum')->getJson("/api/v1/employee/assistant-chats/{$chat->id}")->assertOk();
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/assistant-chats/{$chat->id}/close")->assertOk();

        $this->assertSame($this->employee->id, $chat->fresh()->escalated_to);
        $this->assertSame('closed', $chat->fresh()->status);
    }

    public function test_the_employee_stays_out_of_money_and_settings(): void
    {
        $api = $this->actingAs($this->employee, 'sanctum');
        foreach (['/api/v1/admin/withdrawals', '/api/v1/admin/accounting', '/api/v1/admin/settings', '/api/v1/admin/assistant-faqs'] as $uri) {
            $api->getJson($uri)->assertForbidden();
        }
        $this->actingAs($this->employee)->get(route('admin.withdrawals.index'))->assertForbidden();
    }
}
