<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** متجر أضاف منتجات كثيرة دفعة واحدة لا يحتل «وصل حديثاً» و«العروض» في الموقع والتطبيق. */
class HomeProductsSpreadAcrossStoresTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'قسم', 'slug' => 'c-'.Str::random(5)]);
    }

    private function store(string $name): SellerProfile
    {
        $owner = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create(['user_id' => $owner->id, 'store_name' => $name, 'status' => 'approved']);
    }

    private function product(SellerProfile $store, string $name, int $minutesAgo, bool $onSale = false): Product
    {
        $product = Product::create([
            'vendor_id' => $store->id, 'category_id' => $this->category->id,
            'name' => $name, 'slug' => 'p-'.Str::random(8), 'sku' => 'S-'.Str::random(8),
            'price' => 10000, 'compare_at_price' => $onSale ? 15000 : null,
            'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
        Product::whereKey($product->id)->update(['created_at' => now()->subMinutes($minutesAgo)]);

        return $product;
    }

    public function test_a_store_that_adds_many_products_at_once_gets_two_places_in_new_arrivals(): void
    {
        $others = collect(['متجر أ', 'متجر ب', 'متجر ج'])->map(fn ($name) => $this->store($name));
        foreach ($others as $i => $store) {
            $this->product($store, "منتج قديم {$i}", 100 + $i);
        }
        $flooding = $this->store('متجر الدفعة');
        foreach (range(1, 15) as $i) {
            $this->product($flooding, "منتج دفعة {$i}", $i);
        }

        $arrivals = collect($this->getJson('/api/v1/home/products')->assertOk()->json('new_arrivals'));

        $this->assertCount(12, $arrivals);
        $byStore = $arrivals->countBy('vendor.id');
        $this->assertCount(4, $byStore);   // المتاجر الأربعة كلها ظاهرة
        // منتجان من المتجر في مكانهما، والأماكن التي لم تملأها المتاجر الأخرى تُكمَّل بمنتجاته الأحدث
        $this->assertSame(['منتج دفعة 1', 'منتج دفعة 2'], $arrivals->take(2)->pluck('name')->all());
        foreach ($others as $store) {
            $this->assertSame(1, $byStore[$store->id]);
        }

        $this->get(route('home'))->assertOk()
            ->assertSee('منتج قديم 0')->assertSee('منتج قديم 1')->assertSee('منتج قديم 2');
    }

    public function test_with_enough_stores_each_store_gets_at_most_two_new_arrivals(): void
    {
        foreach (range(1, 8) as $s) {
            $store = $this->store("متجر {$s}");
            foreach (range(1, 4) as $i) {
                $this->product($store, "منتج {$s}-{$i}", $s * 10 + $i);
            }
        }

        $arrivals = collect($this->getJson('/api/v1/home/products')->assertOk()->json('new_arrivals'));

        $this->assertCount(12, $arrivals);
        $this->assertSame(2, $arrivals->countBy('vendor.id')->max());
        $this->assertCount(6, $arrivals->countBy('vendor.id'));
    }

    public function test_deals_show_one_product_per_store(): void
    {
        $big = $this->store('متجر العروض الكثيرة');
        foreach (range(1, 6) as $i) {
            $this->product($big, "عرض كبير {$i}", $i, onSale: true);
        }
        foreach (range(1, 3) as $s) {
            $this->product($this->store("متجر {$s}"), "عرض متجر {$s}", 50 + $s, onSale: true);
        }
        $this->product($big, 'منتج بلا خصم', 0);

        $deals = collect($this->getJson('/api/v1/home/products')->assertOk()->json('deals'));

        $this->assertCount(4, $deals);
        $this->assertCount(4, $deals->countBy('vendor.id'));
        $this->assertNotContains('منتج بلا خصم', $deals->pluck('name'));
    }

    public function test_a_single_store_still_fills_the_home_sections(): void
    {
        $only = $this->store('المتجر الوحيد');
        foreach (range(1, 14) as $i) {
            $this->product($only, "منتج {$i}", $i, onSale: $i <= 5);
        }

        $response = $this->getJson('/api/v1/home/products')->assertOk();

        $this->assertCount(12, $response->json('new_arrivals'));
        $this->assertSame('منتج 1', $response->json('new_arrivals.0.name'));
        $this->assertCount(4, $response->json('deals'));
    }

    public function test_hidden_products_never_reach_the_home_sections(): void
    {
        $store = $this->store('متجر');
        $this->product($store, 'منتج ظاهر', 5);
        $this->product($store, 'منتج بانتظار الموافقة', 1)->update(['admin_approved_at' => null]);
        $suspended = $this->store('متجر موقوف');
        $this->product($suspended, 'منتج متجر موقوف', 2);
        $suspended->update(['status' => 'suspended']);

        $names = collect($this->getJson('/api/v1/home/products')->assertOk()->json('new_arrivals'))->pluck('name');

        $this->assertSame(['منتج ظاهر'], $names->all());
    }
}
