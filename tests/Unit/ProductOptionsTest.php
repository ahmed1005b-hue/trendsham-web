<?php

namespace Tests\Unit;

use App\Support\ProductOptions;
use Tests\TestCase;

class ProductOptionsTest extends TestCase
{
    public function test_palette_has_22_unique_names_and_hexes(): void
    {
        $colors = ProductOptions::colors();

        $this->assertCount(22, $colors);
        $this->assertCount(22, array_unique(array_column($colors, 'name')));
        $this->assertCount(22, array_unique(array_column($colors, 'hex')));

        foreach ($colors as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $color['hex']);
        }
    }

    public function test_size_systems_are_in_spec_order(): void
    {
        $systems = ProductOptions::sizeSystems();

        $this->assertSame(['letters', 'clothing', 'shoes', 'jeans', 'kids'], array_column($systems, 'key'));
        $this->assertSame(['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL'], $systems[0]['sizes']);
        $this->assertSame('0-3M', $systems[4]['sizes'][0]);
        $this->assertSame('13-14Y', $systems[4]['sizes'][11]);
    }

    public function test_normalize_name_unifies_arabic_spellings(): void
    {
        $this->assertSame(ProductOptions::normalizeName('أسود'), ProductOptions::normalizeName('اسود'));
        $this->assertSame(ProductOptions::normalizeName('احمر'), ProductOptions::normalizeName(' أحمـــر '));
        $this->assertSame(ProductOptions::normalizeName('وردي'), ProductOptions::normalizeName('وردىّ'));
        $this->assertSame('navy blue', ProductOptions::normalizeName('Navy  Blue'));
    }

    public function test_hex_for(): void
    {
        $this->assertSame('#D32F2F', ProductOptions::hexFor('d32f2f'));
        $this->assertSame('#1A1A1A', ProductOptions::hexFor('#1a1a1a'));
        $this->assertSame('#1A1A1A', ProductOptions::hexFor('اسود'));
        $this->assertSame('#D32F2F', ProductOptions::hexFor('x', 'احمر'));
        $this->assertNull(ProductOptions::hexFor('لون غريب'));
        $this->assertNull(ProductOptions::hexFor(null, null));
    }
}
