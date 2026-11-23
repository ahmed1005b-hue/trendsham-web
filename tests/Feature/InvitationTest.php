<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Otp;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إنشاء الحساب العام للعملاء فقط؛ البائع والموظف ينضمان برابط دعوة من الإدارة
 * (مرة واحدة، له مدة، ويمكن ربطه بهاتف أو بريد، وتلغيه الإدارة).
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sms.driver' => 'log']);
    }

    private function details(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Sam', 'last_name' => 'Store', 'phone' => '0933111222', 'email' => 'sam@invite.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ];
    }

    /** تسجيل الموقع بخطوتيه (البيانات ثم رمز الهاتف). */
    private function registerOnWeb(array $details)
    {
        $this->postJson(route('register.start'), $details)->assertOk();

        return $this->postJson(route('register.verify'), ['code' => (string) Otp::latest('id')->value('code')]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_public_sign_up_always_creates_a_customer(): void
    {
        $this->get(route('register'))->assertOk()->assertDontSee('name="account_type"', false);

        $this->registerOnWeb($this->details(['account_type' => 'seller', 'store_name' => 'X']))->assertOk();

        $user = User::where('email', 'sam@invite.test')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->sellerProfile);
    }

    public function test_admin_creates_a_seller_link_and_the_guest_opens_a_store_with_it(): void
    {
        $this->actingAs($this->admin())->post(route('admin.invitations.store'), ['role' => 'seller', 'name' => 'Sam', 'store_name' => 'متجر سام', 'days' => 7])
            ->assertRedirect(route('admin.invitations.index'));
        $invitation = Invitation::firstOrFail();
        $this->actingAs($this->admin())->get(route('admin.invitations.index'))->assertOk()->assertSee($invitation->url(), false);
        auth()->logout();

        $this->get($invitation->url())->assertOk()->assertSee('name="invitation_token"', false)->assertSee('متجر سام');

        $this->registerOnWeb($this->details(['invitation_token' => $invitation->token, 'store_name' => 'متجر سام']))
            ->assertOk()->assertJsonPath('redirect', route('seller.dashboard'));

        $user = User::where('email', 'sam@invite.test')->firstOrFail();
        $this->assertSame('seller', $user->role);
        $this->assertSame('approved', $user->sellerProfile->status);
        $this->assertSame('متجر سام', $user->sellerProfile->store_name);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertSame($user->id, $invitation->fresh()->accepted_by);
    }

    public function test_a_link_works_only_once(): void
    {
        $invitation = Invitation::issue(['role' => 'seller']);
        $this->registerOnWeb($this->details(['invitation_token' => $invitation->token, 'store_name' => 'A']))->assertOk();
        auth()->logout();

        $this->get($invitation->url())->assertOk()->assertSee('استُعملت من قبل');
        $this->postJson(route('register.start'), $this->details(['phone' => '0933999888', 'email' => 'b@invite.test', 'invitation_token' => $invitation->token, 'store_name' => 'B']))
            ->assertStatus(422);
    }

    public function test_expired_and_revoked_links_are_refused(): void
    {
        $expired = Invitation::issue(['role' => 'employee', 'days' => 1]);
        $this->travel(2)->days();
        $this->get($expired->url())->assertOk()->assertSee('انتهت صلاحية');

        $revoked = Invitation::issue(['role' => 'seller']);
        $this->actingAs($this->admin())->post(route('admin.invitations.revoke', $revoked))->assertRedirect();
        auth()->logout();
        $this->get($revoked->url())->assertOk()->assertSee('أُلغيت');
        $this->get('/join/not-a-real-token')->assertOk()->assertSee('رابط الدعوة غير صحيح');
    }

    public function test_a_link_tied_to_a_phone_refuses_anyone_else(): void
    {
        $invitation = Invitation::issue(['role' => 'seller', 'phone' => '0933111222']);

        $this->postJson(route('register.start'), $this->details(['phone' => '0944000111', 'invitation_token' => $invitation->token, 'store_name' => 'A']))
            ->assertStatus(422)->assertJsonPath('message', 'هذه الدعوة مخصصة لشخص آخر (رقم هاتف أو بريد مختلف).');
        $this->registerOnWeb($this->details(['invitation_token' => $invitation->token, 'store_name' => 'A']))->assertOk();
        $this->assertSame('seller', User::where('email', 'sam@invite.test')->value('role'));
    }

    public function test_an_employee_link_makes_an_approved_employee(): void
    {
        $invitation = Invitation::issue(['role' => 'employee']);

        $this->registerOnWeb($this->details(['invitation_token' => $invitation->token]))
            ->assertOk()->assertJsonPath('redirect', route('employee.dashboard'));

        $user = User::where('email', 'sam@invite.test')->firstOrFail();
        $this->assertSame('employee', $user->role);
        $this->assertSame('approved', $user->employee_status);
        $this->assertSame('active', $user->status);
    }

    public function test_a_signed_in_customer_accepts_with_the_current_account(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $invitation = Invitation::issue(['role' => 'seller', 'store_name' => 'متجري']);

        $this->actingAs($customer)->get($invitation->url())->assertOk()->assertSee('قبول الدعوة');
        $this->actingAs($customer)->post(route('invitations.accept', $invitation->token), ['store_name' => 'متجري'])
            ->assertRedirect(route('seller.dashboard'));

        $this->assertSame('seller', $customer->fresh()->role);
        $this->assertSame('متجري', SellerProfile::where('user_id', $customer->id)->value('store_name'));

        $seller = $customer->fresh();
        $employeeInvite = Invitation::issue(['role' => 'employee']);
        $this->actingAs($seller)->post(route('invitations.accept', $employeeInvite->token))->assertSessionHas('error');
        $this->assertSame('seller', $seller->fresh()->role);
    }

    public function test_customers_can_no_longer_open_a_store_by_themselves(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('customer.seller-application.create'))->assertForbidden();
        $this->actingAs($customer)->postJson('/api/v1/seller-application', ['store_name' => 'X'])->assertForbidden();
        $this->get(route('home'))->assertOk()->assertDontSee(route('customer.seller-application.create'), false);
    }

    public function test_the_app_signs_up_with_an_invitation_code_and_admins_manage_links(): void
    {
        $admin = $this->admin();
        $created = $this->actingAs($admin)->postJson('/api/v1/admin/invitations', ['role' => 'seller', 'store_name' => 'متجر التطبيق'])
            ->assertCreated()->json('data');
        $this->assertTrue($created['usable']);
        $token = Invitation::findOrFail($created['id'])->token;
        auth()->forgetGuards();

        $this->getJson('/api/v1/invitations/'.$token)->assertOk()
            ->assertJsonPath('data.role', 'seller')->assertJsonPath('data.store_name', 'متجر التطبيق');

        $phone = '+963933777666';
        $payload = ['first_name' => 'App', 'last_name' => 'Seller', 'phone' => $phone, 'email' => 'app@invite.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!'];
        $this->postJson('/api/v1/auth/register', $payload + ['invitation_token' => 'wrong'])->assertStatus(422);
        // الرابط الكامل يُقبل أيضاً، لا الرمز وحده فقط
        $this->postJson('/api/v1/auth/register', $payload + ['invitation_token' => $created['url'], 'store_name' => 'متجر التطبيق'])->assertOk();
        $this->postJson('/api/v1/auth/register/verify', ['phone' => $phone, 'code' => (string) Otp::latest('id')->value('code')])
            ->assertOk()->assertJsonPath('user.role', 'seller');

        $this->actingAs($admin)->getJson('/api/v1/admin/invitations')->assertOk()->assertJsonPath('data.0.usable', false);
    }

    public function test_the_app_signs_up_customers_without_a_code(): void
    {
        $phone = '+963933555444';
        $this->postJson('/api/v1/auth/register', ['first_name' => 'A', 'last_name' => 'B', 'phone' => $phone, 'email' => 'c@invite.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'account_type' => 'employee'])->assertOk();
        $this->postJson('/api/v1/auth/register/verify', ['phone' => $phone, 'code' => (string) Otp::latest('id')->value('code')])
            ->assertOk()->assertJsonPath('user.role', 'customer');
    }
}
