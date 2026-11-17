<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** صفحة المنتج تسمح باختيار أكثر من قطعة قبل الإضافة للسلة. */
class ProductPageQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 5): Product
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'متجر', 'status' => 'approved']);
        $category = Category::create(['name' => 'ملابس', 'slug' => 'clothes', 'is_active' => true]);

        return Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id, 'name' => 'قميص', 'slug' => 'shirt',
            'sku' => 'SHIRT-01', 'price' => 100000, 'stock_quantity' => $stock, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    public function test_product_page_has_a_quantity_field_limited_by_stock(): void
    {
        $product = $this->product(5);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('name="quantity"', false)
            ->assertSee('max="5"', false);
    }

    public function test_chosen_quantity_is_added_and_capped_at_stock(): void
    {
        $product = $this->product(5);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->post(route('customer.cart.store'), ['product_id' => $product->id, 'quantity' => 3])->assertRedirect();
        $this->assertSame(3, (int) CartItem::where('user_id', $customer->id)->value('quantity'));

        $this->actingAs($customer)->post(route('customer.cart.store'), ['product_id' => $product->id, 'quantity' => 4])->assertRedirect();
        $this->assertSame(5, (int) CartItem::where('user_id', $customer->id)->value('quantity'));
    }
}
