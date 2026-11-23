<?php

namespace Tests\Feature;

use App\Auth\TwoFactor\Totp;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\DailyDeal;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * لوحة المدير في التطبيق: المحذوفات، والمساعد الذكي، وإعداد التحقق بخطوتين (نفس صفحات الموقع).
 */
class AdminAppToolsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function product(): Product
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'متجر سام', 'status' => 'approved', 'commission_rate' => 10]);
        $category = Category::create(['name' => 'c', 'slug' => 'c-'.Str::random(4)]);

        return Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id, 'name' => 'قميص قطن', 'slug' => 'p-'.Str::random(6),
            'sku' => 'SKU-'.Str::random(8), 'price' => 10000, 'stock_quantity' => 3, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    public function test_admin_restores_deleted_items_from_the_app(): void
    {
        $product = $this->product();
        $product->delete();
        $api = $this->actingAs($this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/trash')->assertOk()
            ->assertJsonPath('current', 'products')
            ->assertJsonPath('data.0.title', 'قميص قطن')
            ->assertJsonPath('data.0.detail', 'متجر سام')
            ->assertJsonPath('types.0.count', 1);

        $api->postJson("/api/v1/admin/trash/products/{$product->id}/restore")->assertOk();
        $this->assertFalse($product->fresh()->trashed());

        $api->postJson('/api/v1/admin/trash/nothing/1/restore')->assertNotFound();
    }

    public function test_a_deal_does_not_come_back_before_its_product(): void
    {
        $product = $this->product();
        $deal = DailyDeal::create([
            'product_id' => $product->id, 'discount_percentage' => 20, 'special_price' => 8000, 'starts_at' => now(), 'ends_at' => now()->addDay(),
        ]);
        $deal->delete();
        $product->delete();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/trash/daily_deals/{$deal->id}/restore")
            ->assertStatus(422)->assertJsonPath('message', 'المنتج نفسه محذوف. استرجع المنتج أولاً.');
    }

    public function test_admin_answers_and_closes_an_escalated_chat_from_the_app(): void
    {
        $chat = ChatConversation::create(['guest_session_id' => 'g-1', 'status' => 'escalated', 'escalated_at' => now()]);
        $chat->messages()->create(['role' => 'user', 'content' => 'طلبي وصل مكسور']);
        ChatConversation::create(['guest_session_id' => 'g-2', 'status' => 'open']);
        $api = $this->actingAs($admin = $this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/assistant-chats')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.last_message', 'طلبي وصل مكسور');

        $api->postJson("/api/v1/admin/assistant-chats/{$chat->id}/reply", [])->assertStatus(422)->assertJsonValidationErrors('message');
        $api->postJson("/api/v1/admin/assistant-chats/{$chat->id}/reply", ['message' => 'سنرسل لك بديلاً'])->assertOk()
            ->assertJsonPath('messages.1.content', 'سنرسل لك بديلاً')
            ->assertJsonPath('messages.1.agent', true);
        $this->assertSame($admin->id, $chat->fresh()->escalated_to);

        $api->postJson("/api/v1/admin/assistant-chats/{$chat->id}/close")->assertOk()->assertJsonPath('status', 'closed');
        $api->postJson("/api/v1/admin/assistant-chats/{$chat->id}/reply", ['message' => 'x'])->assertStatus(409);
        $api->getJson('/api/v1/admin/assistant-chats?status=closed')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_admin_manages_assistant_answers_from_the_app(): void
    {
        $api = $this->actingAs($this->admin(), 'sanctum');

        $api->postJson('/api/v1/admin/assistant-faqs', ['question' => 'متى يصل طلبي؟'])
            ->assertStatus(422)->assertJsonValidationErrors(['keywords', 'answer']);
        $id = $api->postJson('/api/v1/admin/assistant-faqs', [
            'question' => 'متى يصل طلبي؟', 'keywords' => 'توصيل, شحن', 'answer' => 'خلال 2 إلى 4 أيام.', 'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');

        $api->putJson("/api/v1/admin/assistant-faqs/{$id}", [
            'question' => 'متى يصل طلبي؟', 'keywords' => 'توصيل', 'answer' => 'خلال 3 أيام.', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.answer', 'خلال 3 أيام.')->assertJsonPath('data.is_active', false);

        $api->getJson('/api/v1/admin/assistant-faqs')->assertOk()->assertJsonCount(1, 'data');
        $api->deleteJson("/api/v1/admin/assistant-faqs/{$id}")->assertOk();
        $this->assertSoftDeleted('assistant_faqs', ['id' => $id]);
    }

    public function test_admin_sets_up_two_step_verification_from_the_app(): void
    {
        config(['security.admin_two_factor_required' => true]);
        $admin = $this->admin();
        $token = $admin->createToken('phone')->plainTextToken;
        $old = $admin->createToken('old-phone')->plainTextToken;
        $api = $this->withHeader('Authorization', 'Bearer '.$token);

        // اللوحة مقفلة، لكن صفحة الإعداد تعمل
        $api->getJson('/api/v1/admin/analytics')->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
        $setup = $api->getJson('/api/v1/admin/security/two-factor')->assertOk()
            ->assertJsonPath('required', true)
            ->assertJsonPath('enabled', false)
            ->json();
        $this->assertStringStartsWith('otpauth://totp/', $setup['otpauth_url']);
        $this->assertStringContainsString('<svg', $setup['qr_svg']);
        // نفس السرّ حتى يُؤكَّد
        $this->assertSame($setup['secret'], $api->getJson('/api/v1/admin/security/two-factor')->json('secret'));

        $api->postJson('/api/v1/admin/security/two-factor', ['code' => '000000'])->assertStatus(422)->assertJsonValidationErrors('code');
        $codes = $api->postJson('/api/v1/admin/security/two-factor', ['code' => Totp::code($setup['secret'], Totp::timestep())])
            ->assertOk()->json('recovery_codes');
        $this->assertNotEmpty($codes);

        // هذه الجلسة تبقى، والجلسات الأخرى تُلغى
        $this->assertSame(1, $admin->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/admin/analytics')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$old)->getJson('/api/v1/admin/analytics')->assertUnauthorized();
    }

    public function test_admin_renews_recovery_codes_from_the_app(): void
    {
        $admin = $this->admin();
        $admin->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['AAAAA-BBBBB'],
            'two_factor_confirmed_at' => now(),
        ])->save();
        $api = $this->actingAs($admin, 'sanctum');

        $api->getJson('/api/v1/admin/security/two-factor')->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('recovery_remaining', 1)
            ->assertJsonMissingPath('secret');

        $api->postJson('/api/v1/admin/security/two-factor/recovery-codes', ['code' => '123456'])->assertStatus(422);
        $codes = $api->postJson('/api/v1/admin/security/two-factor/recovery-codes', ['code' => Totp::code('JBSWY3DPEHPK3PXP', Totp::timestep())])
            ->assertOk()->json('recovery_codes');
        $this->assertGreaterThan(1, count($codes));
    }

    public function test_only_admins_reach_these_tools(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        foreach (['/api/v1/admin/trash', '/api/v1/admin/assistant-chats', '/api/v1/admin/assistant-faqs', '/api/v1/admin/security/two-factor'] as $uri) {
            $this->actingAs($customer, 'sanctum')->getJson($uri)->assertForbidden();
        }
    }
}
