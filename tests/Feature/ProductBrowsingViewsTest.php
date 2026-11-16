<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBrowsingViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_search_and_category_pages_share_the_product_presentation(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $store = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر تجريبي',
            'status' => 'approved',
        ]);
        $category = Category::create([
            'name' => 'قهوة',
            'slug' => 'coffee',
            'is_active' => true,
        ]);
        $product = Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'قهوة من حمص',
            'slug' => 'homs-coffee',
            'sku' => 'COFFEE-01',
            'price' => 250000,
            'compare_at_price' => 300000,
            'stock_quantity' => 8,
            'status' => 'active',
            'admin_approved_at' => now(),
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('اختيارات من متاجر الشام')
            ->assertSee($product->name)
            ->assertSee($product->formatted_price)
            ->assertSee($product->formatted_compare_at_price);

        $this->get(route('search', ['q' => 'قهوة']))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->formatted_compare_at_price)
            ->assertSee('تصفية المنتجات');

        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee($product->formatted_price)
            ->assertSee('قهوة');
    }

    public function test_search_and_category_pages_keep_their_empty_states(): void
    {
        $category = Category::create([
            'name' => 'أدوات منزلية',
            'slug' => 'home-tools',
            'is_active' => true,
        ]);

        $this->get(route('search', ['q' => 'منتج غير موجود']))
            ->assertOk()
            ->assertSee('ما لقينا منتجات بهالمواصفات');

        $this->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee('القسم هاد فاضي حالياً');
    }

    public function test_homepage_shows_active_categories_after_the_first_eight(): void
    {
        foreach (range(1, 9) as $index) {
            Category::create([
                'name' => "قسم {$index}",
                'slug' => "category-{$index}",
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $category = Category::create([
            'name' => 'الحرف اليدوية والمنتجات المحلية',
            'slug' => 'local-handicrafts',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($category->name);
    }
}
