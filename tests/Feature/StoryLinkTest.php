<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\SellerProfile;
use App\Models\Story;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoryLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: SellerProfile, 2: Wallet} */
    private function approvedSeller(): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'متجر الرابط', 'status' => 'approved']);
        $wallet = Wallet::create(['vendor_id' => $profile->id, 'balance' => 5000]);
        PlatformSetting::updateOrCreate([], ['story_fee' => 1000, 'commission_rate' => 5]);

        return [$user, $profile, $wallet];
    }

    public function test_seller_web_story_saves_link(): void
    {
        Storage::fake('public');
        [$user, $profile] = $this->approvedSeller();

        $this->actingAs($user)->post(route('seller.stories.store'), [
            'image' => UploadedFile::fake()->image('s.jpg', 300, 600),
            'link_url' => 'https://example.com/products/jacket',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('stories', ['vendor_id' => $profile->id, 'link_url' => 'https://example.com/products/jacket']);
    }

    public function test_javascript_link_is_rejected_and_wallet_untouched(): void
    {
        Storage::fake('public');
        [$user, , $wallet] = $this->approvedSeller();

        $this->actingAs($user)->postJson('/api/v1/seller/stories', [
            'image' => UploadedFile::fake()->image('s.jpg', 300, 600),
            'link_url' => 'javascript:alert(1)',
        ])->assertStatus(422)->assertJsonValidationErrors('link_url');

        $this->assertSame(0, Story::count());
        $this->assertSame(5000, $wallet->fresh()->balance);
    }

    public function test_admin_api_story_saves_link_and_public_feed_returns_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson('/api/v1/admin/stories', [
            'image' => UploadedFile::fake()->image('s.jpg', 300, 600),
            'duration_hours' => 24,
            'link_url' => 'https://example.com/offers',
        ])->assertCreated();

        $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('data.0.link_url', 'https://example.com/offers')
            ->assertJsonPath('data.0.is_from_platform', true);
    }

    public function test_home_page_renders_unified_viewer_with_link(): void
    {
        Story::create([
            'vendor_id' => null,
            'image' => 'stories/a.jpg',
            'caption' => 'عرض',
            'link_url' => 'https://example.com/sale',
            'expires_at' => now()->addDay(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="tsStoryModal"', false)
            ->assertSee('data-ts-story="0"', false)
            ->assertSee('https:\/\/example.com\/sale', false);
    }
}
