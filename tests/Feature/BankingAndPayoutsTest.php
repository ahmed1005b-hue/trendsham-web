<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CustomerCard;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankingAndPayoutsTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'TR330006100519786457841326';

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox', 'payment.payout.driver' => 'manual', 'payment.payout.min_amount' => 1000]);
    }

    // ------------------------------------------------------------------ helpers

    private function seller(int $balanceMajor = 50000): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'متجر الاختبار', 'status' => 'approved', 'identity_verified_at' => now()]);
        $vendor->walletOrCreate()->update(['balance' => $balanceMajor * 100]);

        return [$user, $vendor->fresh()];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function addIban(User $user): PaymentMethod
    {
        $this->actingAs($user)->post(route('seller.wallet.payment-methods.add'), [
            'type' => 'bank_account',
            'provider' => 'Ziraat Bankası',
            'account_number' => 'TR33 0006 1005 1978 6457 8413 26',
            'account_holder_name' => 'أحمد البائع',
        ])->assertSessionHasNoErrors();

        return PaymentMethod::latest('id')->first();
    }

    private function order(User $customer): Order
    {
        return Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(6)),
            'user_id' => $customer->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 1500, 'discount_amount' => 0, 'total' => 11500,
            'shipping_name' => 'عميل', 'shipping_phone' => '0999999999', 'shipping_address' => 'دمشق',
        ]);
    }

    private function savedCard(User $customer, string $last4 = '4242'): CustomerCard
    {
        return $customer->customerCards()->create([
            'gateway' => 'sandbox', 'reference' => (string) Str::uuid(), 'token' => 'sbx_tok_x',
            'brand' => 'visa', 'last4' => $last4, 'exp_month' => 12, 'exp_year' => now()->year + 2,
            'status' => 'active', 'is_default' => true,
        ]);
    }

    // ------------------------------------------------------------------ طرق استلام البائع

    public function test_seller_payout_account_is_encrypted_and_never_returned_in_full(): void
    {
        [$user] = $this->seller();
        $method = $this->addIban($user);

        $this->assertSame(self::IBAN, $method->account_number);
        $this->assertSame('1326', $method->last4);
        $this->assertTrue($method->is_default, 'أول طريقة تصبح افتراضية تلقائياً');

        $raw = DB::table('payment_methods')->where('id', $method->id)->value('account_number');
        $this->assertStringNotContainsString('8413', $raw, 'الرقم يجب أن يُحفظ مشفّراً');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/seller/wallet/payment-methods')
            ->assertOk()
            ->assertJsonPath('payment_methods.0.masked_number', '•••• 1326')
            ->assertJsonMissingPath('payment_methods.0.account_number');
    }

    public function test_invalid_card_and_iban_numbers_are_rejected(): void
    {
        [$user] = $this->seller();

        $this->actingAs($user)->post(route('seller.wallet.payment-methods.add'), [
            'type' => 'bank_card', 'provider' => 'Visa', 'account_number' => '4111111111111112', 'account_holder_name' => 'أحمد',
        ])->assertSessionHasErrors('account_number');

        $this->actingAs($user)->post(route('seller.wallet.payment-methods.add'), [
            'type' => 'bank_account', 'provider' => 'Akbank', 'account_number' => 'TR330006100519786457841399', 'account_holder_name' => 'أحمد',
        ])->assertSessionHasErrors('account_number');

        $this->assertSame(0, PaymentMethod::count());
    }

    // ------------------------------------------------------------------ طلب السحب

    public function test_withdrawal_rules_min_amount_and_available_balance(): void
    {
        [$user, $vendor] = $this->seller(5000);

        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 2000])
            ->assertSessionHasErrors('amount'); // لا توجد طريقة استلام

        $this->addIban($user);

        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 500])->assertSessionHasErrors('amount'); // أقل من الحد
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 3000])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 3000])->assertSessionHasErrors('amount'); // تجاوز المتاح

        $this->assertSame(1, $vendor->withdrawalRequests()->count());
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/seller/wallet')
            ->assertOk()->assertJsonPath('available', 2000)->assertJsonPath('recent_withdrawals.0.status', 'pending');
    }

    // ------------------------------------------------------------------ التحويل اليدوي

    public function test_manual_payout_full_cycle_approve_then_paid_with_receipt(): void
    {
        Storage::fake('local');
        [$user, $vendor] = $this->seller(50000);
        $this->addIban($user);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.withdrawals.approve', $w))->assertRedirect();
        $w->refresh();
        $this->assertSame('approved', $w->status);
        $this->assertSame(30000 * 100, $vendor->wallet()->first()->balance);
        $this->assertDatabaseHas('ledger_entries', ['seller_id' => $user->id, 'entry_type' => 'payout', 'source_id' => $w->id]);

        // لا يمكن الموافقة مرتين
        $this->actingAs($admin)->post(route('admin.withdrawals.approve', $w))->assertStatus(409);

        $this->actingAs($admin)->post(route('admin.withdrawals.paid', $w), [
            'transfer_reference' => 'ZRT-2026-0001',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ])->assertRedirect();

        $w->refresh();
        $this->assertSame('paid', $w->status);
        $this->assertSame('ZRT-2026-0001', $w->transfer_reference);
        $this->assertNotNull($w->paid_at);
        Storage::disk('local')->assertExists($w->receipt_path);
        $this->assertTrue(AppNotification::where('user_id', $user->id)->where('type', 'withdrawal_paid')->exists());

        // الإيصال: للبائع صاحب الطلب نعم، لبائع آخر لا
        $this->actingAs($user)->get(route('withdrawals.receipt', $w))->assertOk();
        [$other] = $this->seller();
        $this->actingAs($other)->get(route('withdrawals.receipt', $w))->assertForbidden();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/seller/wallet/withdrawals')
            ->assertOk()->assertJsonPath('data.0.transfer_reference', 'ZRT-2026-0001')
            ->assertJsonMissingPath('data.0.payout_account_number');
    }

    public function test_failed_transfer_returns_money_to_seller_and_reverses_ledger(): void
    {
        [$user, $vendor] = $this->seller(50000);
        $this->addIban($user);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.withdrawals.approve', $w));
        $this->actingAs($admin)->post(route('admin.withdrawals.failed', $w), ['failure_reason' => 'IBAN مغلق لدى البنك']);

        $w->refresh();
        $this->assertSame('failed', $w->status);
        $this->assertSame(50000 * 100, $vendor->wallet()->first()->balance);
        $this->assertDatabaseHas('ledger_entries', ['seller_id' => $user->id, 'entry_type' => 'payout_reversal', 'source_id' => $w->id]);
        $this->assertDatabaseHas('wallet_transactions', ['withdrawal_request_id' => $w->id, 'reason' => 'withdrawal_reversal', 'type' => 'credit']);
    }

    public function test_reject_keeps_balance_and_notifies_seller(): void
    {
        [$user, $vendor] = $this->seller(50000);
        $this->addIban($user);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.withdrawals.reject', $w), ['admin_note' => 'بيانات الحساب ناقصة']);

        $this->assertSame('rejected', $w->fresh()->status);
        $this->assertSame(50000 * 100, $vendor->wallet()->first()->balance);
        $this->assertTrue(AppNotification::where('user_id', $user->id)->where('type', 'withdrawal_rejected')->exists());
    }

    public function test_api_admin_approval_also_posts_ledger_entry(): void
    {
        [$user] = $this->seller(50000);
        $this->addIban($user);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();

        $this->actingAs($this->admin(), 'sanctum')->postJson("/api/v1/admin/withdrawals/{$w->id}/approve")->assertOk()
            ->assertJsonPath('withdrawal.payout_account_number', self::IBAN);

        $this->assertSame(1, LedgerEntry::where('entry_type', 'payout')->where('source_id', $w->id)->count());
    }

    // ------------------------------------------------------------------ التحويل التلقائي

    public function test_sandbox_automatic_payout_marks_paid_on_approval(): void
    {
        config(['payment.payout.driver' => 'sandbox']);
        [$user] = $this->seller(50000);
        $this->addIban($user);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.withdrawals.approve', $w));

        $w->refresh();
        $this->assertSame('paid', $w->status);
        $this->assertStringStartsWith('SBX-TR-', $w->transfer_reference);
    }

    public function test_bank_payout_api_and_signed_webhook(): void
    {
        config([
            'payment.payout.driver' => 'bank',
            'payment.payout.bank.url' => 'https://bank.test',
            'payment.payout.bank.key' => 'k',
            'payment.payout.bank.webhook_secret' => 'whsec',
        ]);
        Http::fake(['bank.test/*' => Http::response(['status' => 'processing', 'transfer_id' => 'TRF-9'])]);

        [$user] = $this->seller(50000);
        $this->addIban($user);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.withdrawals.approve', $w));
        $this->assertSame('processing', $w->fresh()->status);
        Http::assertSent(fn ($req) => $req['destination']['account'] === self::IBAN && $req->hasHeader('Idempotency-Key', 'withdrawal-'.$w->id));

        $body = json_encode(['reference' => 'withdrawal-'.$w->id, 'status' => 'completed', 'transfer_id' => 'TRF-9']);
        $this->call('POST', '/api/v1/payouts/webhook/bank', [], [], [], ['HTTP_X_SIGNATURE' => 'bad', 'CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', '/api/v1/payouts/webhook/bank', [], [], [], [
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'whsec'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $this->assertSame('paid', $w->fresh()->status);
        $this->assertSame('TRF-9', $w->fresh()->transfer_reference);
    }

    // ------------------------------------------------------------------ بطاقات العملاء والدفع

    public function test_customer_saves_card_through_sandbox_bank(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $res = $this->actingAs($customer)->post(route('customer.cards.store'));
        $card = CustomerCard::firstOrFail();
        $res->assertRedirect(route('payments.sandbox.show', ['card', $card->reference]));
        $this->actingAs($customer)->get(route('payments.sandbox.show', ['card', $card->reference]))->assertOk()->assertSee('Sandbox Bank');

        $this->actingAs($customer)->post(route('payments.sandbox.complete', ['card', $card->reference]), [
            'outcome' => 'approve', 'card' => 'mc_ok', 'holder_name' => 'AHMED',
        ])->assertRedirect(route('customer.cards.return', $card->reference));

        $card->refresh();
        $this->assertSame('active', $card->status);
        $this->assertSame('4444', $card->last4);
        $this->assertTrue($card->is_default);
        $this->assertStringNotContainsString('sbx_tok', DB::table('customer_cards')->value('token'), 'التوكن مشفّر');

        $this->actingAs($customer)->get(route('customer.cards.index'))->assertOk()->assertSee('4444');
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/cards')->assertOk()
            ->assertJsonPath('data.0.last4', '4444')->assertJsonMissingPath('data.0.token');

        // لا يستطيع عميل آخر حذفها
        $other = User::factory()->create(['role' => 'customer']);
        $this->actingAs($other)->delete(route('customer.cards.destroy', $card))->assertForbidden();
    }

    public function test_pay_with_saved_card_succeeds_and_declined_card_fails(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $card = $this->savedCard($customer);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/payments/start', ['order_id' => $order->id, 'card_id' => $card->id])
            ->assertOk()->assertJsonPath('type', 'paid')->assertJsonPath('paid', true);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->fresh()->payment->status);

        $order2 = $this->order($customer);
        $declined = $this->savedCard($customer, '0002');
        $this->actingAs($customer)->from(route('customer.checkout.pay', $order2))
            ->post(route('customer.checkout.pay.process', $order2), ['card_id' => $declined->id])
            ->assertRedirect(route('customer.checkout.pay', $order2))->assertSessionHas('error');
        $this->assertSame('failed', $order2->fresh()->payment_status);
    }

    public function test_pay_new_card_via_sandbox_hosted_page(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);

        $res = $this->actingAs($customer)->post(route('customer.checkout.pay.process', $order));
        $payment = $order->payments()->latest('id')->first();
        $res->assertRedirect(route('payments.sandbox.show', ['payment', $payment->reference]));

        $this->actingAs($customer)->post(route('payments.sandbox.complete', ['payment', $payment->reference]), ['outcome' => 'approve', 'card' => 'visa_ok'])
            ->assertRedirect(route('payments.return', $payment->reference));

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->actingAs($customer)->get(route('payments.return', $payment->reference))->assertOk()->assertSee('عرض الطلب');
    }

    public function test_cannot_pay_with_someone_elses_card(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $card = $this->savedCard($other);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/payments/start', ['order_id' => $order->id, 'card_id' => $card->id])
            ->assertNotFound();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_real_bank_card_tokenization_webhook(): void
    {
        config([
            'payment.driver' => 'bank',
            'payment.bank.url' => 'https://bank.test',
            'payment.bank.key' => 'k',
            'payment.bank.webhook_secret' => 'whsec',
        ]);
        Http::fake(['bank.test/cards/tokenize' => Http::response(['checkout_url' => 'https://bank.test/hosted/abc'])]);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/cards')->assertCreated()->assertJsonPath('url', 'https://bank.test/hosted/abc');
        $card = CustomerCard::firstOrFail();

        $body = json_encode(['event' => 'card', 'reference' => $card->reference, 'status' => 'success',
            'card' => ['token' => 'tok_live_1', 'brand' => 'VISA', 'last4' => '1111', 'exp_month' => 7, 'exp_year' => 2030, 'holder_name' => 'A B']]);
        $this->call('POST', '/api/v1/payments/webhook/bank', [], [], [], [
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, 'whsec'), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $card->refresh();
        $this->assertSame('active', $card->status);
        $this->assertSame('tok_live_1', $card->token);
        $this->assertSame('visa', $card->brand);
        $this->assertSame('07/30', $card->expiry);
    }

    public function test_sandbox_bank_is_disabled_in_production(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $card = $customer->customerCards()->create(['gateway' => 'sandbox', 'reference' => (string) Str::uuid(), 'status' => 'pending']);

        $this->app['env'] = 'production';
        $this->actingAs($customer)->get(route('payments.sandbox.show', ['card', $card->reference]))->assertNotFound();
        $this->assertSame('manual', app(\App\Services\PaymentService::class)->gateway()->name());
    }
}
