<?php

namespace Tests\Feature\Push;

use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerNewOrderTest extends TestCase
{
    use PushTestHelpers, RefreshDatabase;

    private function product(SellerProfile $seller, int $price): Product
    {
        $category = Category::firstOrCreate(['slug' => 'c'], ['name' => 'c']);

        return Product::create([
            'vendor_id' => $seller->id, 'category_id' => $category->id, 'name' => 'منتج '.Str::random(3), 'slug' => 'p-'.Str::random(6),
            'sku' => 'S-'.Str::random(8), 'price' => $price, 'stock_quantity' => 50, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    private function cart(User $u, Product $p, int $qty = 1): void
    {
        CartItem::create(['user_id' => $u->id, 'product_id' => $p->id, 'quantity' => $qty, 'unit_price' => $p->price]);
    }

    private function sellerNotifications(SellerProfile $s)
    {
        return AppNotification::where('user_id', $s->user_id)->where('type', 'seller_new_order')->get();
    }

    private const SHIPPING = ['shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b', 'payment_method' => 'cash_on_delivery'];

    public function test_web_checkout_from_two_stores_notifies_each_seller_with_their_own_total(): void
    {
        $this->noFcm();
        $a = $this->seller();
        $b = $this->seller();
        $u = $this->customer();
        $this->cart($u, $this->product($a, 1_000_000), 2);   // 20,000 ل.س
        $this->cart($u, $this->product($b, 350_000));        // 3,500 ل.س

        $this->actingAs($u)->post(route('customer.checkout.store'), self::SHIPPING);
        $order = $u->orders()->latest('id')->firstOrFail();

        $na = $this->sellerNotifications($a);
        $nb = $this->sellerNotifications($b);
        $this->assertCount(1, $na);
        $this->assertCount(1, $nb);
        $this->assertStringContainsString($order->order_number, $na[0]->title);
        $this->assertStringContainsString('20,000', $na[0]->body);
        $this->assertStringContainsString('3,500', $nb[0]->body);
        $this->assertSame(route('seller.orders.show', $order), $na[0]->link);
        $this->assertSame(['screen' => 'seller_order', 'id' => $order->id], $na[0]->target);
        $this->assertSame('seller_orders', $na[0]->group);
    }

    public function test_api_checkout_from_one_store_notifies_once(): void
    {
        $this->noFcm();
        $a = $this->seller();
        $u = $this->customer();
        $this->cart($u, $this->product($a, 1_000_000));
        $this->cart($u, $this->product($a, 500_000));

        $this->actingAs($u, 'sanctum')->postJson('/api/v1/checkout', self::SHIPPING)->assertSuccessful();

        $n = $this->sellerNotifications($a);
        $this->assertCount(1, $n);
        $this->assertStringContainsString('15,000', $n[0]->body);
    }

    public function test_seller_push_goes_to_the_seller_phone(): void
    {
        $this->fakeFcm();
        $a = $this->seller();
        $this->device($a->user, 'seller-phone');
        $u = $this->customer();
        $this->cart($u, $this->product($a, 1_000_000));

        $this->actingAs($u)->post(route('customer.checkout.store'), self::SHIPPING);

        $push = $this->sentPushes()->firstWhere('token', 'seller-phone');
        $this->assertNotNull($push);
        $this->assertSame('seller_order', $push['data']['screen']);
        $this->assertSame('seller_new_order', $push['data']['type']);
    }

    public function test_customer_messages_and_questions_reach_seller_in_seller_messages_group(): void
    {
        $this->noFcm();
        $seller = $this->seller();

        $msg = AppNotification::send($seller->user_id, 'new_message', 'رسالة جديدة', null, '/seller/conversations/3');
        $q = AppNotification::send($seller->user_id, 'product_question', 'سؤال جديد', null, null);
        $customerMsg = AppNotification::send($this->customer()->id, 'new_message', 'رسالة', null, '/customer/conversations/3');

        $this->assertSame('seller_messages', $msg->group);
        $this->assertSame('seller_messages', $q->group);
        $this->assertSame('messages', $customerMsg->group);
    }
}
