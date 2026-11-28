<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * الضغط على «تأكيد الطلب» مرتين لا يُنشئ طلبين.
 *
 * التزامن الحقيقي لا يُعاد إنتاجه في اختبار متسلسل، فنحاكيه: بعد أن يقرأ الطلب الثاني السلة
 * (قبل المعاملة) ننهي «الطلب الأول» بحذف بنود السلة — كما يحدث حين تسبقه ضغطة متزامنة.
 */
class CheckoutDoubleSubmitTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function payload(): array
    {
        return ['shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery', 'cod_confirmed' => true];
    }

    /** يحذف بنود السلة مباشرة بعد أول قراءة لها خارج المعاملة (= ضغطة سابقة أنهت الطلب). */
    private function finishOtherSubmitAfterCartRead(User $customer): void
    {
        $fired = false;
        $baseLevel = DB::transactionLevel();
        DB::listen(function (QueryExecuted $query) use (&$fired, $customer, $baseLevel) {
            if ($fired || DB::transactionLevel() > $baseLevel || ! str_contains($query->sql, 'from `cart_items`')) {
                return;
            }
            $fired = true;
            DB::table('cart_items')->where('user_id', $customer->id)->delete();
        });
    }

    public function test_api_second_submit_while_the_first_finishes_is_refused_without_a_new_order(): void
    {
        $a = $this->tenant('a');
        $ordersBefore = Order::count();
        $this->finishOtherSubmitAfterCartRead($a['customer']);

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_ordered');

        $this->assertSame($ordersBefore, Order::count());
        $this->assertSame(10, $a['product']->fresh()->stock_quantity);
    }

    public function test_api_sequential_double_submit_creates_one_order(): void
    {
        $a = $this->tenant('a');
        $ordersBefore = Order::count();

        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertCreated();
        $this->actingAs($a['customer'], 'sanctum')->postJson('/api/v1/checkout', $this->payload())->assertStatus(400);

        $this->assertSame($ordersBefore + 1, Order::count());
        $this->assertSame(9, $a['product']->fresh()->stock_quantity);
    }

    public function test_web_second_submit_while_the_first_finishes_redirects_to_the_existing_order(): void
    {
        $a = $this->tenant('a');
        $ordersBefore = Order::count();
        $this->finishOtherSubmitAfterCartRead($a['customer']);

        $this->actingAs($a['customer'])->post(route('customer.checkout.store'), $this->payload())
            ->assertRedirect(route('customer.orders.show', $a['order']))
            ->assertSessionHas('success');

        $this->assertSame($ordersBefore, Order::count());
    }
}
