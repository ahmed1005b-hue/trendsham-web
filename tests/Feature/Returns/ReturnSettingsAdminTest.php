<?php

namespace Tests\Feature\Returns;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Returns\ReturnSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** المدير يعدّل مهل المرتجعات من صفحة إعدادات المنصة الموجودة (القسم 4). */
class ReturnSettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private const DEADLINES = [
        'returns_window_days' => 10,
        'returns_seller_response_days' => 2,
        'returns_dispute_days' => 5,
        'returns_ship_back_days' => 20,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['commission_rate' => 12, 'story_fee' => 50] + self::DEADLINES;
    }

    public function test_settings_page_shows_the_return_deadlines(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('مهل المرتجعات')
            ->assertSee('name="returns_window_days"', false)
            ->assertSee('name="returns_ship_back_days"', false)
            ->assertSee('value="7"', false);
    }

    public function test_admin_saves_return_deadlines_and_the_service_uses_them(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload())
            ->assertRedirect()->assertSessionHas('success');

        $setting = PlatformSetting::current();
        foreach (self::DEADLINES as $field => $days) {
            $this->assertSame($days, (int) $setting->{$field});
        }
        $this->assertSame(12, (int) $setting->commission_rate);

        $settings = app(ReturnSettings::class)->fresh();
        $this->assertSame([10, 2, 5, 20], [$settings->windowDays(), $settings->sellerResponseDays(), $settings->disputeDays(), $settings->shipBackDays()]);
    }

    public function test_deadlines_must_be_whole_days_between_1_and_365(): void
    {
        $this->actingAs($this->admin);

        foreach ([0, 366, 'abc', 2.5, ''] as $invalid) {
            $this->from(route('admin.settings.edit'))
                ->put(route('admin.settings.update'), $this->payload(['returns_dispute_days' => $invalid]))
                ->assertRedirect(route('admin.settings.edit'))
                ->assertSessionHasErrors('returns_dispute_days');
        }
        $this->assertNull(PlatformSetting::current()->returns_dispute_days);
    }

    public function test_api_settings_expose_and_update_deadlines_keeping_existing_keys(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/v1/admin/settings')->assertOk()
            ->assertJsonStructure(['commission_rate', 'story_fee'])
            ->assertJsonPath('returns_window_days', 7)
            ->assertJsonPath('returns_ship_back_days', 14);

        // عميل قديم يرسل الحقلين الأصليين فقط: يبقى يعمل ولا تتغير المهل
        $this->putJson('/api/v1/admin/settings', ['commission_rate' => 9, 'story_fee' => 10])->assertOk();
        $this->assertNull(PlatformSetting::current()->returns_window_days);

        $this->putJson('/api/v1/admin/settings', $this->payload(['returns_seller_response_days' => 0]))
            ->assertStatus(422)->assertJsonValidationErrors('returns_seller_response_days');

        $this->putJson('/api/v1/admin/settings', $this->payload())->assertOk();
        $this->getJson('/api/v1/admin/settings')->assertJsonPath('returns_window_days', 10)
            ->assertJsonPath('returns_dispute_days', 5);
    }
}
