<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductReviewSubmissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_review_product_from_a_delivered_purchase(): void
    {
        [$customer, $product, $orderItem] = $this->createPurchase('delivered');
        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/products/{$product->slug}/reviews", [
            'rating' => 5,
            'comment' => 'منتج ممتاز',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'منتج ممتاز');

        $this->assertDatabaseHas('reviews', [
            'order_item_id' => $orderItem->id,
            'user_id' => $customer->id,
        ]);
    }

    public function test_customer_cannot_review_without_a_delivered_purchase(): void
    {
        [$customer, $product] = $this->createPurchase('shipped');
        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 4])
            ->assertUnprocessable();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_guest_cannot_submit_product_review(): void
    {
        [, $product] = $this->createPurchase('delivered');

        $this->postJson("/api/v1/products/{$product->slug}/reviews", ['rating' => 4])
            ->assertUnauthorized();

        $this->assertDatabaseCount('reviews', 0);
    }

    private function createPurchase(string $status): array
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر التقييم',
            'status' => 'approved',
        ]);
        $category = Category::create(['name' => 'إلكترونيات', 'slug' => 'electronics']);
        $product = Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'هاتف تجريبي',
            'slug' => 'test-phone',
            'sku' => 'TEST-PHONE',
            'price' => 10000,
            'stock_quantity' => 5,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => 'TEST-'.uniqid(),
            'user_id' => $customer->id,
            'status' => $status,
            'subtotal' => $product->price,
            'shipping_fee' => 0,
            'total' => $product->price,
            'shipping_name' => 'عميل',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'عنوان تجريبي',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'vendor_id' => $store->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);

        return [$customer, $product, $orderItem];
    }
}
