<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'web-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-secret',
            'services.facebook.client_id' => '111',
            'services.facebook.client_secret' => 'fb-secret',
        ]);
    }

    /** @param array<string, mixed> $over */
    private function fakeGoogle(array $over = []): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response($over + [
            'aud' => 'web-client.apps.googleusercontent.com',
            'iss' => 'https://accounts.google.com',
            'exp' => (string) (time() + 600),
            'sub' => 'g-123',
            'email' => 'Sara@Example.com',
            'email_verified' => 'true',
            'given_name' => 'سارة',
            'family_name' => 'أحمد',
        ])]);
    }

    public function test_new_google_user_becomes_a_customer_without_phone(): void
    {
        $this->fakeGoogle();

        $response = $this->postJson('/api/v1/auth/social/google', ['token' => 'id-token'])
            ->assertOk()
            ->assertJsonPath('is_new', true)
            ->assertJsonStructure(['token', 'user']);

        $user = User::where('email', 'sara@example.com')->sole();
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->phone);
        $this->assertNull($user->password);
        $this->assertSame('سارة', $user->first_name);
        $this->assertTrue(SocialAccount::where('provider', 'google')->where('provider_user_id', 'g-123')->where('user_id', $user->id)->exists());
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_second_login_reuses_the_same_account(): void
    {
        $this->fakeGoogle();
        $this->postJson('/api/v1/auth/social/google', ['token' => 't'])->assertOk();
        $this->postJson('/api/v1/auth/social/google', ['token' => 't'])->assertOk()->assertJsonPath('is_new', false);

        $this->assertSame(1, User::count());
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_existing_customer_with_same_email_is_linked_not_duplicated(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'email' => 'sara@example.com']);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/social/google', ['token' => 't'])
            ->assertOk()->assertJsonPath('user.id', $customer->id);
        $this->assertSame(1, User::count());
    }

    public function test_seller_or_admin_email_cannot_sign_in_with_google(): void
    {
        foreach (['seller', 'admin', 'employee'] as $role) {
            User::query()->delete();
            User::factory()->create(['role' => $role, 'email' => 'sara@example.com']);
            $this->fakeGoogle();

            $this->postJson('/api/v1/auth/social/google', ['token' => 't'])->assertForbidden();
        }
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_token_for_another_app_or_unverified_email_is_rejected(): void
    {
        $this->fakeGoogle(['aud' => 'someone-elses-app']);
        $this->postJson('/api/v1/auth/social/google', ['token' => 't'])->assertStatus(401);

        Http::swap(new Factory);
        User::factory()->create(['role' => 'customer', 'email' => 'sara@example.com']);
        $this->fakeGoogle(['email_verified' => 'false']);
        $this->postJson('/api/v1/auth/social/google', ['token' => 't'])->assertStatus(422);

        $this->assertSame(0, SocialAccount::count());
    }

    public function test_suspended_customer_is_refused(): void
    {
        User::factory()->create(['role' => 'customer', 'email' => 'sara@example.com', 'status' => 'suspended']);
        $this->fakeGoogle();

        $this->postJson('/api/v1/auth/social/google', ['token' => 't'])->assertForbidden();
    }

    public function test_facebook_token_must_belong_to_our_app(): void
    {
        Http::fake([
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => true, 'app_id' => '999', 'user_id' => 'f-1']]),
        ]);
        $this->postJson('/api/v1/auth/social/facebook', ['token' => 't'])->assertStatus(401);
        $this->assertSame(0, User::count());
    }

    public function test_facebook_login_creates_customer(): void
    {
        Http::fake([
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => true, 'app_id' => '111', 'user_id' => 'f-1']]),
            'graph.facebook.com/*/me*' => Http::response(['id' => 'f-1', 'first_name' => 'Omar', 'last_name' => 'K', 'email' => 'omar@example.com']),
        ]);

        $this->postJson('/api/v1/auth/social/facebook', ['token' => 't'])->assertOk()->assertJsonPath('is_new', true);
        $this->assertTrue(User::where('email', 'omar@example.com')->where('role', 'customer')->exists());
    }

    public function test_unconfigured_provider_is_disabled_and_hidden(): void
    {
        config(['services.facebook.client_id' => null]);

        $this->getJson('/api/v1/auth/social/providers')->assertExactJson([
            'google' => true, 'facebook' => false, 'google_client_id' => 'web-client.apps.googleusercontent.com',
        ]);
        $this->postJson('/api/v1/auth/social/facebook', ['token' => 't'])->assertStatus(503);
        $this->get('/login')->assertOk()->assertSee(route('social.redirect', 'google'), false)
            ->assertDontSee(route('social.redirect', 'facebook'), false);
    }

    public function test_web_callback_logs_customer_in(): void
    {
        $remote = (new SocialiteUser)->setRaw(['sub' => 'g-9', 'email' => 'web@example.com', 'email_verified' => true, 'given_name' => 'Web'])
            ->map(['id' => 'g-9', 'email' => 'web@example.com']);
        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($remote);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback?code=abc&state=x')->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'web@example.com')->sole());
    }

    public function test_web_callback_refuses_admin_email(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'boss@example.com']);
        $remote = (new SocialiteUser)->setRaw(['sub' => 'g-7', 'email' => 'boss@example.com', 'email_verified' => true])
            ->map(['id' => 'g-7', 'email' => 'boss@example.com']);
        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($remote);
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->get('/auth/google/callback?code=abc')->assertRedirect(route('login'))->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }
}
