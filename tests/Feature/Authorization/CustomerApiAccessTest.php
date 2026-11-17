<?php

namespace Tests\Feature\Authorization;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * مصفوفة الوصول الأفقي: الزبون A يحاول قراءة/تعديل موارد الزبون B عبر الـ API.
 */
class CustomerApiAccessTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** [method, uriTemplate, tenantKey, payload] */
    public const CASES = [
        ['DELETE', 'api/v1/cards/{card}', 'card', []],
        ['POST', 'api/v1/cards/{card}/default', 'card', []],
        ['GET', 'api/v1/cards/status/{card.reference}', 'card', []],
        ['DELETE', 'api/v1/cart/{cartItem}', 'cartItem', []],
        ['PUT', 'api/v1/cart/{cartItem}', 'cartItem', ['quantity' => 2]],
        ['DELETE', 'api/v1/profile/addresses/{address}', 'address', []],
        ['PUT', 'api/v1/profile/addresses/{address}', 'address', ['label' => 'work', 'city' => 'Hacked', 'details' => 'Hacked']],
        ['POST', 'api/v1/profile/addresses/{address}/default', 'address', []],
        ['DELETE', 'api/v1/questions/{question}', 'question', []],
        ['GET', 'api/v1/conversations/{conversation}', 'conversation', []],
        ['POST', 'api/v1/conversations/{conversation}/messages', 'conversation', ['body' => 'x']],
        ['GET', 'api/v1/orders/{order}', 'order', []],
        ['GET', 'api/v1/orders/{order}/invoice', 'order', []],
        ['GET', 'api/v1/orders/{order}/items', 'order', []],
        ['GET', 'api/v1/orders/{order}/payment-status', 'order', []],
        ['POST', 'api/v1/orders/{order}/cancel', 'order', []],
        ['POST', 'api/v1/orders/{order}/parcels/{shipment}/cancel', 'shipment', []],
        ['GET', 'api/v1/orders/{order}/returnable', 'order', []],
        ['POST', 'api/v1/orders/{order}/returns', 'order', ['lines' => [1 => 1], 'reason' => 'changed_mind']],
        ['GET', 'api/v1/returns/{returnRequest}', 'returnRequest', []],
        ['GET', 'api/v1/gift-cards/{giftCard}', 'giftCard', []],
        ['POST', 'api/v1/returns/{returnRequest}/withdraw', 'returnRequest', []],
        ['POST', 'api/v1/returns/{returnRequest}/dispute', 'returnRequest', ['note' => 'x']],
        ['POST', 'api/v1/notifications/{notification}/read', 'notification', []],
        ['POST', 'api/v1/order-items/{orderItem}/review', 'orderItem', ['rating' => 5]],
        ['POST', 'api/v1/order-items/{orderItem}/seller-review', 'orderItem', ['rating' => 5]],
    ];

    public function test_customer_cannot_touch_another_customers_resources(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['customer'], 'sanctum');

        foreach (self::CASES as [$method, $template, $key, $payload]) {
            $model = $b[$key];
            $before = $model->fresh()->getAttributes();
            $uri = $this->resolveUri($template, $b);
            $case = "$method $uri";

            $response = $this->json($method, $uri, $payload);
            $this->assertDenied($response, $case);

            $after = $model->fresh();
            $this->assertNotNull($after, "$case: row was deleted");
            $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($after->getAttributes()), "$case: resource changed");
        }
    }

    public function test_positive_controls_owner_can_use_own_resources(): void
    {
        $a = $this->tenant('a');
        $this->actingAs($a['customer'], 'sanctum');

        $this->getJson('/api/v1/orders/'.$a['order']->getRouteKey())->assertOk();
        $this->getJson('/api/v1/orders/'.$a['order']->getRouteKey().'/items')->assertOk();
        $this->getJson('/api/v1/orders/'.$a['order']->getRouteKey().'/returnable')->assertOk();
        $this->getJson('/api/v1/returns/'.$a['returnRequest']->getRouteKey())->assertOk();
        $this->getJson('/api/v1/gift-cards/'.$a['giftCard']->getRouteKey())->assertOk();
        $this->getJson('/api/v1/returns')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/conversations/'.$a['conversation']->getRouteKey())->assertOk();
        $this->postJson('/api/v1/notifications/'.$a['notification']->getRouteKey().'/read')->assertSuccessful();
        $this->postJson('/api/v1/profile/addresses/'.$a['address']->getRouteKey().'/default')->assertSuccessful();
        $this->postJson('/api/v1/cards/'.$a['card']->getRouteKey().'/default')->assertSuccessful();
        $this->getJson('/api/v1/cards/status/'.$a['card']->reference)->assertOk()->assertJsonPath('status', 'active');
        $this->putJson('/api/v1/cart/'.$a['cartItem']->getRouteKey(), ['quantity' => 2])->assertSuccessful();
    }

    public function test_payment_start_rejects_another_customers_card_id(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $order = Order::create([
            'order_number' => 'TS-PENDING-A', 'user_id' => $a['customer']->id, 'status' => 'pending', 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'A', 'shipping_phone' => '0911111111', 'shipping_address' => 'Addr',
        ]);
        $this->actingAs($a['customer'], 'sanctum');
        $cardBefore = $b['card']->fresh()->getAttributes();
        $payments = Payment::count();

        $response = $this->postJson('/api/v1/payments/start', ['order_id' => $order->id, 'card_id' => $b['card']->id]);
        $this->assertContains($response->getStatusCode(), [403, 404, 422]);
        $this->assertSame($payments, Payment::count(), 'a payment was created with the B card');
        $this->assertSame(0, Payment::where('customer_card_id', $b['card']->id)->count());
        $this->assertEquals($this->stableAttributes($cardBefore), $this->stableAttributes($b['card']->fresh()->getAttributes()));

        // الضابط: بطاقة A نفسها تتجاوز فحص الملكية (ليست 403/404) وتُنشئ دفعة
        $response = $this->postJson('/api/v1/payments/start', ['order_id' => $order->id, 'card_id' => $a['card']->id]);
        $this->assertNotContains($response->getStatusCode(), [403, 404]);
        $this->assertSame(1, Payment::where('customer_card_id', $a['card']->id)->count(), 'own card was not used');
    }
}
