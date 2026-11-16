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

class SellerStoryWebTest extends TestCase
{
    use RefreshDatabase;

    private function setStoryFee(int $fee): void
    {
        PlatformSetting::updateOrCreate([], ['story_fee' => $fee, 'commission_rate' => 5]);
    }

    private function makeApprovedSeller(int $balance = 5000): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $user->id,
            'store_name' => 'متجر الاختبار',
            'status' => 'approved',
        ]);
        $wallet = Wallet::create(['vendor_id' => $profile->id, 'balance' => $balance]);
        $this->setStoryFee(1000);

        return [$user, $profile, $wallet];
    }

    private function actingAsSeller(array $seller): static
    {
        [$user] = $seller;

        return $this->actingAs($user);
    }

    public function test_index_shows_stories_page(): void
    {
        [$user, $profile, $wallet] = $this->makeApprovedSeller();

        $this->actingAs($user)->get(route('seller.stories.index'))
            ->assertOk()
            ->assertSee($wallet->formatted_balance, false);
    }

    public function test_create_shows_form(): void
    {
        [$user] = $this->makeApprovedSeller();

        $this->actingAs($user)->get(route('seller.stories.create'))
            ->assertOk();
    }

    public function test_store_publishes_story_and_debits_wallet(): void
    {
        Storage::fake('public');
        [$user, $profile, $wallet] = $this->makeApprovedSeller(balance: 5000);

        $resp = $this->actingAs($user)->post(route('seller.stories.store'), [
            'image' => UploadedFile::fake()->image('story.jpg', 300, 600),
            'caption' => 'عرض اليوم',
        ]);

        $resp->assertSessionHas('success');

        $this->assertDatabaseHas('stories', [
            'vendor_id' => $profile->id,
            'caption' => 'عرض اليوم',
            'amount_paid' => 1000,
        ]);
        $this->assertSame(4000, $wallet->fresh()->balance);
    }

    public function test_store_rejects_when_balance_insufficient(): void
    {
        [$user] = $this->makeApprovedSeller(balance: 500);

        $resp = $this->actingAs($user)->post(route('seller.stories.store'), [
            'image' => UploadedFile::fake()->image('story.jpg', 300, 600),
            'caption' => 'عرض اليوم',
        ]);

        $resp->assertSessionHas('error');

        $this->assertDatabaseCount('stories', 0);
    }

    public function test_unapproved_seller_cannot_store(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        SellerProfile::create([
            'user_id' => $user->id,
            'store_name' => 'متجري',
            'status' => 'pending',
        ]);
        $this->setStoryFee(1000);

        $resp = $this->actingAs($user)->post(route('seller.stories.store'), [
            'image' => UploadedFile::fake()->image('story.jpg', 300, 600),
        ]);

        $resp->assertForbidden();

        $this->assertDatabaseCount('stories', 0);

        // Restore the default fee so it doesn't leak into other tests.
        PlatformSetting::query()->update(['story_fee' => 1000, 'commission_rate' => 10]);
    }

    public function test_destroy_hides_own_story_only(): void
    {
        [$user, $profile] = $this->makeApprovedSeller();
        $story = Story::create([
            'vendor_id' => $profile->id,
            'image' => 'stories/x.jpg',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->delete(route('seller.stories.destroy', $story))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse($story->fresh()->is_active);

        // بائع آخر لا يملك الحذف
        $other = User::factory()->create(['role' => 'seller']);
        SellerProfile::create([
            'user_id' => $other->id,
            'store_name' => 'متجرك',
            'status' => 'approved',
        ]);
        $story2 = Story::create([
            'vendor_id' => $profile->id,
            'image' => 'stories/y.jpg',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $this->actingAs($other)->delete(route('seller.stories.destroy', $story2))
            ->assertForbidden();
    }
}
