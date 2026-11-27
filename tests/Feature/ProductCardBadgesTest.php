<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** بطاقة المنتج: شارة الخصم أو «جديد» على الصورة، وزر «+» للسلة باسم واضح لقارئ الشاشة. */
class ProductCardBadgesTest extends TestCase
{
    use RefreshDatabase;

    private SellerProfile $store;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->create(['role' => 'seller']);
        $this->store = SellerProfile::create(['user_id' => $owner->id, 'store_name' => 'متجر البطاقات', 'status' => 'approved']);
        $this->category = Category::create(['name' => 'قسم', 'slug' => 'c-'.Str::random(5)]);
    }

    private function product(string $name, int $daysOld, ?int $compareAt = null): Product
    {
        $product = Product::create([
            'vendor_id' => $this->store->id, 'category_id' => $this->category->id,
            'name' => $name, 'slug' => 'p-'.Str::random(8), 'sku' => 'S-'.Str::random(8),
            'price' => 8000, 'compare_at_price' => $compareAt,
            'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
        Product::whereKey($product->id)->update(['created_at' => now()->subDays($daysOld)]);

        return $product->refresh();
    }

    public function test_a_product_added_this_week_is_new_and_an_older_one_is_not(): void
    {
        $this->assertTrue($this->product('جديد', 2)->isNew());
        $this->assertFalse($this->product('قديم', Product::NEW_BADGE_DAYS + 1)->isNew());
    }

    public function test_cards_show_the_new_badge_the_discount_and_a_named_cart_button(): void
    {
        $this->product('منتج وصل اليوم', 0);
        $this->product('منتج عليه خصم', 1, compareAt: 10000);
        $this->product('منتج قديم بلا شارة', 30);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'ts-market-badge--new'));
        $this->assertStringContainsString('-20%', $html);
        $this->assertStringContainsString('ts-market-card__buy', $html);
        $this->assertStringContainsString('أضف منتج وصل اليوم إلى السلة', $html);
        $this->assertStringContainsString('ts-market-card__brand', $html);
    }

    public function test_the_api_tells_the_app_which_products_are_new(): void
    {
        $fresh = $this->product('منتج جديد للتطبيق', 1);
        $old = $this->product('منتج قديم للتطبيق', 20);

        $items = collect($this->getJson('/api/v1/products?sort=newest')->assertOk()->json('data'))->keyBy('id');

        $this->assertTrue($items[$fresh->id]['is_new']);
        $this->assertFalse($items[$old->id]['is_new']);
    }
}
