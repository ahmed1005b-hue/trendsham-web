<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Payments\Contracts\WebhookEvent;
use App\Services\PaymentService;
use App\Showcases\WalletBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** اختبارات لإصلاحات ما كشفته مراجعة الكود (/code-review). */
class CodeReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $customer): Order
    {
        return Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(6)), 'user_id' => $customer->id,
            'status' => 'pending', 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
    }

    private function seller(int $balance = 0): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'S', 'status' => 'approved', 'commission_rate' => 10]);
        $vendor->walletOrCreate()->update(['balance' => $balance]);

        return [$user, $vendor->fresh()];
    }

    public function test_real_checkout_flow_card_payment_is_recorded(): void
    {
        // المسار الحقيقي: صفحة الدفع تُنشئ دفعة "معلّقة" أولاً، ثم تبدأ دفعة البنك.
        config(['payment.driver' => 'sandbox']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        Payment::create(['order_id' => $order->id, 'method' => 'card', 'status' => 'pending', 'amount' => $order->total]);

        $this->actingAs($customer)->post(route('customer.checkout.pay.process', $order));
        $payment = $order->payments()->latest('id')->first();
        $this->actingAs($customer)->post(route('payments.sandbox.complete', ['payment', $payment->reference]), ['outcome' => 'approve', 'card' => 'visa_ok']);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('cancelled', Payment::where('order_id', $order->id)->whereNull('reference')->value('status'));
    }

    public function test_sandbox_bank_cannot_complete_a_real_bank_payment(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $real = Payment::create(['order_id' => $order->id, 'gateway' => 'bank', 'reference' => (string) Str::uuid(), 'method' => 'card', 'status' => 'pending', 'amount' => 10000, 'currency' => 'SYP']);

        $this->actingAs($customer)->post(route('payments.sandbox.complete', ['payment', $real->reference]), ['outcome' => 'approve'])
            ->assertNotFound();
        $this->assertSame('pending', $real->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_sandbox_cancel_outcome_is_stored(): void
    {
        config(['payment.driver' => 'sandbox']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        $this->actingAs($customer)->post(route('customer.checkout.pay.process', $order));
        $payment = $order->payments()->latest('id')->first();

        $this->actingAs($customer)->post(route('payments.sandbox.complete', ['payment', $payment->reference]), ['outcome' => 'cancel']);

        $this->assertSame('cancelled', $payment->fresh()->status);
    }

    public function test_second_paid_webhook_for_paid_order_is_flagged_duplicate_not_silently_paid(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        User::factory()->create(['role' => 'admin']);
        $order = $this->order($customer);
        $a = Payment::create(['order_id' => $order->id, 'gateway' => 'bank', 'reference' => (string) Str::uuid(), 'method' => 'card', 'status' => 'pending', 'amount' => 10000, 'currency' => 'SYP']);
        $b = Payment::create(['order_id' => $order->id, 'gateway' => 'bank', 'reference' => (string) Str::uuid(), 'method' => 'card', 'status' => 'pending', 'amount' => 10000, 'currency' => 'SYP']);
        $svc = app(PaymentService::class);

        $svc->apply($a->reference, new WebhookEvent($a->reference, 'paid', 'TX-A', 10000));
        $svc->apply($b->reference, new WebhookEvent($b->reference, 'paid', 'TX-B', 10000));

        $this->assertSame('paid', $a->fresh()->status);
        $this->assertSame('duplicate', $b->fresh()->status);
        $this->assertSame('TX-B', $b->fresh()->gateway_txn_id);
        $this->assertTrue(AppNotification::where('type', 'payment_duplicate')->exists());
    }

    public function test_bank_5xx_does_not_refund_seller_and_unknown_status_is_not_failure(): void
    {
        config([
            'payment.payout.driver' => 'bank', 'payment.payout.min_amount' => 0,
            'payment.payout.bank.url' => 'https://bank.test', 'payment.payout.bank.key' => 'k',
        ]);
        Http::fakeSequence('bank.test/*')
            ->push(['message' => 'Bad gateway'], 502)
            ->push(['status' => 'in_transit', 'transfer_id' => 'T-1']);

        [$user, $vendor] = $this->seller(5_000_000);
        PaymentMethod::create(['vendor_id' => $vendor->id, 'type' => 'electronic_wallet', 'provider' => 'x', 'account_number' => '0933123456', 'account_holder_name' => 'abc', 'is_default' => true]);
        $this->actingAs($user)->post(route('seller.wallet.withdraw'), ['amount' => 20000]);
        $w = WithdrawalRequest::firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.withdrawals.approve', $w));
        $w->refresh();
        $this->assertSame('approved', $w->status, 'خطأ 502 غامض: يبقى الطلب بانتظار التحويل');
        $this->assertSame(3_000_000, $vendor->wallet()->first()->balance, 'ولا يُعاد المبلغ للبائع');
        $this->assertNotNull($w->failure_reason);

        $this->actingAs($admin)->post(route('admin.withdrawals.send', $w));
        $this->assertSame('processing', $w->fresh()->status, 'حالة غير معروفة = قيد التحويل وليست فشلاً');
    }

    public function test_showcase_wallet_reasons_can_be_stored(): void
    {
        [$user, $vendor] = $this->seller(1_000_000);
        $bridge = app(WalletBridge::class);

        $bridge->debit($user, 100, 'showcase_booking');
        $bridge->credit($user, 100, 'showcase_refund');

        $this->assertDatabaseHas('wallet_transactions', ['reason' => 'showcase_booking']);
        $this->assertDatabaseHas('wallet_transactions', ['reason' => 'showcase_refund']);
    }

    public function test_slots_availability_route_is_not_swallowed_by_showcase_wildcard(): void
    {
        $this->assertSame('seller.showcases.slots-availability',
            app('router')->getRoutes()->match(request()->create('/seller/showcases/slots-availability', 'GET'))->getName());
    }

    public function test_a_delivered_order_keeps_earnings_from_the_app_and_a_model_cancel_reverses_them_once(): void
    {
        [$sellerUser, $vendor] = $this->seller();
        $category = Category::create(['name' => 'c', 'slug' => 'c']);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'p', 'slug' => 'p', 'sku' => 'P1', 'price' => 10000, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now()]);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $vendor->id, 'product_name' => 'p', 'unit_price' => 10000, 'quantity' => 1, 'subtotal' => 10000]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'delivered'])->assertOk();
        $this->assertSame(9000, $vendor->wallet()->first()->balance);

        // من التطبيق: الطلب المُسلَّم لا يُلغى (البضاعة عند الزبون، والإرجاع له طريقه) فتبقى الأرباح
        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'cancelled', 'reason' => 'سبب الاختبار'])
            ->assertUnprocessable();
        $this->assertSame(9000, $vendor->wallet()->first()->balance);

        // إلغاء يمر بالنموذج (رفض استلام مثلاً) يعكس الأرباح والقيود مرة واحدة فقط
        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(0, $vendor->wallet()->first()->balance, 'أرباح الطلب المسترجع تُخصم من المحفظة');
        $this->assertDatabaseHas('ledger_entries', ['seller_id' => $sellerUser->id, 'entry_type' => 'refund']);
        $order->fresh()->changeStatus('cancelled');
        $this->assertSame(0, $vendor->wallet()->first()->balance);
    }
}
