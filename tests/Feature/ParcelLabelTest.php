<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Shipping\ParcelLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ملصق الشحنة: البائع يطبع ورقة لطرده فيها المستلم والمبلغ المطلوب عند التسليم،
 * من الموقع ومن التطبيق، ولطرده هو فقط.
 */
class ParcelLabelTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'c', 'slug' => 'c']);
    }

    /** @return array{0: User, 1: SellerProfile} */
    private function seller(string $store = 'متجر سام'): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create([
            'user_id' => $user->id, 'store_name' => $store, 'store_contact_phone' => '0933111222',
            'status' => 'approved', 'commission_rate' => 10,
        ]);

        return [$user, $vendor];
    }

    /** @param  list<SellerProfile>  $vendors */
    private function order(array $vendors, string $paymentStatus = 'unpaid'): Order
    {
        $order = Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(6)), 'user_id' => User::factory()->create()->id,
            'status' => 'pending', 'payment_status' => $paymentStatus,
            'subtotal' => 10000 * count($vendors), 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000 * count($vendors),
            'shipping_name' => 'سارة أحمد', 'shipping_phone' => '0999888777',
            'shipping_address' => 'شارع بغداد، بناء 12', 'shipping_governorate' => 'دمشق',
        ]);
        foreach ($vendors as $i => $vendor) {
            $product = Product::create([
                'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'p', 'slug' => 'p-'.Str::random(6),
                'sku' => 'SKU-'.Str::random(8), 'price' => 10000, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
            ]);
            OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $vendor->id,
                'product_name' => "قميص {$i}", 'unit_price' => 10000, 'quantity' => 1, 'subtotal' => 10000,
            ]);
        }

        return $order;
    }

    private function labelHtml(SellerProfile $vendor, Order $order): string
    {
        $data = app(ParcelLabel::class)->data($vendor, $order);

        return view('shipping.label', $data + ['locale' => 'ar', 'direction' => 'rtl'])->render();
    }

    public function test_seller_prints_a_label_from_the_website(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order([$vendor]);

        $this->actingAs($user)->get(route('seller.orders.show', $order))
            ->assertOk()->assertSee(route('seller.orders.label', $order));

        $response = $this->actingAs($user)->get(route('seller.orders.label', $order))->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_seller_prints_a_label_from_the_app(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order([$vendor]);

        $response = $this->actingAs($user, 'sanctum')->get("/api/v1/seller/orders/{$order->id}/label")->assertOk();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_label_shows_recipient_store_and_cash_to_collect(): void
    {
        [, $vendor] = $this->seller();
        $order = $this->order([$vendor]);
        Shipment::create(['order_id' => $order->id, 'seller_profile_id' => $vendor->id, 'status' => 'pending',
            'delivery_mode' => 'self', 'cod_amount' => 1250000]);

        $html = $this->labelHtml($vendor, $order);

        foreach (['سارة أحمد', '0999888777', 'شارع بغداد، بناء 12', 'دمشق', 'متجر سام', '0933111222', 'قميص 0', $order->order_number, '12,500'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertStringContainsString('المبلغ المطلوب من الزبون عند التسليم', $html);
    }

    public function test_paid_order_label_says_collect_nothing(): void
    {
        [, $vendor] = $this->seller();
        $order = $this->order([$vendor], 'paid');
        Shipment::create(['order_id' => $order->id, 'seller_profile_id' => $vendor->id, 'status' => 'pending', 'cod_amount' => 0]);

        $html = $this->labelHtml($vendor, $order);

        $this->assertStringContainsString('مدفوع مسبقاً', $html);
        $this->assertStringNotContainsString('المبلغ المطلوب من الزبون', $html);
    }

    public function test_shared_order_label_lists_only_this_stores_items(): void
    {
        [, $vendorA] = $this->seller('متجر أ');
        [, $vendorB] = $this->seller('متجر ب');
        $order = $this->order([$vendorA, $vendorB]);

        $html = $this->labelHtml($vendorA, $order);

        $this->assertStringContainsString('قميص 0', $html);
        $this->assertStringNotContainsString('قميص 1', $html);
        $this->assertStringNotContainsString('متجر ب', $html);
    }

    public function test_app_order_details_show_the_sellers_parcel(): void
    {
        [$user, $vendorA] = $this->seller('متجر أ');
        [, $vendorB] = $this->seller('متجر ب');
        $order = $this->order([$vendorA, $vendorB]);
        Shipment::create(['order_id' => $order->id, 'seller_profile_id' => $vendorA->id, 'status' => 'pending',
            'delivery_mode' => 'self', 'cod_amount' => 1250000, 'shipping_fee' => 500000]);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/seller/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.shipping_name', 'سارة أحمد')
            ->assertJsonPath('data.shipping_address', 'شارع بغداد، بناء 12')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_name', 'قميص 0')
            ->assertJsonPath('parcel.delivery_mode', 'self')
            ->assertJsonPath('parcel.seller_delivers', true)
            ->assertJsonPath('parcel.cod_amount', 12500)
            ->assertJsonPath('parcel.shipping_governorate', 'دمشق')
            ->assertJsonPath('parcel.is_shared', true)
            ->assertJsonPath('parcel.can_print_label', true)
            ->assertJsonPath('parcel.next_statuses.0', 'processing');
    }

    public function test_another_store_cannot_print_the_label(): void
    {
        [, $vendorA] = $this->seller('متجر أ');
        [$userB] = $this->seller('متجر ب');
        $order = $this->order([$vendorA]);

        $this->actingAs($userB)->get(route('seller.orders.label', $order))->assertForbidden();
        $this->actingAs($userB, 'sanctum')->getJson("/api/v1/seller/orders/{$order->id}/label")->assertForbidden();
    }

    public function test_cancelled_parcel_has_no_label(): void
    {
        [$user, $vendor] = $this->seller();
        $order = $this->order([$vendor]);
        Shipment::create(['order_id' => $order->id, 'seller_profile_id' => $vendor->id, 'status' => 'cancelled']);

        $this->actingAs($user)->from(route('seller.orders.show', $order))
            ->get(route('seller.orders.label', $order))
            ->assertRedirect(route('seller.orders.show', $order))->assertSessionHas('error');
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/seller/orders/{$order->id}/label")->assertStatus(422);
        $this->actingAs($user)->get(route('seller.orders.show', $order))
            ->assertDontSee(route('seller.orders.label', $order));
    }
}
