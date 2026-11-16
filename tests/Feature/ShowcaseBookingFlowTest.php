<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\ShowcaseSlot;
use App\Models\User;
use Database\Seeders\ShowcaseSlotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** المساحات المؤجرة من البداية للنهاية: حجز البائع ← موافقة المدير ← الظهور في الرئيسية */
class ShowcaseBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private SellerProfile $store;

    /** @var list<int> */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShowcaseSlotSeeder::class);

        $this->seller = User::factory()->create(['role' => 'seller']);
        $this->store = SellerProfile::create(['user_id' => $this->seller->id, 'store_name' => 'متجر الإعلان', 'status' => 'approved']);
        $this->store->walletOrCreate()->update(['balance' => 100_000_00]);   // 100,000 ل.س

        $cat = Category::create(['name' => 'قسم', 'slug' => 'c-'.Str::random(5)]);
        foreach (range(1, 6) as $i) {
            $this->productIds[] = Product::create([
                'vendor_id' => $this->store->id, 'category_id' => $cat->id,
                'name' => "منتج {$i}", 'slug' => 'p-'.Str::random(8), 'sku' => 'S-'.Str::random(8),
                'price' => 10000, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
            ])->id;
        }
    }

    protected function sellerUser(): User
    {
        return $this->seller;
    }

    /** الحمولة كما يرسلها نموذج الحجز في المتصفح */
    private function payload(array $override = []): array
    {
        return $override + [
            'slot_id' => ShowcaseSlot::where('key', 'home_top')->value('id'),
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDay()->toDateString(),
            'title' => 'تخفيضات الشتاء',
            'subtitle' => 'خصم على كل شي',
            'gradient_colors' => ['#0F3D2E', '#2E7D5B'],
            'gradient_direction' => 'diagonal',
            'product_ids' => array_slice($this->productIds, 0, 5),
        ];
    }

    public function test_api_sends_up_to_five_card_images_per_product_like_the_web_card(): void
    {
        $first = Product::findOrFail($this->productIds[0]);
        foreach (range(1, 6) as $i) {
            $first->images()->create(['path' => "products/p{$i}.jpg", 'is_primary' => $i === 1, 'sort_order' => $i]);
        }
        $second = Product::findOrFail($this->productIds[1]);
        $second->images()->create(['path' => 'products/only.jpg', 'is_primary' => true]);

        $this->actingAs($this->seller)->post(route('seller.showcases.store'), $this->payload())->assertSessionHasNoErrors();
        $showcase = Showcase::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.showcases.approve', $showcase));
        $this->app['auth']->forgetGuards();

        $products = collect($this->getJson('/api/v1/home/showcases')->assertOk()->json('data.0.products'))->keyBy('id');

        $many = $products[$first->id];
        $this->assertCount(5, $many['images']);                       // مثل بطاقة الويب: أول 5 صور
        $this->assertStringEndsWith('products/p1.jpg', $many['images'][0]); // بترتيب sort_order
        $this->assertStringEndsWith('products/p1.jpg', $many['image_url']);  // الحقل القديم باقٍ للتطبيقات القديمة

        $this->assertCount(1, $products[$second->id]['images']);
        $this->assertSame([], $products[$this->productIds[2]]['images']);   // بلا صور: قائمة فارغة لا null
    }

    public function test_create_page_renders_for_seller(): void
    {
        $this->actingAs($this->seller)->get(route('seller.showcases.create'))
            ->assertOk()
            ->assertSee('name="product_ids[]"', false)
            ->assertSee('id="submitBtn"', false);
    }

    public function test_seller_books_admin_approves_and_home_shows_it(): void
    {
        $this->actingAs($this->seller)->post(route('seller.showcases.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('seller.showcases.index'));

        $showcase = Showcase::firstOrFail();
        $this->assertSame('pending_review', $showcase->status);
        $perDay = (float) ShowcaseSlot::where('key', 'home_top')->value('price_per_day');
        $this->assertSame((int) round((100_000 - 2 * $perDay) * 100), (int) $this->store->wallet()->first()->balance);   // يومان

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.showcases.approve', $showcase))->assertRedirect();
        $this->assertSame('approved', $showcase->refresh()->status);

        $this->app['auth']->forgetGuards();
        $this->get(route('home'))->assertOk()->assertSee('تخفيضات الشتاء')->assertSee('منتج 1');
    }

    public function test_availability_endpoint_reports_the_slot(): void
    {
        $this->actingAs($this->seller)
            ->getJson(route('seller.showcases.slots-availability', ['start_date' => today()->toDateString(), 'end_date' => today()->addDay()->toDateString()]))
            ->assertOk()
            ->assertJsonFragment(['key' => 'home_top', 'available' => true]);
    }

    public function test_admin_can_deactivate_and_reactivate_a_slot(): void
    {
        $slot = ShowcaseSlot::where('key', 'home_top')->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);
        $fields = $slot->only(['key', 'name', 'position', 'price_per_day', 'min_days', 'max_days', 'capacity', 'min_products', 'max_products']);

        $this->actingAs($admin)->put(route('admin.showcase-slots.update', $slot), $fields + ['is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($slot->refresh()->is_active);

        $this->put(route('admin.showcase-slots.update', $slot), $fields + ['is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($slot->refresh()->is_active);
    }

    public function test_every_showcase_page_renders_after_a_booking(): void
    {
        $this->actingAs($this->seller)->post(route('seller.showcases.store'), $this->payload())->assertSessionHasNoErrors();
        $showcase = Showcase::firstOrFail();

        $this->get(route('seller.showcases.index'))->assertOk()->assertSee('تخفيضات الشتاء');
        $this->get(route('seller.showcases.show', $showcase))->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.showcases.index'))->assertOk()->assertSee('تخفيضات الشتاء');
        $this->get(route('admin.showcase-slots.index'))->assertOk();
        $this->get(route('admin.showcase-slots.create'))->assertOk();
        $this->get(route('admin.showcase-slots.edit', $showcase->slot_id))->assertOk();
        $this->post(route('admin.showcases.approve', $showcase))->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/home/showcases')->assertOk()->assertJsonFragment(['title' => 'تخفيضات الشتاء']);
    }

    public function test_seller_picks_any_text_color_and_home_and_api_use_it(): void
    {
        $this->actingAs($this->seller)
            ->post(route('seller.showcases.store'), $this->payload(['text_color_hex' => '#f1d9a0']))
            ->assertSessionHasNoErrors();

        $showcase = Showcase::firstOrFail();
        $this->assertSame('#F1D9A0', $showcase->text_hex);
        $this->assertSame('light', $showcase->text_color);   // للتطبيقات القديمة

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.showcases.approve', $showcase));
        $this->app['auth']->forgetGuards();
        $this->get(route('home'))->assertOk()->assertSee('--sc-text: #F1D9A0', false);
        $this->getJson('/api/v1/home/showcases')->assertOk()->assertJsonFragment(['text_hex' => '#F1D9A0']);
    }

    public function test_low_contrast_is_the_sellers_choice_not_a_rejection(): void
    {
        // خط أخضر على تدرج أخضر: تحذير في الصفحة فقط، والحجز يمر للمراجعة
        $this->actingAs($this->seller)
            ->post(route('seller.showcases.store'), $this->payload(['text_color_hex' => '#2E7D5B']))
            ->assertSessionHasNoErrors();

        $showcase = Showcase::firstOrFail();
        $this->assertLessThan(3.0, $showcase->contrastRatio());
        $this->assertSame('pending_review', $showcase->status);
    }

    public function test_background_image_is_stored_and_shown_behind_product_cards(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $image = \Illuminate\Http\UploadedFile::fake()->image('bg.jpg', 1600, 500);

        $this->actingAs($this->seller)
            ->post(route('seller.showcases.store'), $this->payload(['background_image' => $image]))
            ->assertSessionHasNoErrors();

        $showcase = Showcase::firstOrFail();
        $this->assertNotNull($showcase->background_image);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($showcase->background_image);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.showcases.approve', $showcase));
        $this->app['auth']->forgetGuards();

        $this->get(route('home'))->assertOk()
            ->assertSee('storage/'.$showcase->background_image, false)
            ->assertSee('class="ts-showcase"', false)
            ->assertSee('ts-market-card', false)          // بطاقة المنتج الموحّدة
            ->assertSee('data-ts-rail', false);           // سكة أفقية بأسهم
        $this->getJson('/api/v1/home/showcases')->assertOk()
            ->assertJsonFragment(['background_url' => asset('storage/'.$showcase->background_image)]);
    }

    public function test_background_image_must_be_an_image(): void
    {
        $this->actingAs($this->seller)
            ->post(route('seller.showcases.store'), $this->payload(['background_image' => \Illuminate\Http\UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('background_image');
        $this->assertSame(0, Showcase::count());
    }

    public function test_failed_booking_does_not_leave_the_uploaded_image_behind(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->store->wallet()->first()->update(['balance' => 0]);

        $this->actingAs($this->seller)
            ->post(route('seller.showcases.store'), $this->payload(['background_image' => \Illuminate\Http\UploadedFile::fake()->image('bg.jpg', 1600, 500)]))
            ->assertSessionHasErrors('amount');

        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('public')->allFiles('showcases'));
    }

    public function test_invalid_text_color_is_rejected(): void
    {
        $this->actingAs($this->seller)
            ->post(route('seller.showcases.store'), $this->payload(['text_color_hex' => 'red']))
            ->assertSessionHasErrors('text_color_hex');
    }

    public function test_without_a_text_color_the_old_auto_choice_is_kept(): void
    {
        $this->actingAs($this->seller)->post(route('seller.showcases.store'), $this->payload())->assertSessionHasNoErrors();

        $showcase = Showcase::firstOrFail();
        $this->assertNull($showcase->text_hex);
        $this->assertSame('#FFFFFF', $showcase->textHex());
    }

    public function test_every_offered_color_preset_is_accepted_by_the_server(): void
    {
        $page = $this->actingAs($this->seller)->get(route('seller.showcases.create'))->getContent();
        preg_match_all("/data-colors='([^']+)'/", $page, $m);
        $this->assertNotEmpty($m[1]);

        foreach ($m[1] as $json) {
            $colors = json_decode(html_entity_decode($json), true);
            [, $ratio] = \App\Support\ColorContrast::pick($colors);
            $this->assertGreaterThanOrEqual(3.0, $ratio, 'preset rejected by server: '.implode(',', $colors));
        }
    }
}
