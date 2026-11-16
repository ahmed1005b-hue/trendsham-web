<?php

namespace Tests\Feature;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerStoreProfileWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_edit_storefront_on_web_and_customers_see_it(): void
    {
        Storage::fake('public');

        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'المتجر القديم',
            'status' => 'approved',
        ]);
        $this->actingAs($seller);

        $this->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee(route('seller.store.edit'), false);

        $this->get(route('seller.store.edit'))
            ->assertOk()
            ->assertSee('name="store_logo"', false)
            ->assertSee('name="store_banner"', false)
            ->assertSee('name="store_contact_phone"', false)
            ->assertSee('512×512')
            ->assertSee('1200×400')
            ->assertSee('معاينة المتجر')
            ->assertSee(route('stores.show', $profile), false);

        $this->from(route('seller.store.edit'))
            ->post(route('seller.profile.update'), [
                '_method' => 'PUT',
                'store_name' => 'متجر ويب',
                'store_description' => 'نبذة تعرف المشترين على المتجر.',
                'store_contact_phone' => '+963 999 123 456',
                'store_whatsapp' => '+963 999 123 456',
                'store_instagram' => 'https://instagram.com/store',
                'business_hours' => 'يومياً',
                'shipping_policy' => 'سياسة الشحن',
                'return_policy' => 'سياسة الإرجاع',
                'store_logo' => UploadedFile::fake()->image('logo.jpg', 320, 320),
                'store_banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
            ])
            ->assertRedirect(route('seller.store.edit'))
            ->assertSessionHas('success', 'تم تحديث بيانات المتجر بنجاح.');

        $profile->refresh();
        Storage::disk('public')->assertExists($profile->store_logo);
        Storage::disk('public')->assertExists($profile->store_banner);

        $this->get(route('stores.show', $profile))
            ->assertOk()
            ->assertSee('متجر ويب')
            ->assertSee('نبذة تعرف المشترين على المتجر.')
            ->assertSee('سياسة الشحن')
            ->assertSee(asset('storage/'.$profile->store_logo), false)
            ->assertSee(asset('storage/'.$profile->store_banner), false);
    }

    public function test_web_profile_update_keeps_existing_images_when_no_replacement_is_uploaded(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('stores/existing-logo.jpg', 'logo');
        Storage::disk('public')->put('stores/existing-banner.jpg', 'banner');

        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'المتجر القديم',
            'store_logo' => 'stores/existing-logo.jpg',
            'store_banner' => 'stores/existing-banner.jpg',
            'status' => 'approved',
        ]);

        $this->actingAs($seller)
            ->from(route('seller.dashboard'))
            ->put(route('seller.profile.update'), [
                'store_name' => 'اسم جديد',
                'store_description' => 'وصف جديد',
            ])
            ->assertRedirect(route('seller.dashboard'));

        $this->assertSame('stores/existing-logo.jpg', $profile->refresh()->store_logo);
        $this->assertSame('stores/existing-banner.jpg', $profile->store_banner);
        Storage::disk('public')->assertExists('stores/existing-logo.jpg');
        Storage::disk('public')->assertExists('stores/existing-banner.jpg');
    }

    public function test_partial_logo_replacement_keeps_banner_and_rejected_image_keeps_both_old_files(): void
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

        $this->actingAs($seller)
            ->from(route('seller.dashboard'))
            ->put(route('seller.profile.update'), [
                'store_name' => 'متجر الصور',
                'store_logo' => UploadedFile::fake()->image('new-logo.jpg', 320, 320),
            ])
            ->assertRedirect(route('seller.dashboard'));

        $profile->refresh();
        $this->assertNotSame('stores/old-logo.jpg', $profile->store_logo);
        $this->assertSame('stores/old-banner.jpg', $profile->store_banner);
        Storage::disk('public')->assertExists($profile->store_logo);
        Storage::disk('public')->assertExists('stores/old-banner.jpg');

        $this->from(route('seller.dashboard'))
            ->put(route('seller.profile.update'), [
                'store_name' => 'متجر الصور',
                'store_logo' => UploadedFile::fake()->create('invalid.jpg', 1, 'text/plain'),
            ])
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionHasErrors('store_logo');

        $this->assertSame($profile->store_logo, $profile->refresh()->store_logo);
        $this->assertSame('stores/old-banner.jpg', $profile->store_banner);
        Storage::disk('public')->assertExists($profile->store_logo);
        Storage::disk('public')->assertExists('stores/old-banner.jpg');
    }
}
