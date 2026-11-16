<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\User;
use App\Services\Sms\Drivers\TwilioSmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhoneLookupAndTwilioTest extends TestCase
{
    use RefreshDatabase;

    public function test_twilio_receives_international_format(): void
    {
        $this->assertSame('+963933123456', TwilioSmsDriver::toE164('0933123456'));
        $this->assertSame('+963933123456', TwilioSmsDriver::toE164('+963 933 123 456'));
        $this->assertSame('+905301234567', TwilioSmsDriver::toE164('00905301234567'));
        $this->assertNull(TwilioSmsDriver::toE164('12'));
    }

    public function test_turkish_local_numbers_are_detected(): void
    {
        $this->assertSame('+905301234567', TwilioSmsDriver::toE164('0530 123 45 67'));
        $this->assertSame('+905301234567', TwilioSmsDriver::toE164('5301234567'));
        $this->assertSame('+905301234567', TwilioSmsDriver::toE164('+90 530 123 45 67'));
        // الأرقام السورية تبقى كما هي
        $this->assertSame('+963933123456', TwilioSmsDriver::toE164('933123456'));
    }

    public function test_turkish_user_can_login_with_local_format(): void
    {
        $user = User::factory()->create(['phone' => '+905301112233', 'password' => 'Secret#2026']);
        $this->assertSame('905301112233', $user->fresh()->phone);

        $this->postJson('/api/v1/auth/login', ['identifier' => '05301112233', 'password' => 'Secret#2026'])
            ->assertOk();
    }

    public function test_twilio_without_credentials_fails_gracefully(): void
    {
        $this->assertFalse((new TwilioSmsDriver('', '', ''))->send('0933123456', 'x'));
    }

    public function test_phone_lookup_accepts_local_format(): void
    {
        $user = User::factory()->create(['phone' => '0933000444']);

        $this->assertSame('963933000444', $user->fresh()->phone);
        $this->assertTrue(User::phoneIs('0933000444')->whereKey($user->id)->exists());
        $this->assertTrue(User::phoneIs('+963933000444')->whereKey($user->id)->exists());
        $this->assertFalse(User::phoneIs('0933000999')->exists());
    }

    public function test_api_login_with_local_phone_format(): void
    {
        User::factory()->create(['phone' => '0933000555', 'password' => 'Secret#2026']);

        $this->postJson('/api/v1/auth/login', ['identifier' => '0933000555', 'password' => 'Secret#2026'])
            ->assertOk();
    }

    public function test_api_password_reset_with_local_phone_format(): void
    {
        $user = User::factory()->create(['phone' => '0933000666']);
        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => '0933000666'])->assertOk();

        $otp = Otp::where('phone', $user->phone)->latest()->firstOrFail();

        $this->postJson('/api/v1/auth/reset-password', [
            'identifier' => '0933000666',
            'code' => (string) $otp->code,
            'password' => 'NewPass#2026',
            'password_confirmation' => 'NewPass#2026',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPass#2026', $user->fresh()->password));
    }

    public function test_web_login_with_local_phone_format(): void
    {
        $user = User::factory()->create(['phone' => '0933000777', 'password' => 'Secret#2026']);

        $this->post('/login', ['identifier' => '0933000777', 'password' => 'Secret#2026']);

        $this->assertAuthenticatedAs($user);
    }
}
