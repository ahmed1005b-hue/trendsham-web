<?php

namespace Tests\Feature\Authorization;

use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CouponRedemption;
use App\Coupons\Models\CustomerCoupon;
use App\Models\Order;
use App\Models\Payment;
use App\Returns\Models\ReturnRequest;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * مصفوفة الوصول الأفقي لموقع الزبون (جلسة ويب): الزبون A يحاول قراءة/تعديل موارد الزبون B.
 */
class CustomerWebAccessTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** [method, uriTemplate, tenantKey, payload] */
    public const CASES = [
        ['DELETE', 'customer/cards/{card}', 'card', []],
        ['POST', 'customer/cards/{card}/default', 'card', []],
        ['GET', 'customer/cards/return/{card.reference}', 'card', []],
        ['DELETE', 'customer/cart/{cartItem}', 'cartItem', []],
        ['PUT', 'customer/cart/{cartItem}', 'cartItem', ['quantity' => 2]],
        ['DELETE', 'customer/questions/{question}', 'question', []],
        ['GET', 'customer/checkout/pay/{order}', 'order', []],
        ['POST', 'customer/checkout/pay/{order}', 'order', []],
        ['GET', 'customer/conversations/{conversation}', 'conversation', []],
        ['POST', 'customer/conversations/{conversation}/messages', 'conversation', ['body' => 'x']],
        ['GET', 'customer/orders/{order}', 'order', []],
        ['POST', 'customer/orders/{order}/cancel', 'order', []],
        ['POST', 'customer/orders/{order}/parcels/{shipment}/cancel', 'shipment', []],
        ['POST', 'customer/order-items/{orderItem}/review', 'orderItem', ['rating' => 5]],
        ['GET', 'customer/returns/{returnRequest}', 'returnRequest', []],
        ['POST', 'customer/returns/{returnRequest}/withdraw', 'returnRequest', []],
        ['POST', 'customer/returns/{returnRequest}/dispute', 'returnRequest', ['note' => 'x']],
        ['GET', 'customer/orders/{order}/returns/create', 'order', []],
        ['POST', 'customer/orders/{order}/returns', 'order', ['lines' => [], 'reason' => 'changed_mind']],
    ];

    /** البنك التجريبي: مرجع الدفع/البطاقة يخص B وهو معلّق فعلاً، فالملكية هي سبب الرفض الوحيد. */
    public const SANDBOX_CASES = [
        ['GET', 'sandbox-bank/payment/{payment.reference}', 'payment', []],
        ['POST', 'sandbox-bank/payment/{payment.reference}', 'payment', ['outcome' => 'approve']],
        ['GET', 'sandbox-bank/card/{card.reference}', 'card', []],
        ['POST', 'sandbox-bank/card/{card.reference}', 'card', ['outcome' => 'approve']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /** قيم تعرّف الزبون B ولا تظهر عند A. */
    private function identifyingValues(array $b): array
    {
        return [
            $b['order']->order_number, 'Hello b', 'Question b?', 'Customer b', 'Address b',
            $b['payment']->reference, $b['card']->reference, $b['card']->token, $b['customer']->email,
            $b['returnRequest']->number, 'Return b',
        ];
    }

    public function test_customer_cannot_touch_another_customers_resources(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['customer']);
        $values = $this->identifyingValues($b);

        foreach (self::CASES as [$method, $template, $key, $payload]) {
            $model = $b[$key];
            $before = $model->fresh()->getAttributes();
            $uri = $this->resolveUri($template, $b);
            $case = "$method $uri";

            $response = $this->call($method, $uri, $payload);
            $this->assertWebDenied($response, $case, $values, $uri);

            $after = $model->fresh();
            $this->assertNotNull($after, "$case: row was deleted");
            $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($after->getAttributes()), "$case: resource changed");
        }
    }

    public function test_positive_controls_owner_can_use_own_resources(): void
    {
        $a = $this->tenant('a');
        $this->actingAs($a['customer']);

        $this->get('/customer/orders/'.$a['order']->getRouteKey())->assertOk();
        $this->get('/customer/conversations/'.$a['conversation']->getRouteKey())->assertOk();
        // الطلب مدفوع: صفحة الدفع تحوّل لصفحة الطلب (النجاح الطبيعي)
        $this->get('/customer/checkout/pay/'.$a['order']->getRouteKey())->assertRedirect(route('customer.orders.show', $a['order']));
        $this->get('/customer/cards/return/'.$a['card']->reference)->assertRedirect(route('customer.cards.index'));
        $this->post('/customer/conversations/'.$a['conversation']->getRouteKey().'/messages', ['body' => 'hi'])->assertRedirect();
        $this->post('/customer/cards/'.$a['card']->getRouteKey().'/default')->assertRedirect();
        $this->put('/customer/cart/'.$a['cartItem']->getRouteKey(), ['quantity' => 2])->assertRedirect();
        $this->assertSame(2, $a['cartItem']->fresh()->quantity);

        $this->get('/customer/returns/'.$a['returnRequest']->getRouteKey())->assertOk();
        // الإرجاع المفتوح يحجز البند؛ بعد سحبه يصبح البند قابلاً للإرجاع من جديد.
        $this->get('/customer/orders/'.$a['order']->getRouteKey().'/returns/create')->assertNotFound();
        $this->post('/customer/returns/'.$a['returnRequest']->getRouteKey().'/withdraw')->assertRedirect();
        $this->assertSame('closed', $a['returnRequest']->fresh()->status);
        $this->get('/customer/orders/'.$a['order']->getRouteKey().'/returns/create')->assertOk();
        $this->post('/customer/orders/'.$a['order']->getRouteKey().'/returns', [
            'lines' => [$a['orderItem']->id => 1], 'reason' => 'changed_mind',
        ])->assertRedirect();
        $this->post('/customer/returns/'.ReturnRequest::latest('id')->value('id').'/withdraw')->assertRedirect();

        $a['order']->update(['status' => 'pending', 'payment_status' => 'unpaid']);
        $this->post('/customer/orders/'.$a['order']->getRouteKey().'/cancel')->assertRedirect();
        $this->assertSame('cancelled', $a['order']->fresh()->status);

        $this->delete('/customer/questions/'.$a['question']->getRouteKey())->assertRedirect();
        $this->assertNull($a['question']->fresh());
        $this->delete('/customer/cart/'.$a['cartItem']->getRouteKey())->assertRedirect();
        $this->assertNull($a['cartItem']->fresh());
        $this->delete('/customer/cards/'.$a['card']->getRouteKey())->assertRedirect();
    }

    public function test_card_return_page_of_another_customers_card_is_not_found(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['customer']);

        // رد التحويل مع رسالة فلاش لا يفضح الفرق في مصفوفة الرفض العامة، فنؤكد 404 صراحة.
        $this->get('/customer/cards/return/'.$b['card']->reference)->assertNotFound();
        $this->assertNull(session('success'));
    }

    /** يحوّل دفعة وبطاقة المستأجر إلى عمليتين معلّقتين على البنك التجريبي. */
    private function makeSandboxPending(array $tenant): void
    {
        $tenant['payment']->update(['gateway' => 'sandbox', 'status' => 'pending', 'paid_at' => null]);
        $tenant['card']->update(['gateway' => 'sandbox', 'status' => 'pending']);
    }

    public function test_sandbox_bank_denies_another_customers_pending_payment_and_card(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->makeSandboxPending($b);
        $this->actingAs($a['customer']);

        foreach (self::SANDBOX_CASES as [$method, $template, $key, $payload]) {
            $model = $b[$key];
            $before = $model->fresh()->getAttributes();
            $uri = $this->resolveUri($template, $b);
            $case = "$method $uri";

            $response = $this->call($method, $uri, $payload);
            $this->assertSame(403, $response->getStatusCode(), "$case: expected 403 from the ownership check");
            $this->assertWebDenied($response, $case, $this->identifyingValues($b), $uri);

            $after = $model->fresh();
            $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($after->getAttributes()), "$case: resource changed");
        }

        $this->assertSame('pending', $b['payment']->fresh()->status);
        $this->assertSame('pending', $b['card']->fresh()->status);
    }

    public function test_sandbox_bank_lets_the_owner_view_and_complete_own_pending_payment_and_card(): void
    {
        $a = $this->tenant('a');
        $this->makeSandboxPending($a);
        $this->actingAs($a['customer']);

        $this->get('/sandbox-bank/payment/'.$a['payment']->reference)->assertOk();
        $this->get('/sandbox-bank/card/'.$a['card']->reference)->assertOk();

        $this->post('/sandbox-bank/payment/'.$a['payment']->reference, ['outcome' => 'approve'])
            ->assertRedirect(route('payments.return', $a['payment']->reference));
        $this->assertSame('paid', $a['payment']->fresh()->status);

        $this->post('/sandbox-bank/card/'.$a['card']->reference, ['outcome' => 'approve', 'holder_name' => 'Owner A'])
            ->assertRedirect(route('customer.cards.return', $a['card']->reference));
        $this->assertSame('active', $a['card']->fresh()->status);
    }

    /** حملة منصة صالحة لأي سلة، حتى تكون الملكية هي سبب الرفض الوحيد. */
    private function platformCampaign(): CouponCampaign
    {
        return CouponCampaign::create([
            'funder' => 'platform', 'title' => 'Platform', 'type' => 'fixed', 'value' => 1000, 'scope' => 'all',
            'audience' => 'all_customers', 'starts_at' => now()->subDay(), 'per_customer_limit' => 1, 'status' => 'active',
        ]);
    }

    private function checkoutPayload(array $extra = []): array
    {
        return $extra + [
            'shipping_name' => 'A', 'shipping_phone' => '0911111111', 'shipping_address' => 'Addr', 'payment_method' => 'cash_on_delivery',
        ];
    }

    private function pendingOrderFor($user): Order
    {
        return Order::create([
            'order_number' => 'TS-PENDING-'.$user->id, 'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'A', 'shipping_phone' => '0911111111', 'shipping_address' => 'Addr',
        ]);
    }

    public function test_checkout_rejects_another_customers_coupon_and_card(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $campaign = $this->platformCampaign();
        $coupon = CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $b['customer']->id, 'status' => 'available', 'source' => 'test']);
        $this->actingAs($a['customer']);
        $orders = Order::count();

        // اختيار كوبون B عبر الواجهة مرفوض بسبب الملكية
        $this->post('/customer/checkout/coupon', ['choice' => (string) $coupon->id])
            ->assertRedirect()->assertSessionHas('error', 'هذا الكوبون غير موجود في محفظتك.');
        $this->assertNull(session('checkout_coupon'));

        // حتى لو زُوّرت الجلسة بمعرّف كوبون B، الطلب لا يُنفَّذ بخصمه (الكوبون صالح للسلة لولا الملكية)
        $response = $this->withSession(['checkout_coupon' => $coupon->id])->post('/customer/checkout', $this->checkoutPayload([
            'customer_coupon_id' => $coupon->id, 'card_id' => $b['card']->id,
        ]));
        $response->assertStatus(302)->assertSessionHas('error');
        $this->assertStringContainsString('الكوبون المختار غير موجود في محفظتك', (string) session('error'));

        $this->assertSame($orders, Order::count(), 'order was created with B coupon');
        $this->assertSame(0, Order::where('customer_coupon_id', $coupon->id)->count());
        $this->assertSame(0, Order::where('user_id', $a['customer']->id)->where('discount_amount', '>', 0)->count());
        $this->assertSame('available', $coupon->fresh()->status);
        $this->assertNull($coupon->fresh()->used_at);
        $this->assertSame(0, CouponRedemption::where('customer_coupon_id', $coupon->id)->count());

        $pending = $this->pendingOrderFor($a['customer']);
        $payments = Payment::count();
        $this->post('/customer/checkout/pay/'.$pending->getRouteKey(), ['card_id' => $b['card']->id])->assertNotFound();
        $this->assertSame($payments, Payment::count());
        $this->assertSame(0, Payment::where('customer_card_id', $b['card']->id)->count());
    }

    public function test_checkout_with_own_coupon_and_card_gets_past_the_ownership_checks(): void
    {
        $a = $this->tenant('a');
        $campaign = $this->platformCampaign();
        $coupon = CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $a['customer']->id, 'status' => 'available', 'source' => 'test']);
        $this->actingAs($a['customer']);

        $this->post('/customer/checkout/coupon', ['choice' => (string) $coupon->id])->assertSessionHas('success');
        $this->assertSame($coupon->id, session('checkout_coupon'));

        $this->post('/customer/checkout', $this->checkoutPayload())->assertSessionMissing('error');
        $order = Order::where('user_id', $a['customer']->id)->where('customer_coupon_id', $coupon->id)->first();
        $this->assertNotNull($order, 'own coupon was not applied');
        $this->assertGreaterThan(0, $order->discount_amount);
        $this->assertSame('used', $coupon->fresh()->status);
        $this->assertSame(1, CouponRedemption::where('customer_coupon_id', $coupon->id)->count());

        // نفس الطلب ببطاقة A نفسها يتجاوز بحث البطاقة (ليس 404)
        $pending = $this->pendingOrderFor($a['customer']);
        $response = $this->post('/customer/checkout/pay/'.$pending->getRouteKey(), ['card_id' => $a['card']->id]);
        $this->assertNotSame(404, $response->getStatusCode());
        $this->assertSame(1, Payment::where('customer_card_id', $a['card']->id)->count(), 'own card was not used');
    }
}
