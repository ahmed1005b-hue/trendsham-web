<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * محرر الألوان والمقاسات في نموذج منتج البائع: إعداداته المطبوعة في الصفحة، وحفظ ما يرسله.
 */
class SellerProductOptionsFormTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'c', 'slug' => 'c']);
    }

    public function test_create_form_renders_editor_config_with_palette_and_text(): void
    {
        [$user] = $this->seller();

        $response = $this->actingAs($user)->get(route('seller.products.create'));

        $response->assertOk()
            ->assertSee('id="ts-options-config"', false)
            ->assertSee('ts-product-options.js', false)
            ->assertSee('#1A1A1A', false)
            ->assertDontSee('placeholder="مثال: أحمر"', false);
        $config = $this->editorConfig($response);
        $this->assertSame(['enabled' => false, 'rows' => []], $config['state']);
        $this->assertSame('لون آخر', $config['text']['otherColor']);
    }

    public function test_editor_config_shows_translated_labels_but_keeps_arabic_palette_names(): void
    {
        [$user] = $this->seller();

        $response = $this->actingAs($user)->withSession(['locale' => 'en'])->get(route('seller.products.create'));

        $config = $this->editorConfig($response);
        $this->assertSame(['name' => 'أسود', 'label' => 'Black', 'hex' => '#1A1A1A'], $config['colors'][0]);
        $this->assertSame(['key' => 'letters', 'label' => 'Letters (clothing)', 'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL']], $config['sizeSystems'][0]);
        $this->assertSame('Other color', $config['text']['otherColor']);
        $this->assertSame('Total: :stock items in :count options', $config['text']['totals']);
    }

    public function test_edit_form_loads_legacy_variants_switched_on_and_keeps_them_if_the_script_does_not_run(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor);
        ProductVariant::create([
            'product_id' => $product->id, 'color' => 'اسود', 'color_name' => 'اسود', 'size' => 'M',
            'price_adjustment' => 1500, 'stock_quantity' => 5, 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('seller.products.edit', $product));

        $response->assertOk();
        $this->assertSame(['enabled' => true, 'rows' => [[
            'color' => 'اسود', 'color_name' => 'اسود', 'size' => 'M', 'sku' => null,
            'price_adjustment' => 15, 'stock_quantity' => 5, 'original_color' => 'اسود',
        ]]], $this->editorConfig($response)['state']);
        $this->assertSame([
            'variant_options_enabled' => ['1'],
            'replace_variants' => ['1'],
            'variant_color[]' => ['اسود'],
            'variant_color_name[]' => ['اسود'],
            'variant_size[]' => ['M'],
            'variant_sku[]' => [''],
            'variant_price_adjustment[]' => ['15'],
            'variant_stock_quantity[]' => ['5'],
            'variant_original_color[]' => ['اسود'],
        ], $this->hiddenOptionInputs($response));
    }

    public function test_saving_a_color_size_matrix_creates_every_available_combination(): void
    {
        [$user] = $this->seller();

        $response = $this->actingAs($user)->post(route('seller.products.store'), $this->form([
            'sku' => 'TEE-1', 'stock_quantity' => 0,
            'variant_options_enabled' => '1', 'replace_variants' => '1',
            'variant_color' => ['#D32F2F', '#D32F2F', '#1A1A1A', '#1A1A1A'],
            'variant_color_name' => ['أحمر', 'أحمر', 'أسود', 'أسود'],
            'variant_size' => ['M', 'L', 'M', 'L'],
            'variant_sku' => ['', '', '', ''],
            'variant_price_adjustment' => ['0', '15', '0', '0'],
            'variant_stock_quantity' => ['1', '2', '3', '4'],
            'variant_original_color' => ['', '', '', ''],
        ]));

        $response->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));
        $product = Product::where('sku', 'TEE-1')->firstOrFail();
        $this->assertSame(4, $product->variants()->count());
        $this->assertSame(10, $product->stock_quantity);
        $this->assertSame(1500, $product->variants()->where('color', '#D32F2F')->where('size', 'L')->value('price_adjustment'));
    }

    public function test_validation_error_brings_the_seller_back_to_the_same_matrix_with_the_message(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor);
        $editUrl = route('seller.products.edit', $product);
        $this->actingAs($user)->from($editUrl)->put(route('seller.products.update', $product), $this->form([
            'sku' => $product->sku, 'stock_quantity' => 3, 'variant_options_enabled' => '1', 'replace_variants' => '1',
            'variant_color' => ['#1A1A1A'], 'variant_color_name' => ['أسود'], 'variant_size' => ['M'], 'variant_sku' => [''],
            'variant_price_adjustment' => ['-5'], 'variant_stock_quantity' => ['3'], 'variant_original_color' => ['اسود'],
        ]))->assertRedirect($editUrl)->assertSessionHasErrors('variant_price_adjustment.0');
        $message = session('errors')->first('variant_price_adjustment.0');

        $response = $this->get($editUrl);

        $response->assertOk()->assertSee($message);
        $this->assertSame(['enabled' => true, 'rows' => [[
            'color' => '#1A1A1A', 'color_name' => 'أسود', 'size' => 'M', 'sku' => null,
            'price_adjustment' => -5, 'stock_quantity' => 3, 'original_color' => 'اسود',
        ]]], $this->editorConfig($response)['state']);
        $this->assertSame(0, $product->variants()->count());
    }

    public function test_edit_page_offers_color_selects_for_saved_images(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor);
        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'p/1.jpg', 'color' => 'اسود', 'color_name' => 'اسود', 'is_primary' => true]);

        $response = $this->actingAs($user)->get(route('seller.products.edit', $product));

        $response->assertOk()
            ->assertSee('data-ts-color-select', false)
            ->assertSee('data-selected="اسود"', false)
            ->assertSee('name="existing_image_color['.$image->id.']"', false)
            ->assertSee('name="existing_image_color_name['.$image->id.']"', false)
            ->assertSee('<option value="">بدون لون</option>', false)
            ->assertSee('<option value="اسود" selected>اسود</option>', false)
            ->assertDontSee('placeholder="لون الصورة (اختياري)"', false);
    }

    public function test_image_colors_are_saved_from_the_selects(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor);
        $image = ProductImage::create(['product_id' => $product->id, 'path' => 'p/1.jpg', 'color' => 'اسود', 'color_name' => 'اسود', 'is_primary' => true]);

        $response = $this->actingAs($user)->put(route('seller.products.update', $product), $this->form([
            'sku' => $product->sku, 'stock_quantity' => 5,
            'existing_image_color' => [$image->id => '#1A1A1A'],
            'existing_image_color_name' => [$image->id => 'أسود'],
        ]));

        $response->assertSessionHasNoErrors();
        $image->refresh();
        $this->assertSame('#1A1A1A', $image->color);
        $this->assertSame('أسود', $image->color_name);
    }

    public function test_saving_with_options_switched_off_removes_the_variants_and_keeps_the_typed_stock(): void
    {
        [$user, $vendor] = $this->seller();
        $product = $this->product($vendor);
        ProductVariant::create(['product_id' => $product->id, 'color' => '#D32F2F', 'color_name' => 'أحمر', 'size' => 'M', 'stock_quantity' => 5, 'is_active' => true]);

        $response = $this->actingAs($user)->put(route('seller.products.update', $product), $this->form([
            'sku' => $product->sku, 'stock_quantity' => 7, 'variant_options_enabled' => '0', 'replace_variants' => '1',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(0, $product->variants()->count());
        $this->assertSame(7, $product->fresh()->stock_quantity);
    }

    /** @return array{0: User, 1: SellerProfile} */
    private function seller(): array
    {
        $user = User::factory()->create(['role' => 'seller']);
        $vendor = SellerProfile::create(['user_id' => $user->id, 'store_name' => 'S'.Str::random(4), 'status' => 'approved', 'commission_rate' => 10]);

        return [$user, $vendor];
    }

    private function product(SellerProfile $vendor): Product
    {
        return Product::create([
            'vendor_id' => $vendor->id, 'category_id' => $this->category->id, 'name' => 'p', 'slug' => 'p-'.Str::random(6),
            'sku' => 'SKU-'.Str::random(8), 'price' => 10000, 'stock_quantity' => 5, 'status' => 'draft',
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function form(array $fields): array
    {
        return [
            'category_id' => $this->category->id, 'name' => 'قميص', 'price' => 100, 'compare_at_price' => '',
            'status' => 'draft', 'low_stock_threshold' => 5,
        ] + $fields;
    }

    /** @return array<string, mixed> */
    private function editorConfig(TestResponse $response): array
    {
        $this->assertSame(1, preg_match('#<script type="application/json" id="ts-options-config">(.*?)</script>#s', $response->getContent(), $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, list<string>> */
    private function hiddenOptionInputs(TestResponse $response): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $inputs = [];
        /** @var \DOMElement $input */
        foreach ((new \DOMXPath($dom))->query('//*[@id="ts-options"]//input[@type="hidden"]') as $input) {
            $inputs[$input->getAttribute('name')][] = $input->getAttribute('value');
        }

        return $inputs;
    }
}
