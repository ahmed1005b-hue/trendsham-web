<?php

namespace Tests\Feature;

use App\GiftCards\GiftCardService;
use App\GiftCards\Models\GiftBalanceTransaction;
use App\GiftCards\Models\GiftCard;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\User;
use App\Returns\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/** بطاقات الهدايا: الشراء بالبنك، الإدخال، الدفع بالرصيد، وإرجاعه عند الإلغاء. */
class GiftCardTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'sandbox']);
    }

    private function gifts(): GiftCardService
    {
        return app(GiftCardService::class);
    }

    /** يشتري بطاقة عبر البنك التجريبي ويعيدها بعد الدفع. */
    private function buy(User $buyer, int $lira = 50000, string $outcome = 'approve'): GiftCard
    {
        $response = $this->actingAs($buyer)->post(route('customer.gift-cards.purchase'), ['amount' => $lira, 'recipient_name' => 'Sam']);
        $card = GiftCard::where('purchaser_id', $buyer->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('payments.sandbox.show', ['gift', $card->reference]));

        $this->actingAs($buyer)->get(route('payments.sandbox.show', ['gift', $card->reference]))->assertOk();
        $this->actingAs($buyer)->post(route('payments.sandbox.complete', ['gift', $card->reference]), ['outcome' => $outcome])
            ->assertRedirect(route('gift-cards.return', $card->reference));

        return $card->fresh();
    }

    private function codeOf(GiftCard $card): string
    {
        return GiftCardService::format($card->code);
    }

    public function test_paid_card_gets_a_code_and_the_buyer_is_notified(): void
    {
        $buyer = $this->tenant('a')['customer'];

        $card = $this->buy($buyer);

        $this->assertSame(GiftCard::ACTIVE, $card->status);
        $this->assertSame(5000000, $card->amount);
        $this->assertMatchesRegularExpression('/^TS-[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $this->codeOf($card));
        $this->assertTrue($card->expires_at->isAfter(now()->addMonths(11)));
        $this->assertTrue(AppNotification::where('user_id', $buyer->id)->where('type', 'gift_card_ready')->exists());
        $this->actingAs($buyer)->get(route('customer.gift-cards.index'))->assertOk()->assertSee($this->codeOf($card));
    }

    public function test_declined_payment_creates_no_code(): void
    {
        $card = $this->buy($this->tenant('a')['customer'], outcome: 'decline');

        $this->assertSame(GiftCard::FAILED, $card->status);
        $this->assertNull($card->code_hash);
    }

    public function test_amount_limits_and_manual_gateway_are_refused(): void
    {
        $buyer = $this->tenant('a')['customer'];
        $before = GiftCard::count();
        $this->actingAs($buyer)->post(route('customer.gift-cards.purchase'), ['amount' => 500])->assertSessionHasErrors('amount');

        config(['payment.driver' => 'manual']);
        $this->actingAs($buyer, 'sanctum')->postJson('/api/v1/gift-cards/purchase', ['amount' => 50000])->assertStatus(422);
        $this->assertSame($before, GiftCard::count());
    }

    public function test_friend_redeems_once_and_the_buyer_sees_it(): void
    {
        $buyer = $this->tenant('a')['customer'];
        $friend = $this->tenant('b')['customer'];
        $card = $this->buy($buyer);
        $code = strtolower(str_replace('-', ' ', $this->codeOf($card))); // يُقبل بأي شكل كتابة

        $this->actingAs($friend, 'sanctum')->postJson('/api/v1/gift-cards/redeem', ['code' => $code])
            ->assertOk()->assertJsonPath('balance', 50000);
        $this->actingAs($friend, 'sanctum')->postJson('/api/v1/gift-cards/redeem', ['code' => $code])
            ->assertStatus(422)->assertJsonPath('message', 'هذه البطاقة استُخدمت من قبل.');
        $this->actingAs($friend, 'sanctum')->postJson('/api/v1/gift-cards/redeem', ['code' => 'TS-AAAA-BBBB-CCCC'])
            ->assertStatus(422)->assertJsonPath('message', 'كود بطاقة الهدية غير صحيح.');

        $this->assertSame(5000000, $this->gifts()->balance($friend->id));
        $this->assertTrue(AppNotification::where('user_id', $buyer->id)->where('type', 'gift_card_redeemed')->exists());
        $this->actingAs($buyer, 'sanctum')->getJson('/api/v1/gift-cards')
            ->assertOk()->assertJsonPath('cards.0.status', GiftCard::REDEEMED)->assertJsonPath('cards.0.code', null);
    }

    public function test_expired_card_cannot_be_redeemed(): void
    {
        $card = $this->buy($this->tenant('a')['customer']);
        $code = $this->codeOf($card);
        $card->update(['expires_at' => now()->subDay()]);

        $this->expectExceptionMessage('انتهت صلاحية هذه البطاقة.');
        $this->gifts()->redeem($this->tenant('b')['customer'], $code);
    }

    public function test_other_customers_cannot_see_a_card(): void
    {
        $card = $this->buy($this->tenant('a')['customer']);

        $this->actingAs($this->tenant('b')['customer'], 'sanctum')->getJson('/api/v1/gift-cards/'.$card->id)->assertNotFound();
    }

    private function checkoutPayload(string $method = 'cash_on_delivery'): array
    {
        return ['shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
            'payment_method' => $method, 'cod_confirmed' => true, 'use_gift_balance' => '1'];
    }

    public function test_checkout_pays_part_with_gift_balance_and_cancel_returns_it(): void
    {
        $a = $this->tenant('a');
        $customer = $a['customer'];
        // سلة بـ 15,000 ل.س + شحن 150 ل.س، ورصيد 10,000 ل.س
        $a['product']->update(['price' => 300000]);
        $a['cartItem']->update(['quantity' => 5, 'unit_price' => 300000, 'original_unit_price' => 300000]);
        $this->gifts()->redeem($customer, $this->codeOf($this->buy($this->tenant('b')['customer'], 10000)));

        $this->actingAs($customer)->post(route('customer.checkout.store'), $this->checkoutPayload())->assertRedirect();

        $order = Order::where('user_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame(1000000, (int) $order->gift_balance_used);
        $this->assertSame(1500000 + 15000 - 1000000, (int) $order->total);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(0, $this->gifts()->balance($customer->id));

        $order->changeStatus('cancelled');
        $order->changeStatus('cancelled');
        $this->assertSame(1000000, $this->gifts()->balance($customer->id));
        $this->assertSame(1, GiftBalanceTransaction::where('order_id', $order->id)->where('type', 'refund')->count());
    }

    public function test_order_fully_covered_by_gift_balance_is_paid_and_skips_the_bank(): void
    {
        $a = $this->tenant('a');
        $customer = $a['customer'];
        $this->gifts()->redeem($customer, $this->codeOf($this->buy($this->tenant('b')['customer'])));

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/checkout', $this->checkoutPayload('card'))
            ->assertCreated()->assertJsonPath('data.gift_balance_used', 250);

        $order = Order::where('user_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame(0, (int) $order->total);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(5000000 - 25000, $this->gifts()->balance($customer->id));
    }

    public function test_balance_is_not_used_unless_asked(): void
    {
        $a = $this->tenant('a');
        $this->gifts()->redeem($a['customer'], $this->codeOf($this->buy($this->tenant('b')['customer'])));

        $payload = $this->checkoutPayload();
        unset($payload['use_gift_balance']);
        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $payload)->assertRedirect();

        $this->assertSame(0, (int) Order::where('user_id', $a['customer']->id)->latest('id')->value('gift_balance_used'));
        $this->assertSame(5000000, $this->gifts()->balance($a['customer']->id));
    }

    public function test_returned_item_can_be_refunded_to_gift_balance_up_to_what_it_paid(): void
    {
        $a = $this->tenant('a');
        $admin = User::factory()->create(['role' => 'admin']);
        $a['order']->update(['gift_balance_used' => 6000]); // 60 من 100 ل.س دُفعت بالرصيد
        $a['returnRequest']->update(['status' => 'received', 'refund_amount' => 10000]);
        $returns = app(ReturnService::class);

        try {
            $returns->recordRefund($admin, $a['returnRequest']->fresh(), 10000, 'gift_balance', null, null);
            $this->fail('refund above the gift-paid part must be refused');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('أكبر مما دُفع برصيد الهدايا', $e->getMessage());
        }
        $this->assertSame(0, $this->gifts()->balance($a['customer']->id));

        $returns->recordRefund($admin, $a['returnRequest']->fresh(), 6000, 'gift_balance', null, null);
        $this->assertSame(6000, $this->gifts()->balance($a['customer']->id));
        $this->assertSame(0, $this->gifts()->refundableForOrder($a['order']->fresh()));

        // إلغاء الطلب بعدها لا يعيد الرصيد مرة ثانية
        $this->gifts()->refundForOrder($a['order']->fresh());
        $this->assertSame(6000, $this->gifts()->balance($a['customer']->id));
    }

    public function test_admin_sees_sold_and_owed_totals_but_not_codes(): void
    {
        $friend = $this->tenant('a')['customer'];
        $sold = $this->buy($this->tenant('b')['customer'], 20000);
        $this->gifts()->redeem($friend, $this->codeOf($sold));
        $unused = $this->buy($this->tenant('c')['customer'], 30000);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.gift-cards.index'))->assertOk();

        // كل مستأجر يُنشئ بطاقة 50,000 ل.س غير مستخدمة (3 مستأجرين)
        $response->assertSee('200,000 ل.س', false)  // بيعت: 150,000 + 20,000 + 30,000
            ->assertSee('20,000 ل.س', false)            // رصيد العميل
            ->assertDontSee($this->codeOf($unused));
        $this->actingAs($friend)->get(route('admin.gift-cards.index'))->assertForbidden();
    }
}
