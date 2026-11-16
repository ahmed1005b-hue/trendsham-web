<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * مزامنة ألوان ومقاسات المنتج: ترقية الألوان القديمة (كلمات) إلى الدرجات الموحدة بدون كسر السلال.
 */
class ProductVariantOptionsSyncTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'c', 'slug' => 'c']);
    }

    private function seller(): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'S'.Str::random(4), 'status' => 'approved', 'commission_rate' => 10]);

        return [$user, $vendor];
    }

    private function product(SellerProfile $vendor, int $stock = 10): Product
    {
        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'p', 'slug' => 'p-'.Str::random(6),
            'sku' => 'SKU-'.Str::random(8), 'price' => 10000, 'stock_quantity' => $stock, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    private function payload(Product $product, array $variant): array
    {
        $fields = [];
        foreach ($variant as $key => $value) {
            $fields['variant_'.$key] = [$value];
        }

        return [
            'category_id' => $this->category->id, 'name' => 'p', 'sku' => $product->sku, 'price' => 100, 'compare_at_price' => '',
            'stock_quantity' => $product->stock_quantity, 'status' => 'active', 'replace_variants' => '1',
        ] + $fields;
    }

    private function legacyRename(): array
    {
        return [
            'color' => '#1a1a1a', 'color_name' => 'أسود', 'size' => 'M',
            'stock_quantity' => 3, 'original_color' => 'اسود',
        ];
    }

    private function legacyVariant(Product $product): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $product->id, 'color' => 'اسود', 'color_name' => 'اسود', 'size' => 'M',
            'stock_quantity' => 5, 'is_active' => true,
        ]);
    }

    public function test_palette_color_replaces_legacy_word_in_place_keeping_cart_item(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $variant = $this->legacyVariant($product);
        $cart = CartItem::create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 10000]);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->payload($product, $this->legacyRename()))
            ->assertSessionHasNoErrors();

        $fresh = $variant->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('#1A1A1A', $fresh->color);
        $this->assertSame('أسود', $fresh->color_name);
        $this->assertSame(3, $fresh->stock_quantity);
        $this->assertNotNull($cart->fresh());
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_images_follow_the_renamed_color(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $this->legacyVariant($product);
        $image = $product->images()->create(['path' => 'products/x.jpg', 'color' => 'اسود', 'color_name' => 'اسود', 'is_primary' => true, 'sort_order' => 0]);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->payload($product, $this->legacyRename()));

        $this->assertSame('#1A1A1A', $image->fresh()->color);
        $this->assertSame('أسود', $image->fresh()->color_name);
    }

    public function test_hex_colors_are_stored_uppercase_with_hash(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->payload($product, [
            'color' => 'd32f2f', 'color_name' => 'أحمر', 'size' => 'M', 'stock_quantity' => 2,
        ]));

        $this->assertSame('#D32F2F', $product->variants()->first()->color);
    }

    public function test_original_color_without_a_matching_variant_creates_a_new_one(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->payload($product, [
            'color' => '#1a1a1a', 'color_name' => 'أسود', 'size' => 'M', 'stock_quantity' => 2, 'original_color' => 'ليس موجوداً',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, $product->variants()->count());
        $this->assertSame('#1A1A1A', $product->variants()->first()->color);
    }

    public function test_negative_price_increase_is_rejected_on_web(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $variant = $this->legacyVariant($product);

        $this->actingAs($user)->put(route('seller.products.update', $product), $this->payload($product, $this->legacyRename() + ['price_adjustment' => -5]))
            ->assertSessionHasErrors('variant_price_adjustment.0');

        $this->assertSame('اسود', $variant->fresh()->color);
    }

    public function test_api_update_upgrades_legacy_color_and_rejects_negative_price(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $variant = $this->legacyVariant($product);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/seller/products/{$product->id}", $this->payload($product, $this->legacyRename()))->assertOk();
        $this->assertSame('#1A1A1A', $variant->fresh()->color);

        $this->postJson("/api/v1/seller/products/{$product->id}", $this->payload($product, $this->legacyRename() + ['price_adjustment' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('variant_price_adjustment.0');
    }

    public function test_api_update_canonicalizes_existing_image_hex_colors(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $image = $product->images()->create(['path' => 'products/x.jpg', 'color' => null, 'color_name' => null, 'is_primary' => true, 'sort_order' => 0]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/seller/products/{$product->id}", [
            'category_id' => $this->category->id, 'name' => 'p', 'sku' => $product->sku, 'price' => 100,
            'stock_quantity' => 5, 'status' => 'active', 'compare_at_price' => '',
            'existing_image_color' => [$image->id => '#1a1a1a'],
        ])->assertOk();

        $this->assertSame('#1A1A1A', $image->fresh()->color);
        $this->assertSame('#1A1A1A', $image->fresh()->color_name);
    }

    public function test_web_update_canonicalizes_existing_image_hex_colors(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor, 5);
        $image = $product->images()->create(['path' => 'products/x.jpg', 'color' => null, 'color_name' => null, 'is_primary' => true, 'sort_order' => 0]);

        $this->actingAs($user)->put(route('seller.products.update', $product), [
            'category_id' => $this->category->id, 'name' => 'p', 'sku' => $product->sku, 'price' => 100,
            'stock_quantity' => 5, 'status' => 'active', 'compare_at_price' => '',
            'existing_image_color' => [$image->id => '#d32f2f'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('#D32F2F', $image->fresh()->color);
    }
}
