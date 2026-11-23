<?php

namespace Tests\Feature;

use App\Coupons\Models\CouponCampaign;
use App\Models\AppNotification;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * لوحة المدير في التطبيق: ديون المتاجر وإعداداتها، ومراجعة كوبونات البائعين (نفس صفحات الموقع).
 */
class AdminAppDebtsAndCouponsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_sees_store_debts_and_records_a_payment_from_the_app(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $a['store']->walletOrCreate()->update(['balance' => -2500000]);
        $b['store']->walletOrCreate()->update(['balance' => 700000]);
        $api = $this->actingAs($this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/accounting/debts')->assertOk()
            ->assertJsonPath('total', 25000)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.store_name', $a['store']->store_name)
            ->assertJsonPath('data.0.debt', 25000);

        $api->postJson("/api/v1/admin/accounting/debts/{$a['store']->id}/pay", ['amount' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $api->postJson("/api/v1/admin/accounting/debts/{$a['store']->id}/pay", ['amount' => 10000, 'note' => 'تحويل'])
            ->assertOk()->assertJsonPath('debt', 15000);

        $this->assertSame(-1500000, (int) $a['store']->wallet()->first()->balance);
        $this->assertTrue(AppNotification::where('user_id', $a['seller']->id)->where('type', 'debt_paid')->exists());
    }

    public function test_app_settings_include_shipping_fees_and_debt_rules(): void
    {
        $api = $this->actingAs($this->admin(), 'sanctum');
        PlatformSetting::current()->update(['seller_debt_limit' => 10000000, 'self_delivery_min_orders' => 5]);

        $api->getJson('/api/v1/admin/settings')->assertOk()
            ->assertJsonPath('seller_debt_limit', 100000)
            ->assertJsonPath('self_delivery_min_orders', 5);

        $api->putJson('/api/v1/admin/settings', [
            'commission_rate' => 10, 'story_fee' => 0,
            'seller_debt_limit' => 50000, 'self_delivery_min_orders' => 3, 'shipping_parcel_fee' => 15000,
        ])->assertOk();

        $setting = PlatformSetting::current()->fresh();
        $this->assertSame(5000000, (int) $setting->seller_debt_limit);
        $this->assertSame(3, (int) $setting->self_delivery_min_orders);
        $this->assertSame(1500000, (int) $setting->shipping_parcel_fee);
    }

    public function test_admin_reviews_seller_coupons_from_the_app(): void
    {
        $a = $this->tenant('a');
        $a['campaign']->update(['status' => 'pending_review', 'value' => 70]);
        $api = $this->actingAs($this->admin(), 'sanctum');

        $api->getJson('/api/v1/admin/seller-coupons')->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('data.0.id', $a['campaign']->id)
            ->assertJsonPath('data.0.status', 'pending_review')
            ->assertJsonPath('data.0.store_name', $a['store']->store_name);

        $api->postJson("/api/v1/admin/seller-coupons/{$a['campaign']->id}/reject", [])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $api->postJson("/api/v1/admin/seller-coupons/{$a['campaign']->id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'active');
        // الموافقة مرة ثانية: رسالة واضحة لا خطأ خادم
        $api->postJson("/api/v1/admin/seller-coupons/{$a['campaign']->id}/approve")->assertStatus(422);

        $api->postJson("/api/v1/admin/seller-coupons/{$a['campaign']->id}/suspend", ['reason' => 'خصم مبالغ فيه'])->assertOk()
            ->assertJsonPath('data.status', 'suspended');
        $this->assertSame('suspended', CouponCampaign::find($a['campaign']->id)->status);
    }

    public function test_platform_coupons_are_not_reviewed_as_seller_coupons(): void
    {
        $a = $this->tenant('a');
        $a['campaign']->update(['funder' => 'platform', 'status' => 'pending_review']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/v1/admin/seller-coupons/{$a['campaign']->id}/approve")->assertNotFound();
    }

    public function test_only_admins_reach_debts_and_seller_coupons(): void
    {
        $a = $this->tenant('a');

        $this->actingAs($a['seller'], 'sanctum')->getJson('/api/v1/admin/accounting/debts')->assertForbidden();
        $this->actingAs($a['customer'], 'sanctum')->getJson('/api/v1/admin/seller-coupons')->assertForbidden();
    }
}
