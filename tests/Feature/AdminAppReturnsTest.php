<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SellerProfile;
use App\Models\StaffActivity;
use App\Models\User;
use App\Returns\Models\ReturnRequest;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Loyalty\LoyaltyTestHelpers;
use Tests\TestCase;

/**
 * المرتجعات للمدير في التطبيق: نفس صفحة الموقع (المحالة للإدارة، والاستلام، والاسترداد).
 */
class AdminAppReturnsTest extends TestCase
{
    use LoyaltyTestHelpers, RefreshDatabase;

    private User $customer;

    private Order $order;

    private OrderItem $line;

    private SellerProfile $seller;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = $this->customer();
        $this->order = $this->order($this->customer, 3_000_000);
        $this->line = $this->order->items()->first();
        $this->line->update(['quantity' => 3, 'unit_price' => 1_000_000]);
        $this->order = $this->deliver($this->order);
        $this->line = $this->line->fresh();
        $this->seller = SellerProfile::findOrFail($this->line->vendor_id);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function requested(): ReturnRequest
    {
        return $this->service()->create($this->customer, $this->order, [$this->line->id => 1], 'damaged', 'وصل مكسوراً');
    }

    private function escalated(): ReturnRequest
    {
        $request = $this->requested();
        $this->service()->sellerReject($this->seller, $request, 'لا يوجد عيب');

        return $this->service()->dispute($this->customer, $request->fresh(), 'العيب واضح');
    }

    public function test_admin_lists_returns_by_tab_with_counts(): void
    {
        $escalated = $this->escalated();
        $requested = $this->requested();

        $api = $this->actingAs($this->admin, 'sanctum');
        $api->getJson('/api/v1/admin/returns')->assertOk()
            ->assertJsonPath('tab', 'escalated')
            ->assertJsonPath('counts.escalated', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.number', $escalated->number)
            ->assertJsonPath('data.0.store_name', $this->seller->store_name);

        $numbers = collect($api->getJson('/api/v1/admin/returns?tab=all')->json('data'))->pluck('number');
        $this->assertTrue($numbers->contains($requested->number));
    }

    public function test_admin_decides_an_escalated_return_from_the_app(): void
    {
        $request = $this->escalated();
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->getJson("/api/v1/admin/returns/{$request->id}")->assertOk()
            ->assertJsonPath('actions.decide', true)
            ->assertJsonPath('actions.record_refund', false);

        $api->postJson("/api/v1/admin/returns/{$request->id}/reject", [])->assertStatus(422)->assertJsonValidationErrors('note');
        $api->postJson("/api/v1/admin/returns/{$request->id}/approve", ['note' => 'العيب ظاهر بالصور'])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('actions.decide', false)
            ->assertJsonPath('actions.confirm_received', true);

        // نفس الخطوة مرتين: رسالة واضحة لا خطأ خادم
        $api->postJson("/api/v1/admin/returns/{$request->id}/approve")->assertStatus(422);
    }

    public function test_admin_confirms_receipt_and_records_the_refund_from_the_app(): void
    {
        $request = $this->requested();
        $this->service()->sellerApprove($this->seller, $request);
        $api = $this->actingAs($this->admin, 'sanctum');

        $api->postJson("/api/v1/admin/returns/{$request->id}/received")->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('actions.record_refund', true)
            ->assertJsonPath('refund_form.suggested_amount', 10000)
            ->assertJsonPath('refund_form.methods.0.value', 'bank_transfer');

        $api->postJson("/api/v1/admin/returns/{$request->id}/refund", ['amount' => 10000, 'method' => 'paypal'])
            ->assertStatus(422)->assertJsonValidationErrors('method');
        $api->postJson("/api/v1/admin/returns/{$request->id}/refund", [
            'amount' => 10000, 'method' => 'bank_transfer', 'reference' => 'TRX-1',
        ])->assertOk()->assertJsonPath('data.status', 'refunded');

        $this->assertSame(1_000_000, (int) $request->fresh()->refund->amount);
    }

    public function test_only_admins_reach_the_returns_api(): void
    {
        $request = $this->escalated();

        $this->actingAs($this->seller->user, 'sanctum')->getJson('/api/v1/admin/returns')->assertForbidden();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/admin/returns/{$request->id}/approve")->assertForbidden();
    }

    /** الموظف يقرر في النزاع ويؤكد الاستلام (الموقع والتطبيق)، أما إرجاع المال فللمدير وحده. */
    public function test_the_employee_decides_and_receives_returns_but_cannot_refund(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'employee_status' => 'approved', 'status' => 'active']);
        $request = $this->escalated();
        $api = $this->actingAs($employee, 'sanctum');

        $api->getJson('/api/v1/employee/returns')->assertOk()->assertJsonPath('data.0.number', $request->number);
        $api->postJson("/api/v1/employee/returns/{$request->id}/approve", ['note' => 'العيب ظاهر'])->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $api->postJson("/api/v1/employee/returns/{$request->id}/received")->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('actions.record_refund', false);

        $api->postJson("/api/v1/employee/returns/{$request->id}/refund", ['amount' => 10000, 'method' => 'bank_transfer'])->assertNotFound();
        $api->postJson("/api/v1/admin/returns/{$request->id}/refund", ['amount' => 10000, 'method' => 'bank_transfer'])->assertForbidden();

        $this->actingAs($employee)->get(route('employee.returns.show', $request))->assertOk()
            ->assertSee(route('employee.orders.show', $request->order), false)
            ->assertDontSee(route('admin.returns.refund', $request), false);
        $this->assertNull($request->fresh()->refund);
        $this->assertSame(['return.approve', 'return.received'], StaffActivity::orderBy('id')->pluck('action')->all());
    }
}
