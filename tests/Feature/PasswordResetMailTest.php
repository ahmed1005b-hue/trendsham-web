<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_forgot_password_sends_branded_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'web@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();

        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo($user->email)
            && str_contains($mail->url, '/reset-password/'));
    }

    public function test_api_forgot_password_sends_branded_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'api@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $user->email])->assertOk();

        Mail::assertSent(PasswordResetMail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_mail_renders_html_and_text(): void
    {
        $user = User::factory()->make(['first_name' => 'أحمد', 'last_name' => null]);
        $mail = new PasswordResetMail($user, 'https://example.test/reset-password/abc?email=a%40b.c');

        $mail->assertHasSubject('إعادة تعيين كلمة المرور - تريندشام');
        $mail->assertSeeInHtml('مرحباً أحمد', false);
        $mail->assertSeeInHtml('https://example.test/reset-password/abc?email=a%40b.c', false);
        $mail->assertSeeInHtml('dir="rtl"', false);
        $mail->assertSeeInText('https://example.test/reset-password/abc?email=a%40b.c', false);
    }
}
