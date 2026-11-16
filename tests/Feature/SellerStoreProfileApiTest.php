<?php

namespace Tests\Feature;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerStoreProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_update_store_details_and_upload_store_images(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'المتجر القديم',
            'status' => 'approved',
        ]);
        Sanctum::actingAs($seller);

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/seller/store', [
                '_method' => 'PUT',
                'store_name' => 'متجر الشام',
                'store_description' => 'منتجات مختارة بعناية.',
                'store_logo' => UploadedFile::fake()->image('logo.jpg', 320, 320),
                'store_banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
            ])
            ->assertOk()
            ->assertJsonPath('data.store_name', 'متجر الشام')
            ->assertJsonPath('data.store_description', 'منتجات مختارة بعناية.');

        $profile->refresh();
        $response
            ->assertJsonPath('data.store_logo', asset('storage/'.$profile->store_logo))
            ->assertJsonPath('data.store_banner', asset('storage/'.$profile->store_banner));
        $this->assertSame('متجر الشام', $profile->store_name);
        $this->assertSame('منتجات مختارة بعناية.', $profile->store_description);
        Storage::disk('public')->assertExists($profile->store_logo);
        Storage::disk('public')->assertExists($profile->store_banner);

        $this->getJson("/api/v1/stores/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('store.store_name', 'متجر الشام')
            ->assertJsonPath('store.store_description', 'منتجات مختارة بعناية.')
            ->assertJsonPath('store.store_logo', asset('storage/'.$profile->store_logo))
            ->assertJsonPath('store.store_banner', asset('storage/'.$profile->store_banner));
    }

    public function test_updating_store_text_without_new_images_keeps_existing_images(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'المتجر القديم',
            'store_logo' => 'stores/existing-logo.jpg',
            'store_banner' => 'stores/existing-banner.jpg',
            'status' => 'approved',
        ]);
        Sanctum::actingAs($seller);

        $this->putJson('/api/v1/seller/store', [
            'store_name' => 'اسم جديد',
            'store_description' => 'وصف جديد',
        ])->assertOk();

        $this->assertSame('stores/existing-logo.jpg', $profile->refresh()->store_logo);
        $this->assertSame('stores/existing-banner.jpg', $profile->store_banner);
    }

    public function test_partial_banner_replacement_keeps_logo_and_invalid_replacement_preserves_old_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('stores/old-logo.jpg', 'old logo');
        Storage::disk('public')->put('stores/old-banner.jpg', 'old banner');

        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر الصور',
            'store_logo' => 'stores/old-logo.jpg',
            'store_banner' => 'stores/old-banner.jpg',
            'status' => 'approved',
        ]);
        Sanctum::actingAs($seller);

        $this->post('/api/v1/seller/store', [
            '_method' => 'PUT',
            'store_name' => 'متجر الصور',
            'store_banner' => UploadedFile::fake()->image('new-banner.jpg', 1200, 400),
        ])->assertOk();

        $profile->refresh();
        $this->assertSame('stores/old-logo.jpg', $profile->store_logo);
        $this->assertNotSame('stores/old-banner.jpg', $profile->store_banner);
        Storage::disk('public')->assertExists($profile->store_logo);
        Storage::disk('public')->assertExists($profile->store_banner);

        $oldLogo = $profile->store_logo;
        $oldBanner = $profile->store_banner;
        $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/v1/seller/store', [
            '_method' => 'PUT',
            'store_name' => 'متجر الصور',
            'store_logo' => UploadedFile::fake()->create('invalid.jpg', 1, 'text/plain'),
            ])->assertUnprocessable();

        $this->assertSame($oldLogo, $profile->refresh()->store_logo);
        $this->assertSame($oldBanner, $profile->store_banner);
        Storage::disk('public')->assertExists($oldLogo);
        Storage::disk('public')->assertExists($oldBanner);
    }
}
