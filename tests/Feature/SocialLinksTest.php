<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** روابط حسابات التواصل: يكتبها المدير في الإعدادات وتظهر أيقوناتها أسفل الموقع. */
class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    private function save(array $links): TestResponse
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return $this->actingAs($admin)->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), ['commission_rate' => 10, 'story_fee' => 5000] + $links);
    }

    public function test_footer_shows_no_social_icons_until_links_are_set(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('ts-footer__brand', false)
            ->assertDontSee('ts-footer__social', false)
            ->assertDontSee('bi-tiktok', false)
            ->assertDontSee('bi-twitter-x', false)
            ->assertDontSee('href="#" aria-label="إنستغرام"', false);
    }

    public function test_admin_saves_links_and_only_filled_ones_appear_in_the_footer(): void
    {
        $this->save([
            'social_instagram' => 'https://www.instagram.com/trendsham',
            'social_tiktok' => 'https://www.tiktok.com/@trendsham',
            'social_x' => 'https://x.com/trendsham',
            'social_facebook' => '',
            'social_whatsapp' => '0912 345 678',
        ])->assertSessionHasNoErrors();

        $this->assertSame('963912345678', PlatformSetting::current()->social_whatsapp);

        auth()->logout();
        $this->get(route('home'))->assertOk()
            ->assertSee('href="https://www.instagram.com/trendsham"', false)
            ->assertSee('href="https://www.tiktok.com/@trendsham"', false)
            ->assertSee('href="https://x.com/trendsham"', false)
            ->assertSee('href="https://wa.me/963912345678"', false)
            ->assertSee('bi-tiktok', false)
            ->assertSee('bi-twitter-x', false)
            ->assertDontSee('bi-facebook', false);
    }

    public function test_wrong_or_unsafe_links_are_refused(): void
    {
        $this->save([
            'social_tiktok' => 'https://www.instagram.com/trendsham',
            'social_instagram' => 'javascript:alert(1)',
            'social_x' => 'https://evil-x.com/trendsham',
        ])->assertSessionHasErrors(['social_tiktok', 'social_instagram', 'social_x']);

        $this->assertNull(PlatformSetting::current()->social_tiktok);
    }

    public function test_old_twitter_links_are_accepted_for_x(): void
    {
        $this->save(['social_x' => 'https://twitter.com/trendsham'])->assertSessionHasNoErrors();
        $this->assertSame('https://twitter.com/trendsham', PlatformSetting::current()->social_x);
    }
}
