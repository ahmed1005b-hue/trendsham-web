<?php

namespace Tests\Feature;

use App\Models\StaffActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * صلاحيات الموظف: المدير يختار أقسام كل موظف، والموظف لا يدخل غيرها في الموقع ولا في التطبيق.
 * الموظف الذي لم تُحدَّد أقسامه يعمل في كل الأقسام (كما كان).
 */
class EmployeeSectionsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function employee(?array $sections = null): User
    {
        return User::factory()->create([
            'role' => 'employee', 'employee_status' => 'approved', 'status' => 'active', 'employee_permissions' => $sections,
        ]);
    }

    public function test_an_employee_without_chosen_sections_keeps_every_section(): void
    {
        $employee = $this->employee();

        $this->assertSame(array_keys(User::EMPLOYEE_SECTIONS), $employee->employeeSections());
        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/employee/returns')->assertOk();
        $this->actingAs($employee)->get(route('employee.orders.index'))->assertOk();
    }

    public function test_a_support_only_employee_is_kept_out_of_other_sections_on_web_and_app(): void
    {
        $a = $this->tenant('a');
        $employee = $this->employee(['support']);

        $this->actingAs($employee)->get(route('employee.support.index'))->assertOk();
        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/employee/support')->assertOk();

        $this->actingAs($employee)->get(route('employee.orders.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('employee.returns.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('employee.products.approve', $a['product']))->assertForbidden();
        $api = $this->actingAs($employee, 'sanctum');
        $api->getJson('/api/v1/employee/orders')->assertForbidden();
        $api->putJson("/api/v1/employee/orders/{$a['order']->id}/status", ['status' => 'cancelled'])->assertForbidden();
        $api->getJson('/api/v1/employee/assistant-chats')->assertForbidden();
        $api->postJson("/api/v1/employee/products/{$a['product']->id}/approve")->assertForbidden();
        $this->assertNotSame('cancelled', $a['order']->fresh()->status);
    }

    public function test_the_home_page_shows_only_the_employee_sections(): void
    {
        $employee = $this->employee(['orders', 'chats']);

        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/employee/dashboard')->assertOk()
            ->assertJsonPath('sections', ['orders', 'chats'])
            ->assertJsonStructure(['data' => ['new_orders', 'late_orders', 'customer_chats']])
            ->assertJsonMissingPath('data.returns')
            ->assertJsonMissingPath('data.pending_products');

        $this->actingAs($employee)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('data-employee-tile="employee.assistant-chats.index"', false)
            ->assertDontSee('data-employee-tile="employee.returns.index"', false)
            ->assertDontSee(route('employee.products.index'), false);

        $this->actingAs($employee, 'sanctum')->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.employee_sections', ['orders', 'chats']);
    }

    public function test_the_admin_chooses_sections_on_web_and_app_and_it_is_logged(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->admin)->get(route('admin.employees.index'))->assertOk()
            ->assertSee('data-employee-sections="'.$employee->id.'"', false);
        $this->actingAs($this->admin)->put(route('admin.employees.permissions', $employee), ['sections' => ['returns', 'orders']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['orders', 'returns'], $employee->fresh()->employeeSections());

        $api = $this->actingAs($this->admin, 'sanctum');
        $api->getJson('/api/v1/admin/employees/team')->assertOk()
            ->assertJsonPath('data.0.sections', ['orders', 'returns'])
            ->assertJsonCount(count(User::EMPLOYEE_SECTIONS), 'sections');
        $api->putJson("/api/v1/admin/employees/{$employee->id}/permissions", ['sections' => ['support']])->assertOk()
            ->assertJsonPath('sections', ['support']);
        $api->putJson("/api/v1/admin/employees/{$employee->id}/permissions", ['sections' => []])->assertUnprocessable();
        $api->putJson("/api/v1/admin/employees/{$employee->id}/permissions", ['sections' => ['withdrawals']])->assertUnprocessable();

        $this->assertSame(['support'], $employee->fresh()->employeeSections());
        $this->assertSame(
            'حدّد أقسام الموظف '.$employee->name.': دعم البائعين',
            StaffActivity::where('action', 'team.permissions')->latest('id')->first()->description(),
        );
    }

    public function test_only_the_admin_changes_sections_and_only_for_approved_employees(): void
    {
        $employee = $this->employee(['support']);
        $pending = User::factory()->create(['role' => 'employee', 'employee_status' => 'pending', 'status' => 'active']);

        $this->actingAs($employee, 'sanctum')->putJson("/api/v1/admin/employees/{$employee->id}/permissions", ['sections' => ['orders']])
            ->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/employees/{$pending->id}/permissions", ['sections' => ['orders']])
            ->assertNotFound();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/admin/employees/{$this->admin->id}/permissions", ['sections' => ['orders']])
            ->assertNotFound();

        $this->assertSame(['support'], $employee->fresh()->employeeSections());
    }
}
