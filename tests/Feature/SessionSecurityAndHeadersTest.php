<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * نتائج فحص الأمان: تغيير كلمة السر يُنهي الجلسات الأخرى (الموقع والتطبيق)،
 * وترويسات الأمان على كل رد، واستعادة كلمة السر لا تكشف الحسابات المسجّلة.
 */
class SessionSecurityAndHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'OldPass#2026';

    private const NEW_PASSWORD = 'NewPass#2026';

    private function userWithPassword(): User
    {
        return User::factory()->create(['email' => fake()->unique()->safeEmail(), 'password' => self::OLD_PASSWORD]);
    }

    public function test_web_and_api_responses_carry_security_headers(): void
    {
        foreach ([$this->get('/'), $this->getJson('/api/v1/categories')] as $response) {
            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    public function test_changing_the_password_on_the_website_signs_out_the_app(): void
    {
        $user = $this->userWithPassword();
        $user->createToken('mobile-app');

        $this->actingAs($user)->put(route('profile.password.update'), [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_password_change_elsewhere_ends_the_open_website_session(): void
    {
        $user = $this->userWithPassword();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        // تغيير كلمة السر من جهاز آخر (التطبيق أو الاستعادة)
        $user->update(['password' => self::NEW_PASSWORD]);

        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_the_website_session_survives_when_the_password_is_unchanged(): void
    {
        $user = $this->userWithPassword();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_password_reset_does_not_reveal_whether_an_account_exists(): void
    {
        $payload = [
            'code' => '000000',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
        $user = User::factory()->create(['phone' => '0933000444']);

        $unknown = $this->postJson('/api/v1/auth/reset-password', ['identifier' => '0933999999'] + $payload);
        $known = $this->postJson('/api/v1/auth/reset-password', ['identifier' => $user->phone] + $payload);

        $unknown->assertStatus(422);
        $known->assertStatus(422);
        $this->assertSame($known->json('message'), $unknown->json('message'));
    }
}
