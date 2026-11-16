<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Support\MessageTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * رسائل السيرفر بلغة المستخدم: ردود الـ API حسب X-App-Locale، والإشعارات
 * المخزّنة بالعربية حسب لغة القارئ، وSMS والبريد حسب لغة المستلم.
 */
class ApiLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_messages_follow_the_app_locale_and_the_choice_is_remembered(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/cart', [], ['X-App-Locale' => 'tr'])
            ->assertOk()->assertJsonPath('message', 'Sepet tamamen temizlendi.')
            ->assertHeader('Content-Language', 'tr');
        $this->assertSame('tr', $user->fresh()->locale);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/cart', [], ['X-App-Locale' => 'ar'])
            ->assertJsonPath('message', 'تم مسح السلة بالكامل.');
        $this->assertSame('ar', $user->fresh()->locale);

        // ترويسة المتصفح التلقائية ليست اختياراً من المستخدم: تبقى العربية ولا تُحفظ
        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/cart', [], ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertJsonPath('message', 'تم مسح السلة بالكامل.');
        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_default_validation_messages_exist_in_every_language(): void
    {
        $this->postJson('/api/v1/auth/login', [], ['X-App-Locale' => 'tr'])
            ->assertStatus(422)->assertJsonFragment(['message' => 'Telefon veya e-posta alanı zorunludur. (and 1 more error)']);
    }

    public function test_validation_and_dynamic_messages_are_translated_whole(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [], ['X-App-Locale' => 'en'])
            ->assertStatus(422)->assertJsonPath('message', 'The identifier is required.');

        $this->assertSame('Starts on 2026-12-01', __('يبدأ في :date', ['date' => '2026-12-01'], 'en'));
    }

    public function test_stored_notifications_are_shown_in_the_readers_language(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        AppNotification::create([
            'user_id' => $user->id, 'type' => 'order_status_changed', 'group' => 'orders',
            'title' => 'تحديث على طلبك #TS-1042', 'body' => 'الحالة الجديدة: قيد التجهيز', 'push_status' => 'sent',
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications', ['X-App-Locale' => 'en'])
            ->assertJsonPath('data.0.title', 'Update on your order #TS-1042')
            ->assertJsonPath('data.0.body', 'New status: Being prepared');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications', ['X-App-Locale' => 'ar'])
            ->assertJsonPath('data.0.title', 'تحديث على طلبك #TS-1042');
    }

    public function test_translator_handles_nested_values_and_leaves_unknown_text(): void
    {
        $this->assertSame('You earned 5 points from your order #77',
            MessageTranslator::translate('كسبت 5 نقاط من طلبك #77', 'en'));
        $this->assertSame('Bir kupon kazandınız: %10 indirim',
            MessageTranslator::translate('وصلك كوبون: خصم 10%', 'tr'));
        $this->assertSame("Order #9 was paid twice — second transaction number: X1\nNew status: Being prepared",
            MessageTranslator::translate("الطلب #9 دُفع مرتين — رقم العملية الثانية: X1\nالحالة الجديدة: قيد التجهيز", 'en'));
        // نص كتبه مستخدم يبقى كما هو
        $this->assertSame('منتج رائع جداً', MessageTranslator::translate('منتج رائع جداً', 'en'));
    }

    public function test_sms_is_sent_in_the_given_language(): void
    {
        config(['sms.driver' => 'log']);
        Log::spy();

        app(SmsService::class)->send('0999999999', 'رمز التحقق الخاص بك في تريندشام: 123456', 'tr');

        Log::shouldHaveReceived('info')->withArgs(fn ($line) => str_contains($line, 'TrendSham doğrulama kodunuz: 123456'));
    }

    public function test_password_reset_mail_uses_the_request_language(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'customer', 'email' => 'sam@example.com', 'first_name' => 'Sam', 'last_name' => 'Lee']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'sam@example.com'], ['X-App-Locale' => 'en'])->assertOk();

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) {
            $this->assertSame('en', $mail->locale);
            $html = $mail->render();
            $this->assertStringContainsString('dir="ltr"', $html);
            $this->assertStringContainsString('Reset password', $html);
            $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', strip_tags(preg_replace('/<style.*?<\/style>/s', '', $html)));

            return true;
        });
    }
}
