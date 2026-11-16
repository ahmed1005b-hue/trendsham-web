<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductOptionsEditorState;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ProductOptionsEditorStateTest extends TestCase
{
    public function test_old_input_wins_and_restores_switch_and_rows(): void
    {
        $product = $this->productWithVariants([['color' => '#1A1A1A', 'color_name' => 'أسود', 'size' => 'S', 'stock_quantity' => 9]]);
        $old = [
            'variant_options_enabled' => '1',
            'variant_color' => ['#D32F2F', '#D32F2F', ''],
            'variant_color_name' => ['أحمر', 'أحمر', ''],
            'variant_size' => ['M', 'L', ''],
            'variant_sku' => ['TEE-RM', '', ''],
            'variant_price_adjustment' => ['15.00', '0', '0'],
            'variant_stock_quantity' => ['2', '0', '0'],
            'variant_original_color' => ['احمر', 'احمر', ''],
        ];

        $state = ProductOptionsEditorState::make($product, $old);

        $this->assertTrue($state['enabled']);
        $this->assertCount(2, $state['rows']);
        $this->assertSame([2, 0], array_column($state['rows'], 'stock_quantity'));
        $this->assertSame(['M', 'L'], array_column($state['rows'], 'size'));
        $this->assertSame(['احمر', 'احمر'], array_column($state['rows'], 'original_color'));
        $this->assertSame(['TEE-RM', null], array_column($state['rows'], 'sku'));
        $this->assertSame(15, $state['rows'][0]['price_adjustment']);
    }

    public function test_old_input_with_the_switch_off_keeps_the_editor_off_even_when_the_product_has_variants(): void
    {
        $product = $this->productWithVariants([['color' => 'اسود', 'color_name' => 'اسود', 'size' => 'M', 'stock_quantity' => 5]]);

        $state = ProductOptionsEditorState::make($product, ['name' => 'p', 'variant_options_enabled' => '0']);

        $this->assertSame(['enabled' => false, 'rows' => []], $state);
    }

    public function test_product_variants_load_with_price_in_pounds_and_original_color(): void
    {
        $product = $this->productWithVariants([
            ['color' => 'اسود', 'color_name' => 'اسود', 'size' => 'M', 'sku' => null, 'price_adjustment' => 1500, 'stock_quantity' => 5],
            ['color' => null, 'color_name' => null, 'size' => 'XL', 'sku' => 'TEE-XL', 'price_adjustment' => 1250, 'stock_quantity' => 0],
        ]);

        $state = ProductOptionsEditorState::make($product, []);

        $this->assertTrue($state['enabled']);
        $this->assertSame([
            'color' => 'اسود', 'color_name' => 'اسود', 'size' => 'M', 'sku' => null,
            'price_adjustment' => 15, 'stock_quantity' => 5, 'original_color' => 'اسود',
        ], $state['rows'][0]);
        $this->assertSame(12.5, $state['rows'][1]['price_adjustment']);
        $this->assertNull($state['rows'][1]['original_color']);
    }

    public function test_new_product_starts_switched_off(): void
    {
        $this->assertSame(['enabled' => false, 'rows' => []], ProductOptionsEditorState::make(null, []));
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function productWithVariants(array $variants): Product
    {
        $product = new Product;
        $product->setRelation('variants', new Collection(array_map(
            fn (array $attributes) => new ProductVariant(['price_adjustment' => 0, 'sku' => null, ...$attributes]),
            $variants,
        )));

        return $product;
    }
}
