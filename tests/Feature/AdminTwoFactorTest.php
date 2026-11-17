<?php

namespace Tests\Feature;

use App\Auth\TwoFactor\Totp;
use App\Auth\TwoFactor\TwoFactorService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private function adminWith2fa(array $over = []): User
    {
        $admin = User::factory()->create($over + ['role' => 'admin', 'email' => 'boss@example.com', 'password' => 'secret-pass']);
        $admin->forceFill([
            'two_factor_secret' => self::SECRET,
            'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $admin;
    }

    private function currentCode(): string
    {
        return Totp::code(self::SECRET, Totp::timestep());
    }

    public function test_totp_matches_rfc_6238_reference_values(): void
    {
        // المفتاح المرجعي في RFC 6238 «12345678901234567890» (SHA1)، آخر 6 أرقام
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('287082', Totp::code($secret, Totp::timestep(59)));
        $this->assertSame('081804', Totp::code($secret, Totp::timestep(1111111109)));
        $this->assertSame('005924', Totp::code($secret, Totp::timestep(1234567890)));
        $this->assertSame('279037', Totp::code($secret, Totp::timestep(2000000000)));
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
    }

    public function test_web_login_asks_for_code_before_opening_the_session(): void
    {
        $this->adminWith2fa();

        $this->post(route('login.email'), ['identifier' => 'boss@example.com', 'password' => 'secret-pass'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.verify'), ['code' => $this->currentCode()])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_same_code_cannot_be_used_twice_and_recovery_code_works_once(): void
    {
        $admin = $this->adminWith2fa();
        $service = app(TwoFactorService::class);

        $code = $this->currentCode();
        $this->assertTrue($service->verify($admin, $code));
        $this->assertFalse($service->verify($admin, $code), 'replayed code is refused');

        $this->assertTrue($service->verify($admin, 'aaaaa-bbbbb'));
        $this->assertFalse($service->verify($admin->fresh(), 'AAAAA-BBBBB'), 'recovery code is single use');
        $this->assertSame(['CCCCC-DDDDD'], $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_challenge_page_without_password_step_goes_back_to_login(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_too_many_wrong_codes_end_the_attempt(): void
    {
        $this->adminWith2fa();
        $this->post(route('login.email'), ['identifier' => 'boss@example.com', 'password' => 'secret-pass']);

        foreach (range(1, 5) as $i) {
            $this->post(route('two-factor.verify'), ['code' => '000000']);
        }
        // حدّ المسار العام (5 في الدقيقة) يرفض فوراً؛ وبعد الدقيقة يبقى قفل الرمز نفسه ويُنهي المحاولة
        $this->post(route('two-factor.verify'), ['code' => $this->currentCode()]);
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->post(route('two-factor.verify'), ['code' => $this->currentCode()])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('two_factor.login'), 'must type the password again');
    }

    public function test_api_login_returns_challenge_then_token(): void
    {
        $this->adminWith2fa();

        $login = $this->postJson('/api/v1/auth/login', ['identifier' => 'boss@example.com', 'password' => 'secret-pass'])
            ->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonMissingPath('token');

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $login->json('challenge'), 'code' => '000000'])->assertStatus(422);
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => 'forged', 'code' => $this->currentCode()])->assertStatus(401);
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $login->json('challenge'), 'code' => $this->currentCode()])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_admin_without_2fa_is_sent_to_setup_when_required(): void
    {
        config(['security.admin_two_factor_required' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.security.two-factor'));
        $this->actingAs($admin)->get(route('admin.security.two-factor'))->assertOk()->assertSee('<svg', false);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/analytics')
            ->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
    }

    public function test_setup_needs_a_correct_code_and_then_unlocks_the_dashboard(): void
    {
        config(['security.admin_two_factor_required' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.security.two-factor'))->assertOk();
        $secret = session('two_factor.setup_secret');

        $this->post(route('admin.security.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($admin->fresh()->hasTwoFactor());

        $this->post(route('admin.security.two-factor.confirm'), ['code' => Totp::code($secret, Totp::timestep())])
            ->assertRedirect(route('admin.security.two-factor'))
            ->assertSessionHas('recovery_codes', fn ($codes) => count($codes) === 8);

        $this->assertTrue($admin->fresh()->hasTwoFactor());
        $this->assertNotSame($secret, $admin->fresh()->getRawOriginal('two_factor_secret'), 'secret is stored encrypted');
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_password_reset_does_not_hand_out_a_token_to_a_2fa_account(): void
    {
        $admin = $this->adminWith2fa();
        DB::table('password_reset_tokens')->insert([
            'email' => $admin->email, 'token' => Hash::make('123456'), 'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $admin->email, 'code' => '123456', 'password' => 'new-pass-123', 'password_confirmation' => 'new-pass-123',
        ]);

        $response->assertJsonMissingPath('token');
    }
}
