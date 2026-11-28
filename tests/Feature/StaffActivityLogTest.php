<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\ChatConversation;
use App\Models\Order;
use App\Models\StaffActivity;
use App\Models\User;
use App\Services\StaffActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * سجل نشاط الفريق: كل عمل للموظف أو المدير في الطلبات والمنتجات والدعم والمرتجعات يُسجَّل،
 * ويراه المدير وحده (الموقع والتطبيق) مع التصفية بالشخص ونوع العمل والتاريخ.
 */
class StaffActivityLogTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private User $employee;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox']);
        $this->employee = User::factory()->create(['role' => 'employee', 'employee_status' => 'approved', 'status' => 'active', 'first_name' => 'سامر', 'last_name' => 'الموظف']);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    /** @param  array<string, mixed>  $tenant */
    private function cashOrder(array $tenant): Order
    {
        CartItem::firstOrCreate(
            ['user_id' => $tenant['customer']->id, 'product_id' => $tenant['product']->id],
            ['quantity' => 1, 'unit_price' => $tenant['product']->price, 'original_unit_price' => $tenant['product']->price],
        );

        return Order::findOrFail($this->actingAs($tenant['customer'], 'sanctum')->postJson('/api/v1/checkout', [
            'shipping_name' => 'زبون', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق',
            'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true,
        ])->assertCreated()->json('data.id'));
    }

    public function test_an_order_status_change_is_logged_with_who_what_and_when(): void
    {
        $order = $this->cashOrder($this->tenant('a'));

        $this->actingAs($this->employee, 'sanctum')
            ->putJson("/api/v1/employee/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();

        $activity = StaffActivity::sole();
        $this->assertSame($this->employee->id, $activity->user_id);
        $this->assertSame('employee', $activity->actor_role);
        $this->assertSame('order.status', $activity->action);
        $this->assertSame($order->id, $activity->subject_id);
        $this->assertSame(
            "غيّر حالة الطلب {$order->order_number} من «قيد المراجعة» إلى «قيد التجهيز»",
            $activity->description(),
        );
        $this->assertSame(route('admin.orders.show', $order), $activity->subjectUrl());
    }

    public function test_support_chats_products_and_returns_are_logged_from_web_and_app(): void
    {
        $a = $this->tenant('a');
        $chat = ChatConversation::create(['user_id' => $a['customer']->id, 'status' => 'escalated', 'escalated_at' => now()]);

        $this->actingAs($this->employee)->post(route('employee.support.message', $a['supportConversation']), ['body' => 'نتابع']);
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/support/{$a['supportConversation']->id}/close")->assertOk();
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/assistant-chats/{$chat->id}/reply", ['message' => 'أنا معك'])->assertOk();
        $this->actingAs($this->employee)->post(route('employee.products.reject', $a['product']), ['rejection_note' => 'الصور غير واضحة']);
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/products/{$a['product']->id}/approve")->assertOk();

        $this->assertSame(
            ['support.reply', 'support.close', 'chat.reply', 'product.reject', 'product.approve'],
            StaffActivity::orderBy('id')->pluck('action')->all(),
        );
        $this->assertStringContainsString('الصور غير واضحة', StaffActivity::where('action', 'product.reject')->sole()->description());
    }

    public function test_the_admin_reads_and_filters_the_log_on_web_and_app(): void
    {
        $a = $this->tenant('a');
        $order = $this->cashOrder($a);
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/orders/{$order->id}/archive")->assertOk();
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/products/{$a['product']->id}/approve")->assertOk();

        $this->actingAs($this->admin)->get(route('admin.staff-activity.index'))->assertOk()
            ->assertSee("أرشف الطلب {$order->order_number}")
            ->assertSee('سامر الموظف');
        $this->actingAs($this->admin)->get(route('admin.staff-activity.index', ['group' => 'product']))->assertOk()
            ->assertDontSee("أرشف الطلب {$order->order_number}");

        $api = $this->actingAs($this->admin, 'sanctum');
        $api->getJson('/api/v1/admin/staff-activity?user_id='.$this->employee->id)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'product.approve')
            ->assertJsonPath('data.0.actor_name', 'سامر الموظف')
            ->assertJsonFragment(['id' => $this->employee->id, 'role' => 'employee']);
        $api->getJson('/api/v1/admin/staff-activity?group=order')->assertOk()->assertJsonCount(1, 'data');
        $api->getJson('/api/v1/admin/staff-activity?from='.now()->addDay()->toDateString())->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_the_admin_sees_the_log_and_sellers_are_not_logged(): void
    {
        $a = $this->tenant('a');

        $this->actingAs($this->employee)->get(route('admin.staff-activity.index'))->assertForbidden();
        $this->actingAs($this->employee, 'sanctum')->getJson('/api/v1/admin/staff-activity')->assertForbidden();
        $this->actingAs($a['seller'], 'sanctum')->getJson('/api/v1/admin/staff-activity')->assertForbidden();

        app(StaffActivityLog::class)->record($a['seller'], 'order.archive', $a['order']);
        $this->assertSame(0, StaffActivity::count());
    }

    public function test_the_log_keeps_the_name_after_the_account_is_deleted(): void
    {
        $order = $this->cashOrder($this->tenant('a'));
        $this->actingAs($this->employee, 'sanctum')->postJson("/api/v1/employee/orders/{$order->id}/archive")->assertOk();

        $this->employee->forceDelete();

        $activity = StaffActivity::sole();
        $this->assertNull($activity->user_id);
        $this->assertSame('سامر الموظف', $activity->actor_name);
    }

    public function test_an_admin_status_change_from_the_app_is_logged_too(): void
    {
        $order = $this->cashOrder($this->tenant('a'));

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();

        $activity = StaffActivity::sole();
        $this->assertSame('admin', $activity->actor_role);
        $this->assertSame('order.status', $activity->action);
    }

    public function test_repeating_a_close_or_archive_is_logged_once(): void
    {
        $a = $this->tenant('a');
        $order = $this->cashOrder($a);
        $chat = ChatConversation::create(['user_id' => $a['customer']->id, 'status' => 'escalated', 'escalated_at' => now()]);
        $api = $this->actingAs($this->employee, 'sanctum');

        foreach ([1, 2] as $attempt) {
            $api->postJson("/api/v1/employee/orders/{$order->id}/archive")->assertOk();
            $api->postJson("/api/v1/employee/assistant-chats/{$chat->id}/close")->assertOk();
        }

        $this->assertSame(['order.archive', 'chat.close'], StaffActivity::orderBy('id')->pluck('action')->all());
    }

    public function test_a_failed_log_write_does_not_undo_or_break_the_action(): void
    {
        $order = $this->cashOrder($this->tenant('a'));
        Schema::drop('staff_activities');

        $this->actingAs($this->employee, 'sanctum')
            ->putJson("/api/v1/employee/orders/{$order->id}/status", ['status' => 'processing'])->assertOk();

        $this->assertSame('processing', $order->fresh()->status);
    }
}
