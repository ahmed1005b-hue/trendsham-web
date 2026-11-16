<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionsStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_word_color_gets_a_palette_swatch_on_the_product_page(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر تجريبي',
            'status' => 'approved',
        ]);
        $category = Category::create(['name' => 'ملابس', 'slug' => 'clothes', 'is_active' => true]);
        $product = Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'قميص',
            'slug' => 'shirt',
            'sku' => 'SHIRT-01',
            'price' => 100000,
            'stock_quantity' => 5,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'اسود',
            'size' => 'M',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('background:#1A1A1A', false);
    }

    public function test_hostile_color_name_is_escaped_on_the_product_page(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر تجريبي',
            'status' => 'approved',
        ]);
        $category = Category::create(['name' => 'ملابس', 'slug' => 'clothes', 'is_active' => true]);
        $product = Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'قميص',
            'slug' => 'shirt',
            'sku' => 'SHIRT-01',
            'price' => 100000,
            'stock_quantity' => 5,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'color' => '#FF0000',
            'color_name' => 'x" onmouseover="alert(1)',
            'size' => 'M',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('x" onmouseover="alert(1)', false)
            ->assertSee('x&quot; onmouseover=&quot;alert(1)', false);
    }
}
