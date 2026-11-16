<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\Story;
use App\Models\User;
use App\Services\ProductThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * نسخ WebP مصغّرة لصور المنتجات تُعرض في البطاقات والقوائم بدل الأصل الثقيل.
 */
class ProductThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function putJpeg(string $path, int $w = 1600, int $h = 1200): void
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 11, 95, 74));
        ob_start();
        imagejpeg($img, null, 90);
        Storage::disk('public')->put($path, ob_get_clean());
    }

    private function product(): Product
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'متجر الاختبار',
            'status' => 'approved',
        ]);

        return Product::create([
            'vendor_id' => $profile->id,
            'category_id' => Category::create(['name' => 'قسم', 'slug' => 'cat-'.uniqid()])->id,
            'name' => 'منتج اختبار',
            'slug' => 'test-product-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'price' => 10000,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);
    }

    public function test_creating_an_image_generates_a_small_webp_thumbnail(): void
    {
        Storage::fake('public');
        $this->putJpeg('products/abc.jpg');

        $image = $this->product()->images()->create(['path' => 'products/abc.jpg', 'is_primary' => true]);

        Storage::disk('public')->assertExists('thumbs/products/abc.webp');
        $info = getimagesizefromstring(Storage::disk('public')->get('thumbs/products/abc.webp'));
        $this->assertSame('image/webp', $info['mime']);
        $this->assertSame(ProductThumbnailService::MAX_WIDTH, $info[0]);
        $this->assertSame(450, $info[1]); // يحافظ على النسبة 4:3
        $this->assertStringEndsWith('storage/thumbs/products/abc.webp', $image->thumb_url);
    }

    public function test_small_images_are_not_upscaled(): void
    {
        Storage::fake('public');
        $this->putJpeg('products/small.jpg', 300, 300);

        $this->product()->images()->create(['path' => 'products/small.jpg']);

        $info = getimagesizefromstring(Storage::disk('public')->get('thumbs/products/small.webp'));
        $this->assertSame(300, $info[0]);
    }

    public function test_thumb_url_falls_back_to_the_original_when_missing(): void
    {
        Storage::fake('public');

        // الأصل غير موجود: لا استثناء، ولا نسخة مصغّرة، والرابط يعود للأصل
        $image = $this->product()->images()->create(['path' => 'products/missing.jpg']);

        Storage::disk('public')->assertMissing('thumbs/products/missing.webp');
        $this->assertStringEndsWith('storage/products/missing.jpg', $image->thumb_url);
    }

    public function test_deleting_an_image_removes_its_thumbnail(): void
    {
        Storage::fake('public');
        $this->putJpeg('products/gone.jpg');
        $image = $this->product()->images()->create(['path' => 'products/gone.jpg']);
        Storage::disk('public')->assertExists('thumbs/products/gone.webp');

        $image->delete();

        Storage::disk('public')->assertMissing('thumbs/products/gone.webp');
    }

    public function test_backfill_command_generates_missing_thumbnails_only(): void
    {
        Storage::fake('public');
        $product = $this->product();
        ProductImage::withoutEvents(function () use ($product) {
            $this->putJpeg('products/old.jpg');
            $product->images()->create(['path' => 'products/old.jpg']);
        });
        Storage::disk('public')->assertMissing('thumbs/products/old.webp');

        $this->artisan('products:thumbnails')->assertSuccessful();

        Storage::disk('public')->assertExists('thumbs/products/old.webp');
    }

    public function test_story_images_get_a_thumbnail_but_keep_the_original_for_the_viewer(): void
    {
        Storage::fake('public');
        $this->putJpeg('stories/s1.jpg', 1080, 1920);

        $story = Story::create([
            'image' => 'stories/s1.jpg',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);

        Storage::disk('public')->assertExists('thumbs/stories/s1.webp');
        Storage::disk('public')->assertExists('stories/s1.jpg');
        $this->assertStringEndsWith('storage/thumbs/stories/s1.webp', $story->image_thumb_url);

        $story->delete();
        Storage::disk('public')->assertMissing('thumbs/stories/s1.webp');
    }

    public function test_api_exposes_thumb_url(): void
    {
        Storage::fake('public');
        $this->putJpeg('products/api.jpg');
        $image = $this->product()->images()->create(['path' => 'products/api.jpg']);

        $data = (new \App\Http\Resources\ProductImageResource($image))->toArray(request());

        $this->assertStringEndsWith('storage/thumbs/products/api.webp', $data['thumb_url']);
        $this->assertStringEndsWith('storage/products/api.jpg', $data['url']);
    }
}
