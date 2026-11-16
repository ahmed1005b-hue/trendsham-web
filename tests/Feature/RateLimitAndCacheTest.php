<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PlatformSetting;
use App\Support\AppCache;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitAndCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_is_blocked_after_five_wrong_attempts_with_a_clear_message(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['identifier' => 'x@test.com', 'password' => 'wrong'])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['identifier' => 'x@test.com', 'password' => 'wrong'])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after'])
            ->assertHeader('Retry-After');

        // حساب آخر من نفس الجهاز ما زال يستطيع المحاولة
        $this->postJson('/api/v1/auth/login', ['identifier' => 'other@test.com', 'password' => 'wrong'])->assertStatus(401);
    }

    public function test_web_login_is_blocked_after_five_attempts_and_shows_the_error_on_the_form(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', ['identifier' => 'x@test.com', 'password' => 'wrong']);
        }

        $this->from('/login')->post('/login', ['identifier' => 'x@test.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('throttle')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_reset_code_requests_are_limited_to_three_a_minute_per_account(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.com'])->status());
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.com'])->assertStatus(429);
    }

    public function test_writes_limiter_covers_every_form_but_not_reads_or_bank_webhooks(): void
    {
        $writes = RateLimiter::limiter('writes');

        $limit = $writes(Request::create('/anything', 'POST'));
        $this->assertSame(60, $limit->maxAttempts);
        $this->assertInstanceOf(Unlimited::class, $writes(Request::create('/anything', 'GET')));
    }

    public function test_platform_settings_are_read_from_cache_and_refreshed_after_an_update(): void
    {
        PlatformSetting::current();

        DB::enableQueryLog();
        PlatformSetting::current();
        $this->assertCount(0, DB::getQueryLog(), 'second read must come from the cache');

        PlatformSetting::current()->update(['commission_rate' => 17]);
        $this->assertSame(17, (int) PlatformSetting::current()->commission_rate);
    }

    public function test_category_changes_clear_the_categories_cache(): void
    {
        Category::create(['name' => 'Alpha', 'slug' => 'alpha', 'is_active' => true, 'sort_order' => 1]);
        $this->assertCount(1, AppCache::navCategories());
        $this->assertTrue(Cache::has(AppCache::NAV_CATEGORIES));

        Category::create(['name' => 'Beta', 'slug' => 'beta', 'is_active' => true, 'sort_order' => 2]);

        $this->assertCount(2, AppCache::navCategories());
        $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(2, 'data');
    }
}
