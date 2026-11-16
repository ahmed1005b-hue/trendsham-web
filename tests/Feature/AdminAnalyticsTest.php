<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'first_name' => 'Ad', 'last_name' => 'Min', 'phone' => '0999000777',
            'email' => 'admin@analytics.test', 'password' => 'password', 'role' => 'admin', 'status' => 'active',
        ]);
    }

    public function test_summary_aggregates_the_platform_in_major_units(): void
    {
        $this->tenant('a');
        $this->tenant('b');

        $data = app(AdminAnalyticsService::class)->summary(30);

        $this->assertSame(30, $data['days']);
        $this->assertEquals(200, $data['kpis']['gmv']);
        $this->assertSame(2, $data['kpis']['orders']);
        $this->assertEquals(100, $data['kpis']['avg_order_value']);
        $this->assertSame(2, $data['kpis']['new_customers']);
        $this->assertCount(30, $data['series']);
        $this->assertEquals(200, array_sum(array_column($data['series'], 'gmv')));

        $card = collect($data['payment_methods'])->firstWhere('method', 'card');
        $this->assertSame(2, $card['count']);
        $this->assertEquals(200, $card['amount']);

        $this->assertCount(2, $data['top_sellers']);
        $this->assertCount(2, $data['top_products']);
        $this->assertSame(2, $data['users']['customers']);
        $this->assertSame(2, $data['users']['sellers']);
        $this->assertSame(2, $data['users']['active_buyers']);
        $this->assertSame(2, $data['catalog']['active_products']);

        $this->assertSame(2, $data['returns']['total']);
        $this->assertEquals(100, $data['returns']['return_rate']);
        $this->assertSame(2, collect($data['returns']['by_reason'])->firstWhere('reason', 'changed_mind')['count']);
        $this->assertSame(2, $data['support']['unanswered_questions']);
    }

    public function test_year_window_groups_the_series_by_week(): void
    {
        $data = app(AdminAnalyticsService::class)->summary(365);

        $this->assertSame(365, $data['days']);
        $this->assertLessThanOrEqual(54, count($data['series']));
        $this->assertGreaterThanOrEqual(52, count($data['series']));
    }

    public function test_unknown_window_falls_back_to_thirty_days(): void
    {
        $this->assertSame(30, AdminAnalyticsService::normalizeDays('5'));
        $this->assertSame(90, AdminAnalyticsService::normalizeDays('90'));
        $this->assertSame(30, AdminAnalyticsService::normalizeDays(null));
    }

    public function test_admin_sees_the_analytics_page_and_others_are_refused(): void
    {
        $a = $this->tenant('a');

        $this->actingAs($this->admin())->get(route('admin.analytics', ['days' => 7]))
            ->assertOk()
            ->assertSee('تحليلات المنصة')
            ->assertSee('Store a');

        $this->actingAs($a['customer'])->get(route('admin.analytics'))->assertForbidden();
        $this->actingAs($a['seller'])->get(route('admin.analytics'))->assertForbidden();
    }

    public function test_dashboard_groups_sections_and_links_to_analytics(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.analytics'), false)
            ->assertSee('المالية')
            ->assertSee('التسويق');
    }

    public function test_api_returns_the_same_summary_to_admins_only(): void
    {
        $a = $this->tenant('a');

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/admin/analytics?days=7')
            ->assertOk()
            ->assertJsonPath('days', 7)
            ->assertJsonPath('kpis.orders', 1)
            ->assertJsonStructure(['kpis', 'changes', 'series', 'order_status', 'payment_methods', 'top_sellers', 'top_products', 'top_categories', 'users', 'catalog', 'returns', 'finance', 'marketing', 'support']);

        Sanctum::actingAs($a['seller']);
        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
    }
}
