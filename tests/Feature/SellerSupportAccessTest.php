<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerSupportGrant;
use App\Models\StaffActivity;
use App\Models\StaffConversation;
use App\Models\User;
use App\Services\SellerSupportAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** البائع يأذن لفريق الدعم بفتح لوحة متجره حتى يُنهي الإذن؛ الموظف يعمل داخلها بحدود ويُسجَّل عمله. */
class SellerSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private SellerProfile $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->store = SellerProfile::create(['user_id' => $this->seller->id, 'store_name' => 'متجر النور', 'status' => 'approved']);
    }

    /** @param  list<string>|null  $sections */
    private function employee(?array $sections = ['support']): User
    {
        return User::factory()->create([
            'role' => 'employee', 'employee_status' => 'approved', 'status' => 'active', 'employee_permissions' => $sections,
        ]);
    }

    private function grant(): SellerSupportGrant
    {
        return app(SellerSupportAccess::class)->grant($this->store, $this->seller);
    }

    /** جلسة «وضع الدعم» كما يتركها الدخول: البائع داخل، وعلامة الدعم تشير إلى الموظف. */
    private function inSupportMode(User $staff, SellerSupportGrant $grant): static
    {
        return $this->actingAs($this->seller)->withSession([SellerSupportAccess::SESSION_KEY => [
            'grant_id' => $grant->id, 'staff_id' => $staff->id, 'store_id' => $this->store->id,
        ]]);
    }

    public function test_seller_grants_access_until_they_end_it_and_the_team_is_told(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->seller)->post(route('seller.support-access.grant'))->assertRedirect(route('seller.support.index'));
        $this->post(route('seller.support-access.grant'));   // مرة ثانية لا تفتح إذناً آخر

        $this->assertSame(1, SellerSupportGrant::query()->active()->count());
        $this->assertTrue(AppNotification::where('user_id', $employee->id)->where('title', 'like', '%متجر النور%')->exists());

        $this->travel(30)->days();   // لا مدة: يبقى حتى يُنهيه البائع
        $this->assertNotNull(app(SellerSupportAccess::class)->activeGrant($this->store));

        $this->delete(route('seller.support-access.revoke'))->assertRedirect(route('seller.support.index'));
        $this->assertNull(app(SellerSupportAccess::class)->activeGrant($this->store));
    }

    public function test_a_support_employee_opens_the_store_panel_only_after_the_seller_allows_it(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->post(route('employee.support-access.enter', $this->store))->assertForbidden();

        $this->grant();
        $this->actingAs($employee)->post(route('employee.support-access.enter', $this->store))
            ->assertRedirect(route('seller.dashboard'));

        $this->assertAuthenticatedAs($this->seller);
        $this->assertSame($employee->id, session(SellerSupportAccess::SESSION_KEY)['staff_id']);
        $this->assertTrue(StaffActivity::where('user_id', $employee->id)->where('action', 'store.enter')->exists());
    }

    public function test_a_full_visit_keeps_the_session_through_entering_and_leaving(): void
    {
        $employee = $this->employee();
        $this->grant();
        $next = function (): void {
            $this->app['auth']->forgetGuards();   // كل طلب يقرأ الحساب من الجلسة فقط، كالمتصفح
        };

        $this->actingAs($employee)->post(route('employee.support-access.enter', $this->store))->assertRedirect(route('seller.dashboard'));
        $next();
        $this->get(route('seller.products.index'))->assertOk();
        $this->assertAuthenticatedAs($this->seller);
        $next();
        $this->post(route('support-access.leave'))->assertRedirect(route('employee.support.index'));
        $next();
        $this->get(route('employee.support.index'))->assertOk();
        $this->assertAuthenticatedAs($employee);
        $next();
        $this->get(route('seller.dashboard'))->assertForbidden();   // عاد موظفاً: لوحة البائع لم تعد له
    }

    public function test_staff_outside_the_support_section_and_other_users_cannot_open_a_store(): void
    {
        $this->grant();

        $this->actingAs($this->employee(['orders']))->post(route('employee.support-access.enter', $this->store))->assertForbidden();
        $this->actingAs($this->seller)->post(route('employee.support-access.enter', $this->store))->assertForbidden();
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->post(route('admin.support-access.enter', $this->store))->assertForbidden();
    }

    public function test_inside_the_store_the_employee_works_on_store_pages_and_changes_are_logged(): void
    {
        $employee = $this->employee();
        $grant = $this->grant();

        $this->inSupportMode($employee, $grant)->get(route('seller.dashboard'))
            ->assertOk()->assertSee('الخروج من لوحة المتجر')->assertSee('متجر النور');

        $this->inSupportMode($employee, $grant)->put(route('seller.store.update'), ['store_name' => 'متجر النور الجديد'])
            ->assertSessionHasNoErrors();
        $this->assertSame('متجر النور الجديد', $this->store->refresh()->store_name);

        $change = StaffActivity::where('action', 'store.change')->sole();
        $this->assertSame($employee->id, $change->user_id);
        $this->assertSame('store', $change->subject_type);
        $this->assertSame($this->store->id, $change->subject_id);

        // تعديل رفضه التحقق لا يُسجَّل
        $this->inSupportMode($employee, $grant)->put(route('seller.store.update'), ['store_name' => ''])->assertSessionHasErrors('store_name');
        $this->assertSame(1, StaffActivity::where('action', 'store.change')->count());
    }

    public function test_money_identity_account_private_chats_and_deletions_stay_closed_to_the_team(): void
    {
        $employee = $this->employee();
        $grant = $this->grant();
        $category = Category::create(['name' => 'قسم', 'slug' => 'c-'.Str::random(5)]);
        $product = Product::create([
            'vendor_id' => $this->store->id, 'category_id' => $category->id, 'name' => 'منتج', 'slug' => 'p-'.Str::random(8),
            'sku' => 'S-'.Str::random(8), 'price' => 1000, 'stock_quantity' => 3, 'status' => 'active', 'admin_approved_at' => now(),
        ]);

        $blocked = [
            ['get', route('seller.wallet.index')],
            ['get', route('seller.wallet.payment-methods')],
            ['post', route('seller.wallet.withdraw')],
            ['get', route('seller.identity-verification.create')],
            ['put', route('seller.profile.update')],
            ['get', route('seller.conversations.index')],
            ['get', route('seller.support.index')],
            ['delete', route('seller.support-access.revoke')],
            ['post', route('seller.showcases.store')],
            ['delete', route('seller.products.destroy', $product)],
            ['get', route('assistant.chat.show')],   // المساعد يجيب البائع عن رصيده ومحادثاته
            ['post', route('assistant.chat.send')],
            ['get', route('profile.edit')],
        ];

        foreach ($blocked as [$method, $url]) {
            $this->inSupportMode($employee, $grant)->{$method}($url)
                ->assertRedirect(route('seller.dashboard'));
        }

        $this->assertNotNull(app(SellerSupportAccess::class)->activeGrant($this->store));   // الموظف لا يُنهي الإذن بدل البائع
        $this->assertSame(0, StaffActivity::where('action', 'store.change')->count());
        $this->assertNotNull($product->fresh());
    }

    public function test_when_the_seller_ends_access_the_employee_is_sent_back_on_the_next_click(): void
    {
        $employee = $this->employee();
        $grant = $this->grant();

        app(SellerSupportAccess::class)->revoke($this->store, $this->seller);

        $this->inSupportMode($employee, $grant)->get(route('seller.products.index'))
            ->assertRedirect(route('employee.support.index'))
            ->assertSessionHas('support_notice');

        $this->assertAuthenticatedAs($employee);
        $this->assertNull(session(SellerSupportAccess::SESSION_KEY));
    }

    public function test_an_employee_who_leaves_the_support_section_is_sent_back_too(): void
    {
        $employee = $this->employee();
        $grant = $this->grant();
        $employee->forceFill(['employee_permissions' => ['orders']])->save();

        $this->inSupportMode($employee, $grant)->get(route('seller.dashboard'))
            ->assertRedirect(route('employee.support.index'));
        $this->assertAuthenticatedAs($employee);
    }

    public function test_leaving_the_store_returns_the_employee_to_their_own_account(): void
    {
        $employee = $this->employee();
        $grant = $this->grant();

        $this->inSupportMode($employee, $grant)->post(route('support-access.leave'))
            ->assertRedirect(route('employee.support.index'));

        $this->assertAuthenticatedAs($employee);
        $this->assertTrue(StaffActivity::where('user_id', $employee->id)->where('action', 'store.leave')->exists());
        $this->assertNotNull(app(SellerSupportAccess::class)->activeGrant($this->store));   // الخروج لا يُنهي إذن البائع
    }

    public function test_the_seller_sees_what_the_team_did_without_staff_names(): void
    {
        $employee = $this->employee();
        $employee->update(['first_name' => 'موظف', 'last_name' => 'سري']);
        $grant = $this->grant();
        $this->inSupportMode($employee, $grant)->put(route('seller.store.update'), ['store_name' => 'متجر النور']);
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->actingAs($this->seller)->get(route('seller.support.index'))
            ->assertOk()
            ->assertSee('إنهاء الإذن')
            ->assertSee('عدّل معلومات المتجر')
            ->assertDontSee('موظف سري');
    }

    public function test_support_staff_see_the_open_button_on_the_conversation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $conversation = StaffConversation::create([
            'vendor_id' => $this->store->id, 'staff_id' => $admin->id, 'subject' => 'مشكلة في الشحن', 'status' => 'open',
        ]);
        $employee = $this->employee();

        $this->actingAs($employee)->get(route('employee.support.show', $conversation))
            ->assertOk()->assertDontSee(route('employee.support-access.enter', $this->store));

        $this->grant();
        $this->actingAs($employee)->get(route('employee.support.show', $conversation))
            ->assertOk()->assertSee(route('employee.support-access.enter', $this->store));
        $this->actingAs($employee)->get(route('employee.support.index'))->assertOk()->assertSee('سمح بفتح لوحته');
    }

    public function test_the_app_lets_the_seller_grant_see_and_end_access(): void
    {
        $this->actingAs($this->seller, 'sanctum');

        $this->getJson('/api/v1/seller/support-access')->assertOk()->assertJsonPath('data.active', false);
        $this->postJson('/api/v1/seller/support-access')->assertOk()->assertJsonPath('data.active', true);
        $this->assertNotNull(app(SellerSupportAccess::class)->activeGrant($this->store));

        StaffActivity::create([
            'user_id' => $this->employee()->id, 'actor_name' => 'موظف سري', 'actor_role' => 'employee',
            'action' => 'store.change', 'subject_type' => 'store', 'subject_id' => $this->store->id,
            'properties' => ['store' => 'متجر النور', 'what' => 'عدّل المنتج', 'item' => 'قميص'],
        ]);
        $this->getJson('/api/v1/seller/support-access')
            ->assertOk()
            ->assertJsonPath('data.actions.0.description', 'عدّل المنتج «قميص»')
            ->assertDontSee('موظف سري');

        $this->deleteJson('/api/v1/seller/support-access')->assertOk()->assertJsonPath('data.active', false);
    }
}
