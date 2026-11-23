<?php

namespace Tests\Feature;

use App\GiftCards\Models\GiftCard;
use App\Loyalty\LoyaltyLedger;
use App\Loyalty\Models\LoyaltyTransaction;
use App\Models\PlatformSetting;
use App\Models\PushBroadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * لوحة المدير في التطبيق: الإشعار الجماعي، وبطاقات الهدايا، ونقاط الولاء (نفس صفحات الموقع).
 */
class AdminAppEngagementTest extends TestCase
{
    use RefreshDatabase;

    private const FORM = ['title' => 'تخفيضات الجمعة', 'body' => 'خصومات حتى 50% على متاجر مختارة', 'link' => '/store/1', 'confirm' => true];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'phone_verified_at' => now()]);
    }

    private function noFcm(): void
    {
        config(['services.firebase.credentials' => base_path('tests/Fixtures/does-not-exist.json')]);
        Http::preventStrayRequests();
    }

    public function test_admin_sends_a_broadcast_from_the_app_once_a_week(): void
    {
        $this->noFcm();
        Carbon::setTestNow(Carbon::parse('2026-11-23 12:00', 'Asia/Damascus'));
        $this->customer();
        $api = $this->actingAs($this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/push-broadcasts')->assertOk()
            ->assertJsonPath('audience.total', 1)
            ->assertJsonPath('next_allowed_at', null)
            ->assertJsonCount(0, 'data');

        $api->postJson('/api/v1/admin/push-broadcasts', ['confirm' => false] + self::FORM)
            ->assertStatus(422)->assertJsonValidationErrors('confirm');
        $api->postJson('/api/v1/admin/push-broadcasts', ['link' => 'https://evil.example'] + self::FORM)
            ->assertStatus(422)->assertJsonValidationErrors('link');

        $api->postJson('/api/v1/admin/push-broadcasts', self::FORM)->assertCreated()->assertJsonPath('scheduled', false);
        $this->assertSame(1, PushBroadcast::count());

        // مرة كل 7 أيام: الثاني يُرفض برسالة واضحة
        $api->postJson('/api/v1/admin/push-broadcasts', self::FORM)->assertStatus(422)->assertJsonValidationErrors('title');
        $api->getJson('/api/v1/admin/push-broadcasts')->assertOk()
            ->assertJsonPath('data.0.title', 'تخفيضات الجمعة')
            ->assertJsonPath('next_allowed_at', fn ($v) => $v !== null);
    }

    public function test_a_night_broadcast_is_scheduled_for_the_morning(): void
    {
        $this->noFcm();
        Carbon::setTestNow(Carbon::parse('2026-11-23 23:30', 'Asia/Damascus'));

        $this->actingAs($this->admin(), 'sanctum')->postJson('/api/v1/admin/push-broadcasts', self::FORM)
            ->assertCreated()->assertJsonPath('scheduled', true);
    }

    public function test_admin_sees_gift_card_totals_and_cards_from_the_app(): void
    {
        $buyer = $this->customer();
        foreach ([[GiftCard::ACTIVE, 5000000], [GiftCard::REDEEMED, 2000000], [GiftCard::PENDING, 1000000]] as [$status, $amount]) {
            GiftCard::create([
                'purchaser_id' => $buyer->id, 'amount' => $amount, 'status' => $status,
                'reference' => 'gift-'.Str::random(10), 'gateway' => 'sandbox',
                'code' => 'TSGIFT'.Str::upper(Str::random(8)), 'code_hash' => hash('sha256', Str::random(20)),
                'paid_at' => $status === GiftCard::PENDING ? null : now(), 'expires_at' => now()->addYear(),
            ]);
        }
        $api = $this->actingAs($this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/gift-cards')->assertOk()
            ->assertJsonPath('totals.sold_count', 2)
            ->assertJsonPath('totals.sold_amount', 70000)
            ->assertJsonPath('totals.unredeemed', 50000)
            ->assertJsonCount(3, 'data')
            ->assertJsonMissingPath('data.0.code');

        $api->getJson('/api/v1/admin/gift-cards?status=active')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 50000)
            ->assertJsonPath('data.0.purchaser', $buyer->name);
    }

    public function test_admin_manages_loyalty_points_from_the_app(): void
    {
        $customer = $this->customer();
        app(LoyaltyLedger::class)->credit($customer->id, LoyaltyTransaction::ADMIN_ADD, 30, reason: 'اختبار');
        $api = $this->actingAs($admin = $this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/loyalty')->assertOk()
            ->assertJsonPath('summary.outstanding', 30)
            ->assertJsonPath('data.0.user_id', $customer->id)
            ->assertJsonPath('data.0.balance', 30)
            ->assertJsonPath('settings.loyalty_point_value', 200);

        $api->postJson("/api/v1/admin/loyalty/customers/{$customer->id}/adjust", ['direction' => 'add', 'points' => 10])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $api->postJson("/api/v1/admin/loyalty/customers/{$customer->id}/adjust", ['direction' => 'deduct', 'points' => 500, 'reason' => 'خطأ'])
            ->assertStatus(422)->assertJsonValidationErrors('points');
        $api->postJson("/api/v1/admin/loyalty/customers/{$customer->id}/adjust", ['direction' => 'add', 'points' => 10, 'reason' => 'تعويض تأخير'])
            ->assertOk()->assertJsonPath('balance', 40);

        $api->getJson("/api/v1/admin/loyalty/customers/{$customer->id}")->assertOk()
            ->assertJsonPath('balance', 40)
            ->assertJsonPath('transactions.0.points', 10);
        $this->assertSame($admin->id, LoyaltyTransaction::where('reason', 'تعويض تأخير')->value('admin_id'));

        // النقاط للعملاء فقط
        $api->getJson("/api/v1/admin/loyalty/customers/{$admin->id}")->assertNotFound();
    }

    public function test_admin_saves_loyalty_settings_from_the_app(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/v1/admin/loyalty/settings', [
            'loyalty_enabled' => false, 'loyalty_spend_per_point' => 5000, 'loyalty_point_value' => 100,
            'loyalty_min_redeem' => 20, 'loyalty_expiry_months' => 6, 'loyalty_coupon_valid_days' => 15,
        ])->assertOk()->assertJsonPath('settings.loyalty_enabled', false)->assertJsonPath('settings.loyalty_spend_per_point', 5000);

        $s = PlatformSetting::current()->fresh();
        $this->assertFalse((bool) $s->loyalty_enabled);
        $this->assertSame(500000, (int) $s->loyalty_spend_per_point);
        $this->assertSame(10000, (int) $s->loyalty_point_value);
    }

    public function test_only_admins_reach_these_screens(): void
    {
        $customer = $this->customer();

        foreach (['/api/v1/admin/push-broadcasts', '/api/v1/admin/gift-cards', '/api/v1/admin/loyalty'] as $uri) {
            $this->actingAs($customer, 'sanctum')->getJson($uri)->assertForbidden();
        }
    }
}
