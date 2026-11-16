<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryBrowsingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_list_includes_active_children_but_hides_inactive_children(): void
    {
        $parent = Category::create([
            'name' => 'المنزل والحديقة',
            'slug' => 'home-garden',
            'is_active' => true,
        ]);
        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'النباتات',
            'slug' => 'plants',
            'is_active' => true,
        ]);
        Category::create([
            'parent_id' => $parent->id,
            'name' => 'قسم مخفي',
            'slug' => 'hidden-category',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonMissing(['name' => 'قسم مخفي']);
    }

    public function test_root_category_products_include_visible_products_from_active_children(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر النباتات',
            'status' => 'approved',
        ]);
        $parent = Category::create([
            'name' => 'الحدائق والنباتات',
            'slug' => 'gardens',
            'is_active' => true,
        ]);
        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'نباتات منزلية',
            'slug' => 'indoor-plants',
            'is_active' => true,
        ]);

        $parentProduct = $this->createVisibleProduct($store, $parent, 'garden-tools');
        $childProduct = $this->createVisibleProduct($store, $child, 'house-plant');

        $this->getJson("/api/v1/categories/{$parent->slug}")
            ->assertOk()
            ->assertJsonFragment(['slug' => $parentProduct->slug])
            ->assertJsonFragment(['slug' => $childProduct->slug]);

        $this->getJson("/api/v1/categories/{$child->slug}")
            ->assertOk()
            ->assertJsonFragment(['slug' => $childProduct->slug])
            ->assertJsonMissing(['slug' => $parentProduct->slug]);
    }

    private function createVisibleProduct(SellerProfile $store, Category $category, string $slug): Product
    {
        return Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => $slug,
            'slug' => $slug,
            'sku' => strtoupper($slug),
            'price' => 10000,
            'stock_quantity' => 5,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);
    }
}
