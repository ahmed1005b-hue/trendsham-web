<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_gets_catalog_with_translated_labels(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        SellerProfile::create(['user_id' => $user->id, 'store_name' => 'Store', 'status' => 'approved', 'commission_rate' => 10]);
        Sanctum::actingAs($user);

        $response = $this->withHeaders(['X-App-Locale' => 'en'])->getJson('/api/v1/seller/product-options');

        $response->assertOk()
            ->assertJsonPath('colors.0', ['name' => 'أسود', 'label' => 'Black', 'hex' => '#1A1A1A'])
            ->assertJsonPath('size_systems.0.key', 'letters')
            ->assertJsonPath('size_systems.0.label', 'Letters (clothing)');
        $this->assertCount(22, $response->json('colors'));
        $this->assertCount(5, $response->json('size_systems'));
    }

    public function test_customer_cannot_read_seller_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->getJson('/api/v1/seller/product-options')->assertForbidden();
    }

    public function test_variant_and_image_resources_expose_color_hex(): void
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'Store', 'status' => 'approved', 'commission_rate' => 10]);
        $category = Category::create(['name' => 'c', 'slug' => 'c']);
        $product = Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'p', 'slug' => 'p-hex',
            'sku' => 'SKU-HEX1', 'price' => 10000, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
        ProductVariant::create(['product_id' => $product->id, 'color' => 'اسود', 'size' => 'M', 'stock_quantity' => 3, 'price_adjustment' => 0, 'is_active' => true]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'x.jpg', 'color' => '#c0c0c0', 'is_primary' => true, 'sort_order' => 0]);

        $response = $this->getJson('/api/v1/products/p-hex')->assertOk();

        $response->assertJsonPath('data.variants.0.color_hex', '#1A1A1A');
        $this->assertContains('#C0C0C0', array_column($response->json('data.images'), 'color_hex'));
    }
}
