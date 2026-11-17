<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\AccountClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** إيقاف الحساب مؤقتاً، وحذفه نهائياً بعد مهلة 30 يوماً (والبائعون عبر الإدارة). */
class AccountClosureTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret#123';

    private function customer(array $attrs = []): User
    {
        return User::factory()->create(['role' => 'customer', 'password' => self::PASSWORD, 'email' => fake()->unique()->safeEmail()] + $attrs);
    }

    private function order(User $user, string $status): Order
    {
        return Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(8)), 'user_id' => $user->id,
            'status' => $status, 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
    }

    private function apiLogin(User $user)
    {
        return $this->postJson('/api/v1/auth/login', ['identifier' => $user->email, 'password' => self::PASSWORD]);
    }

    public function test_status_endpoint_describes_what_the_user_can_do(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/v1/profile/account')->assertOk()
            ->assertJsonPath('data.can_self_delete', true)
            ->assertJsonPath('data.blockers', [])
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.grace_days', 30);
    }

    public function test_deactivate_needs_the_password_signs_out_everywhere_and_login_reactivates(): void
    {
        $user = $this->customer();
        $user->createToken('phone');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/account/deactivate', ['password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertNull($user->fresh()->deactivated_at);

        $before = $user->fresh()->remember_token;
        $this->postJson('/api/v1/profile/account/deactivate', ['password' => self::PASSWORD])->assertOk();
        $user->refresh();
        $this->assertNotNull($user->deactivated_at);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame($before, $user->remember_token); // كوكي "تذكرني" القديم لا يعيد الدخول

        $this->apiLogin($user)->assertOk()->assertJsonPath('notice', 'أهلاً بعودتك! أُعيد تفعيل حسابك.');
        $this->assertNull($user->fresh()->deactivated_at);
    }

    public function test_open_orders_block_deletion(): void
    {
        $user = $this->customer();
        $this->order($user, 'shipped');
        $this->order($user, 'delivered');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile/account')->assertJsonCount(1, 'data.blockers');
        $this->postJson('/api/v1/profile/account/delete', ['password' => self::PASSWORD, 'confirm' => 'حذف'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'لديك 1 طلب قيد التنفيذ. انتظر تسليمها أو ألغِها أولاً.');
        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_deletion_requires_typing_the_confirmation_word(): void
    {
        Sanctum::actingAs($user = $this->customer());

        $this->postJson('/api/v1/profile/account/delete', ['password' => self::PASSWORD, 'confirm' => 'نعم'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_the_confirmation_word_may_be_typed_in_the_interface_language(): void
    {
        foreach (['Delete', ' SIL '] as $word) {
            Sanctum::actingAs($user = $this->customer());
            $this->postJson('/api/v1/profile/account/delete', ['password' => self::PASSWORD, 'confirm' => $word])
                ->assertOk()->assertJsonPath('result', 'scheduled');
        }
    }

    public function test_deletion_closes_now_and_logging_in_within_grace_cancels_it(): void
    {
        $user = $this->customer();
        $this->order($user, 'delivered'); // الطلبات المنتهية لا تمنع الحذف
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/profile/account/delete', ['password' => self::PASSWORD, 'confirm' => 'حذف'])
            ->assertOk()->assertJsonPath('result', 'scheduled');
        $user->refresh();
        $this->assertNotNull($user->deletion_requested_at);
        $this->assertNotNull($user->deactivated_at);

        $this->travel(20)->days();
        $this->apiLogin($user)->assertOk()
            ->assertJsonPath('notice', 'أهلاً بعودتك! أُلغي طلب حذف حسابك وعاد حسابك يعمل كالمعتاد.');
        $this->assertNull($user->fresh()->deletion_requested_at);

        $this->travel(20)->days();
        $this->artisan('accounts:purge-closed')->assertSuccessful();
        $this->assertSame($user->email, $user->fresh()->email); // أُلغي الطلب فلم يُمسح شيء
    }

    public function test_purge_after_grace_anonymizes_but_keeps_orders(): void
    {
        $user = $this->customer(['first_name' => 'سارة', 'last_name' => 'علي', 'city' => 'دمشق']);
        $order = $this->order($user, 'delivered');
        $user->addresses()->create(['label' => 'home', 'city' => 'دمشق', 'details' => 'المزة', 'is_default' => true]);
        $phone = $user->phone;
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/profile/account/delete', ['password' => self::PASSWORD, 'confirm' => 'حذف'])->assertOk();

        $this->travel(29)->days();
        $this->artisan('accounts:purge-closed');
        $this->assertSame('سارة', $user->fresh()->first_name); // ما زال ضمن المهلة

        $this->travel(2)->days();
        $this->artisan('accounts:purge-closed')->expectsOutput('Purged 1 account(s).');
        $user->refresh();

        $this->assertSame('مستخدم محذوف', $user->name);
        $this->assertSame('deleted-'.$user->id, $user->phone);
        $this->assertNull($user->email);
        $this->assertNull($user->password);
        $this->assertNull($user->city);
        $this->assertSame(0, $user->addresses()->withTrashed()->count());
        $this->assertTrue(app(AccountClosureService::class)->isPurged($user));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $user->id]); // السجل المحاسبي باقٍ

        // لا دخول بعد المسح، والرقم القديم صار متاحاً للتسجيل من جديد
        $this->postJson('/api/v1/auth/login', ['identifier' => $phone, 'password' => self::PASSWORD])->assertUnauthorized();
        $this->assertFalse(User::where('phone', $phone)->exists());
    }

    public function test_sellers_only_send_a_request_to_the_admin(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'password' => self::PASSWORD]);
        $profile = SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'متجر الورد', 'status' => 'approved']);
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/profile/account')->assertJsonPath('data.can_self_delete', false);
        $this->postJson('/api/v1/profile/account/delete', ['password' => self::PASSWORD, 'confirm' => 'حذف'])
            ->assertOk()->assertJsonPath('result', 'requested');

        $seller->refresh();
        $this->assertNotNull($seller->deletion_requested_at);
        $this->assertNull($seller->deactivated_at); // المتجر يبقى يعمل حتى تراجعه الإدارة

        // لا يُمسح تلقائياً حتى بعد المهلة
        $this->travel(40)->days();
        $this->artisan('accounts:purge-closed')->expectsOutput('Purged 0 account(s).');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.sellers.index'))
            ->assertOk()->assertSee('طلب حذف الحساب')->assertSee($profile->store_name);
    }

    public function test_web_profile_delete_flow(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('إدارة الحساب')->assertSee('حذف الحساب نهائياً');

        $this->actingAs($user)->post(route('profile.account.delete'), ['password' => 'wrong', 'confirm' => 'حذف'])
            ->assertRedirect(route('profile.edit').'#account-closure')
            ->assertSessionHasErrors('password', null, 'closure');

        $this->actingAs($user)->post(route('profile.account.delete'), ['password' => self::PASSWORD, 'confirm' => 'حذف'])
            ->assertRedirect(route('home'));
        $this->assertGuest();
        $this->assertNotNull($user->fresh()->deletion_requested_at);
    }

    public function test_web_login_reactivates_with_a_message(): void
    {
        $user = $this->customer();
        $user->forceFill(['deactivated_at' => now()])->save();

        $this->post(route('login.email'), ['identifier' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHas('success', 'أهلاً بعودتك! أُعيد تفعيل حسابك.');
        $this->assertNull($user->fresh()->deactivated_at);
    }

    public function test_public_deletion_page_is_reachable_without_login(): void
    {
        $this->get(route('legal.account-deletion'))->assertOk()
            ->assertSee('حذف حسابك في تريندشام')->assertSee('30 يوماً');
        $this->get(route('legal.privacy'))->assertOk()->assertSee(route('legal.account-deletion'), false);
    }
}
