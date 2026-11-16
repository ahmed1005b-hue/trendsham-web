<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApiPasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_never_returns_reset_token(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'victim@example.com', 'phone' => '0933000111']);

        $existing = $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $user->email]);
        $missing = $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'nobody@example.com']);

        $existing->assertOk()->assertJsonMissingPath('token');
        $missing->assertOk()->assertJsonMissingPath('token');
        // نفس الرد تماماً حتى لا يُعرف إن كان الحساب مسجلاً
        $this->assertSame($missing->json(), $existing->json());
    }

    public function test_password_cannot_be_reset_without_valid_code(): void
    {
        $user = User::factory()->create(['phone' => '0933000222']);
        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $user->phone])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $user->phone,
            'code' => '000000',
            'password' => 'NewPass#2026',
            'password_confirmation' => 'NewPass#2026',
        ])->assertStatus(422);

        $this->assertFalse(Hash::check('NewPass#2026', $user->fresh()->password));
    }

    public function test_password_resets_with_sms_code(): void
    {
        $user = User::factory()->create(['phone' => '0933000333']);
        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $user->phone])->assertOk();

        $otp = Otp::where('phone', $user->phone)->latest()->firstOrFail();
        $this->assertNotNull($otp->code ?? null);

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $user->phone,
            'code' => (string) $otp->code,
            'password' => 'NewPass#2026',
            'password_confirmation' => 'NewPass#2026',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPass#2026', $user->fresh()->password));
    }

    public function test_email_reset_token_expires_after_one_hour(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('known-token'),
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => $user->email,
            'token' => 'known-token',
            'password' => 'NewPass#2026',
            'password_confirmation' => 'NewPass#2026',
        ])->assertStatus(422);

        $this->assertFalse(Hash::check('NewPass#2026', $user->fresh()->password));
    }
}
