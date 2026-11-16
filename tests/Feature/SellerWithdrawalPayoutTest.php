<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerWithdrawalPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // هذه الاختبارات تختبر نسخ بيانات التحويل، لا الحد الأدنى للسحب
        config(['payment.payout.min_amount' => 0]);
    }

    public function test_withdrawal_requires_default_payment_method(): void
    {
        [$seller] = $this->createSellerWithWallet();
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/seller/wallet/withdraw', ['amount' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'أضف طريقة استلام واجعلها الافتراضية قبل طلب السحب.']);

        $this->assertDatabaseCount('withdrawal_requests', 0);
    }

    public function test_web_withdrawal_snapshots_the_default_payment_method(): void
    {
        [$seller, $profile] = $this->createSellerWithWallet();
        PaymentMethod::create([
            'vendor_id' => $profile->id,
            'type' => 'bank_card',
            'provider' => 'مصرف الشام',
            'account_number' => '1234567890',
            'account_holder_name' => 'صاحب الحساب',
            'is_default' => true,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.wallet.withdraw'), ['amount' => 100])
            ->assertRedirect();

        $withdrawal = WithdrawalRequest::firstOrFail();
        $this->assertSame('مصرف الشام', $withdrawal->payout_provider);
        $this->assertSame('1234567890', $withdrawal->payout_account_number);
        $this->assertSame('صاحب الحساب', $withdrawal->payout_account_holder_name);
    }

    public function test_withdrawal_snapshots_encrypted_payout_details_for_admin(): void
    {
        [$seller, $profile] = $this->createSellerWithWallet();
        $method = PaymentMethod::create([
            'vendor_id' => $profile->id,
            'type' => 'electronic_wallet',
            'provider' => 'محفظة شام',
            'account_number' => '0912345678',
            'account_holder_name' => 'اسم البائع',
            'is_default' => true,
        ]);
        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/seller/wallet/withdraw', ['amount' => 100])
            ->assertCreated();

        $withdrawal = WithdrawalRequest::firstOrFail();
        $storedNumber = \DB::table('withdrawal_requests')
            ->where('id', $withdrawal->id)
            ->value('payout_account_number');

        $this->assertNotSame('0912345678', $storedNumber);
        $this->assertSame('0912345678', $withdrawal->payout_account_number);

        $method->update([
            'account_number' => '0999999999',
            'account_holder_name' => 'اسم جديد',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/v1/admin/withdrawals')
            ->assertOk()
            ->assertJsonPath('data.0.payout_provider', 'محفظة شام')
            ->assertJsonPath('data.0.payout_account_number', '0912345678')
            ->assertJsonPath('data.0.payout_account_holder_name', 'اسم البائع');
    }

    public function test_admin_cannot_approve_legacy_request_without_payout_details(): void
    {
        [$seller, $profile] = $this->createSellerWithWallet();
        $withdrawal = WithdrawalRequest::create([
            'vendor_id' => $profile->id,
            'amount' => 10000,
            'status' => 'pending',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson("/api/v1/admin/withdrawals/{$withdrawal->id}/approve")
            ->assertConflict();

        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawal->id,
            'status' => 'pending',
        ]);
    }

    private function createSellerWithWallet(): array
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر البائع',
            'status' => 'approved',
        ]);
        Wallet::create(['vendor_id' => $profile->id, 'balance' => 100000]);

        return [$seller, $profile];
    }
}
