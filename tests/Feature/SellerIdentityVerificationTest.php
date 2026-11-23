<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerIdentityVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_seller_application_stores_document_privately_and_resubmission_replaces_it(): void
    {
        Storage::fake('identity_documents');
        $customer = User::factory()->create(['role' => 'customer']);
        // بائع مدعو بطلب قديم معلّق يوثّق هويته (فتح متجر جديد بدعوة فقط)
        SellerProfile::create(['user_id' => $customer->id, 'store_name' => 'متجر التحقق', 'status' => 'pending']);

        $this->actingAs($customer->fresh())
            ->post(route('customer.seller-application.store'), [
                'store_name' => 'متجر التحقق',
                'store_description' => 'وصف المتجر',
                'identity_document' => UploadedFile::fake()->image('identity.jpg'),
            ])
            ->assertRedirect(route('seller.dashboard'));

        $profile = SellerProfile::where('user_id', $customer->id)->firstOrFail();
        $oldPath = $profile->identity_document_path;
        Storage::disk('identity_documents')->assertExists($oldPath);
        $this->assertSame('pending', $profile->status);
        $this->assertNotNull($profile->identity_document_submitted_at);
        $this->assertSame('seller', $customer->fresh()->role);

        $profile->update(['status' => 'rejected', 'rejection_reason' => 'الصورة غير واضحة']);
        $this->actingAs($customer->fresh())
            ->post(route('seller.identity-verification.store'), [
                'store_name' => 'متجر التحقق',
                'identity_document' => UploadedFile::fake()->image('identity-retry.png'),
            ])
            ->assertRedirect(route('seller.dashboard'));

        $profile->refresh();
        $this->assertSame('pending', $profile->status);
        $this->assertNull($profile->rejection_reason);
        $this->assertNotSame($oldPath, $profile->identity_document_path);
        Storage::disk('identity_documents')->assertMissing($oldPath);
        Storage::disk('identity_documents')->assertExists($profile->identity_document_path);
    }

    public function test_self_sign_up_as_a_seller_is_no_longer_possible(): void
    {
        // طلب قديم معلّق بنوع «بائع»: يُنشأ حساب عميل فقط (فتح المتاجر بدعوة من الإدارة)
        $phone = '+963900001234';
        Cache::put('pending_registration:'.$phone, [
            'account_type' => 'seller',
            'first_name' => 'Seller',
            'last_name' => 'Test',
            'phone' => $phone,
            'email' => 'seller-identity@example.com',
            'address' => null,
            'password' => Hash::make('Password123!'),
            'store_name' => 'متجر جديد',
        ], now()->addMinutes(15));
        Otp::create([
            'phone' => $phone,
            'code' => '123456',
            'expires_at' => now()->addMinutes(5),
            'is_used' => false,
            'attempts' => 0,
        ]);

        $this->post('/api/v1/auth/register/verify', [
            'phone' => $phone,
            'code' => '123456',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('user.role', 'customer');

        $this->assertSame(0, SellerProfile::count());
    }

    public function test_admin_document_access_and_approval_are_protected_and_approved_legacy_sellers_are_grandfathered(): void
    {
        Storage::fake('identity_documents');
        $seller = User::factory()->create(['role' => 'seller']);
        $pending = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'بائع جديد',
            'status' => 'pending',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.sellers.approve', $pending))
            ->assertStatus(422);
        $this->assertSame('pending', $pending->fresh()->status);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/sellers/{$pending->id}/approve")
            ->assertUnprocessable();
        $this->assertSame('pending', $pending->fresh()->status);

        $documentPath = UploadedFile::fake()->image('identity.png')->store('seller-identities', 'identity_documents');
        $pending->update([
            'identity_document_path' => $documentPath,
            'identity_document_submitted_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get(route('admin.sellers.identity-document', $pending))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.sellers.identity-document', $pending))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        Sanctum::actingAs($seller);
        $this->get("/api/v1/admin/sellers/{$pending->id}/identity-document")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->get("/api/v1/admin/sellers/{$pending->id}/identity-document")
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/v1/admin/sellers')
            ->assertOk()
            ->assertJsonMissingPath('data.0.identity_document_path')
            ->assertJsonMissingPath('data.0.identity_document_url')
            ->assertJsonPath('data.0.has_identity_document', true);
        $this->postJson("/api/v1/admin/sellers/{$pending->id}/approve")->assertOk();
        $this->assertSame('approved', $pending->fresh()->status);

        $legacySeller = User::factory()->create(['role' => 'seller']);
        $legacyApproved = SellerProfile::create([
            'user_id' => $legacySeller->id,
            'store_name' => 'ط¨ط§ط¦ط¹ ظ‚ط¯ظٹظ…',
            'status' => 'approved',
        ]);
        $this->postJson("/api/v1/admin/sellers/{$legacyApproved->id}/approve")->assertOk();
        $this->assertSame('approved', $legacyApproved->fresh()->status);
    }

    public function test_admin_rejection_keeps_reason_and_identity_resubmission_returns_to_pending(): void
    {
        Storage::fake('identity_documents');
        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'ظ…طھط¬ط± ظ…ط±ظپظˆط¶',
            'status' => 'pending',
            'identity_document_path' => UploadedFile::fake()->image('first.jpg')->store('seller-identities', 'identity_documents'),
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/sellers/{$profile->id}/reject", [
            'reason' => 'الصورة غير واضحة',
        ])->assertOk();
        $this->assertSame('الصورة غير واضحة', $profile->fresh()->rejection_reason);

        Sanctum::actingAs($seller);
        $this->post('/api/v1/seller/identity-document', [
            'identity_document' => UploadedFile::fake()->image('retry.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $profile->refresh();
        $this->assertSame('pending', $profile->status);
        $this->assertNull($profile->rejection_reason);
        Storage::disk('identity_documents')->assertExists($profile->identity_document_path);
    }
}
