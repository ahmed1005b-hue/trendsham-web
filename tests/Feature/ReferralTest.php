<?php

namespace Tests\Feature;

use App\Loyalty\Models\LoyaltyAccount;
use App\Models\Order;
use App\Models\Otp;
use App\Models\User;
use App\Referrals\Models\Referral;
use App\Referrals\ReferralCode;
use App\Referrals\ReferralCookie;
use App\Referrals\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** «دعوة صديق»: الكود، شروط الحساب الجديد، المكافأة بعد التسليم، وسحبها عند الإرجاع. */
class ReferralTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function newCustomer(string $tag): User
    {
        return User::create([
            'first_name' => "New $tag", 'last_name' => 'Friend', 'phone' => '0988'.str_pad((string) crc32($tag) % 1000000, 6, '0', STR_PAD_LEFT),
            'email' => "new-$tag@ref.test", 'password' => 'password', 'role' => 'customer', 'status' => 'active',
        ]);
    }

    private function balance(int $userId): int
    {
        return (int) LoyaltyAccount::where('user_id', $userId)->value('balance');
    }

    /** طلب للصديق بقيمة كافية، ثم تسليمه. */
    private function deliveredOrder(User $friend, int $total = 3000000): Order
    {
        $order = Order::create([
            'order_number' => Order::generateOrderNumber(), 'user_id' => $friend->id, 'status' => 'shipped',
            'payment_status' => 'paid', 'subtotal' => $total, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => $total,
            'shipping_name' => 'Friend', 'shipping_phone' => $friend->phone, 'shipping_address' => 'Damascus',
        ]);
        $order->changeStatus('delivered');

        return $order->fresh();
    }

    public function test_code_round_trips_and_rejects_tampering(): void
    {
        $code = ReferralCode::for(1234);

        $this->assertSame(1234, ReferralCode::decode($code));
        $this->assertSame(1234, ReferralCode::decode(' '.strtolower($code).' '));
        $this->assertNull(ReferralCode::decode(ReferralCode::for(1235).'X'));
        $this->assertNull(ReferralCode::decode(substr(ReferralCode::for(1235), 0, -4).substr($code, -4)));
        $this->assertNull(ReferralCode::decode('AB'));
    }

    public function test_new_customer_applies_a_code_and_both_get_points_after_delivery(): void
    {
        $inviter = $this->tenant('a')['customer'];
        $friend = $this->newCustomer('f1');

        $this->actingAs($friend, 'sanctum')->postJson('/api/v1/referrals', ['code' => ReferralCode::for($inviter->id)])
            ->assertCreated();
        $this->actingAs($inviter, 'sanctum')->getJson('/api/v1/referrals')
            ->assertOk()->assertJsonPath('stats.joined', 1)->assertJsonPath('invited.0.status', 'pending')
            ->assertJsonPath('code', ReferralCode::for($inviter->id));

        $inviterBefore = $this->balance($inviter->id);
        $order = $this->deliveredOrder($friend);

        $referral = Referral::where('referee_id', $friend->id)->first();
        $this->assertSame(Referral::REWARDED, $referral->status);
        $this->assertSame($order->id, $referral->order_id);
        $this->assertSame($inviterBefore + 25, $this->balance($inviter->id));
        $this->assertGreaterThanOrEqual(25, $this->balance($friend->id));

        // تسليم مكرر لا يضيف شيئاً
        $order->changeStatus('shipped');
        $order->changeStatus('delivered');
        $this->assertSame($inviterBefore + 25, $this->balance($inviter->id));
    }

    public function test_cancelling_the_rewarded_order_takes_the_points_back(): void
    {
        $inviter = $this->tenant('a')['customer'];
        $friend = $this->newCustomer('f2');
        app(ReferralService::class)->apply($friend, ReferralCode::for($inviter->id));
        $inviterBefore = $this->balance($inviter->id);

        $order = $this->deliveredOrder($friend);
        $order->changeStatus('cancelled');

        $this->assertSame(Referral::REVERSED, Referral::where('referee_id', $friend->id)->value('status'));
        $this->assertSame($inviterBefore, $this->balance($inviter->id));
    }

    public function test_small_first_order_does_not_reward_yet(): void
    {
        $inviter = $this->tenant('a')['customer'];
        $friend = $this->newCustomer('f3');
        app(ReferralService::class)->apply($friend, ReferralCode::for($inviter->id));

        $this->deliveredOrder($friend, 1000000); // 10,000 ل.س < الحد الأدنى

        $this->assertSame(Referral::PENDING, Referral::where('referee_id', $friend->id)->value('status'));
    }

    public function test_code_is_refused_for_own_code_old_accounts_customers_with_orders_and_twice(): void
    {
        $a = $this->tenant('a');
        $inviter = $a['customer'];
        $other = $this->tenant('b')['customer'];
        $code = ReferralCode::for($inviter->id);

        $this->actingAs($inviter, 'sanctum')->postJson('/api/v1/referrals', ['code' => $code])
            ->assertStatus(422)->assertJsonPath('message', 'لا يمكنك استخدام كود دعوتك.');
        $this->actingAs($other, 'sanctum')->postJson('/api/v1/referrals', ['code' => 'XXXXX'])
            ->assertStatus(422)->assertJsonPath('message', 'كود الدعوة غير صحيح.');

        $hasOrder = $this->tenant('c')['customer']; // لديه طلب سابق
        $this->actingAs($hasOrder, 'sanctum')->postJson('/api/v1/referrals', ['code' => $code])
            ->assertStatus(422)->assertJsonPath('message', 'كود الدعوة للحسابات الجديدة فقط، قبل أول طلب.');

        $old = $this->newCustomer('old');
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $this->actingAs($old, 'sanctum')->postJson('/api/v1/referrals', ['code' => $code])->assertStatus(422);

        $friend = $this->newCustomer('twice');
        $this->actingAs($friend, 'sanctum')->postJson('/api/v1/referrals', ['code' => $code])->assertCreated();
        $this->actingAs($friend, 'sanctum')->postJson('/api/v1/referrals', ['code' => ReferralCode::for($other->id)])
            ->assertStatus(422)->assertJsonPath('message', 'سبق أن أدخلت كود دعوة.');
        $this->assertSame(1, Referral::count());
    }

    public function test_invite_link_cookie_applies_the_code_when_the_account_is_created(): void
    {
        $inviter = $this->tenant('a')['customer'];
        $code = ReferralCode::for($inviter->id);

        $this->get(route('referrals.invite', $code))->assertRedirect(route('register'))->assertCookie(ReferralCookie::NAME);

        request()->cookies->set(ReferralCookie::NAME, $code);
        $friend = $this->newCustomer('cookie');

        $this->assertSame($inviter->id, Referral::where('referee_id', $friend->id)->value('referrer_id'));
    }

    public function test_app_registration_with_a_code_links_the_new_customer(): void
    {
        config(['sms.driver' => 'log']);
        $inviter = $this->tenant('a')['customer'];
        $phone = '+963900005678';
        $payload = [
            'account_type' => 'customer', 'first_name' => 'New', 'last_name' => 'Friend', 'phone' => $phone,
            'email' => 'new-app-friend@ref.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ];

        $this->postJson('/api/v1/auth/register', $payload + ['referral_code' => 'NOPE1'])
            ->assertStatus(422)->assertJsonValidationErrors('referral_code');

        $this->postJson('/api/v1/auth/register', $payload + ['referral_code' => ReferralCode::for($inviter->id)])->assertOk();
        $code = (string) Otp::latest('id')->value('code');
        $this->postJson('/api/v1/auth/register/verify', ['phone' => $phone, 'code' => $code])->assertOk();

        $friend = User::where('email', 'new-app-friend@ref.test')->firstOrFail();
        $this->assertSame($inviter->id, Referral::where('referee_id', $friend->id)->value('referrer_id'));
    }

    public function test_web_page_shows_the_code_and_accepts_a_code(): void
    {
        $inviter = $this->tenant('a')['customer'];
        $friend = $this->newCustomer('web');
        $code = ReferralCode::for($inviter->id);

        $this->actingAs($inviter)->get(route('customer.referrals.index'))->assertOk()->assertSee($code);
        $this->actingAs($friend)->post(route('customer.referrals.apply'), ['code' => $code])
            ->assertRedirect(route('customer.referrals.index'));
        $this->assertSame($inviter->id, Referral::where('referee_id', $friend->id)->value('referrer_id'));
    }
}
