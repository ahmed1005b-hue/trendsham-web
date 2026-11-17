<?php

namespace Tests\Feature\Authorization;

use App\Coupons\Models\CouponCampaign;
use App\Models\Category;
use App\Models\DailyDeal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\Showcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * مصفوفة الوصول الأفقي: البائع A يحاول قراءة/تعديل موارد متجر البائع B عبر الـ API.
 */
class SellerApiAccessTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** [method, uriTemplate, tenantKey, payload] */
    public const CASES = [
        ['DELETE', 'api/v1/seller/daily-deals/{deal}', 'deal', []],
        ['PUT', 'api/v1/seller/daily-deals/{deal}', 'deal', []],
        ['POST', 'api/v1/seller/daily-deals/{deal}/toggle', 'deal', []],
        ['DELETE', 'api/v1/seller/products/{product}', 'product', []],
        ['POST', 'api/v1/seller/products/{product}', 'product', []],
        ['PUT', 'api/v1/seller/products/{product}/quick-stock', 'product', ['set' => 99]],
        ['DELETE', 'api/v1/seller/products/{product}/images/{image}', 'image', []],
        ['POST', 'api/v1/seller/products/{product}/images/{image}/primary', 'image', []],
        ['DELETE', 'api/v1/seller/stories/{story}', 'story', []],
        ['DELETE', 'api/v1/seller/wallet/payment-methods/{paymentMethod}', 'paymentMethod', []],
        ['POST', 'api/v1/seller/wallet/payment-methods/{paymentMethod}/default', 'paymentMethod', []],
        ['GET', 'api/v1/seller/showcases/{showcase}', 'showcase', []],
        ['POST', 'api/v1/seller/showcases/{showcase}/cancel', 'showcase', []],
        ['GET', 'api/v1/seller/support/{supportConversation}', 'supportConversation', []],
        ['POST', 'api/v1/seller/support/{supportConversation}/message', 'supportConversation', ['body' => 'x']],
        ['POST', 'api/v1/seller/questions/{question}/answer', 'question', ['answer' => 'x']],
        ['PUT', 'api/v1/seller/orders/{order}/status', 'order', ['status' => 'shipped']],
        ['GET', 'api/v1/seller/coupons/{campaign}', 'campaign', []],
        ['PUT', 'api/v1/seller/coupons/{campaign}', 'campaign', ['title' => 'Hijacked', 'type' => 'fixed', 'value' => 5, 'scope' => 'all', 'audience' => 'collectable', 'ends_at' => '2099-01-01', 'total_limit' => 5, 'per_customer_limit' => 1]],
        ['POST', 'api/v1/seller/coupons/{campaign}/toggle', 'campaign', []],
        ['DELETE', 'api/v1/seller/coupons/{campaign}', 'campaign', []],
        ['GET', 'api/v1/seller/returns/{returnRequest}', 'returnRequest', []],
        ['POST', 'api/v1/seller/returns/{returnRequest}/approve', 'returnRequest', []],
        ['POST', 'api/v1/seller/returns/{returnRequest}/reject', 'returnRequest', ['note' => 'x']],
        ['POST', 'api/v1/seller/returns/{returnRequest}/received', 'returnRequest', []],
    ];

    public function test_seller_cannot_touch_another_stores_resources(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller'], 'sanctum');

        foreach (self::CASES as [$method, $template, $key, $payload]) {
            $model = $b[$key];
            $before = $model->fresh()->getAttributes();
            $uri = $this->resolveUri($template, $b);
            $case = "$method $uri";

            $response = $this->json($method, $uri, $payload);
            $this->assertDenied($response, $case);

            $after = $model->fresh();
            $this->assertNotNull($after, "$case: row was deleted");
            $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($after->getAttributes()), "$case: resource changed");
        }
    }

    public function test_ownership_is_checked_before_state_on_status_and_cancel(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller'], 'sanctum');

        // حالات لا تقبل أي انتقال: لو فُحصت الحالة أولاً لجاء 409/422 بدل الرفض بسبب الملكية
        $b['order']->update(['status' => 'cancelled']);
        $b['showcase']->update(['status' => 'rejected']);

        foreach (['shipped', 'processing', 'cancelled', 'bogus'] as $status) {
            $this->assertDenied($this->putJson('/api/v1/seller/orders/'.$b['order']->id.'/status', ['status' => $status]), "status=$status");
        }
        $this->assertDenied($this->postJson('/api/v1/seller/showcases/'.$b['showcase']->id.'/cancel'), 'cancel rejected showcase');

        $b['showcase']->update(['status' => 'pending_review', 'start_date' => today()->subDay()]);
        $this->assertDenied($this->postJson('/api/v1/seller/showcases/'.$b['showcase']->id.'/cancel'), 'cancel started showcase');
    }

    public function test_quick_stock_with_a_valid_payload_is_denied_for_another_stores_product(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller'], 'sanctum');

        // الحقل الفعلي للمسار هو set/change (الحمولة في CASES غير صالحة، فنختبر الصالحة هنا)
        $this->assertDenied($this->putJson('/api/v1/seller/products/'.$b['product']->id.'/quick-stock', ['set' => 99]), 'quick-stock set');
        $this->assertDenied($this->putJson('/api/v1/seller/products/'.$b['product']->id.'/quick-stock', ['change' => 5]), 'quick-stock change');
        $this->assertSame(10, $b['product']->fresh()->stock_quantity);
    }

    public function test_nested_image_of_another_product_is_denied(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller'], 'sanctum');

        $uri = '/api/v1/seller/products/'.$a['product']->id.'/images/'.$b['image']->id;
        $before = $b['image']->fresh()->getAttributes();
        $this->assertDenied($this->deleteJson($uri), "DELETE $uri");
        $this->assertDenied($this->postJson($uri.'/primary'), "POST $uri/primary");

        $this->assertNotNull($b['image']->fresh(), 'image of B was deleted');
        $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($b['image']->fresh()->getAttributes()));
        $this->assertSame(1, ProductImage::where('product_id', $a['product']->id)->count(), 'A images changed');
    }

    public function test_body_ids_of_another_store_are_rejected(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller'], 'sanctum');

        $a['store']->walletOrCreate()->update(['balance' => 100_000_00]);
        $balance = $a['store']->walletOrCreate()->fresh()->balance;

        // --- حجز مساحة مؤجرة: 5 منتجات، أحدها (أو كلها) من متجر B ---
        $aProducts = collect(range(1, 4))->map(fn ($i) => $this->makeProduct($a['store'], "A extra $i"));
        $bProducts = collect(range(1, 5))->map(fn ($i) => $this->makeProduct($b['store'], "B extra $i"));
        $booking = fn (array $ids) => [
            'slot_id' => $a['showcase']->slot_id,
            'start_date' => today()->addDays(10)->toDateString(),
            'end_date' => today()->addDays(11)->toDateString(),
            'title' => 'Booking', 'gradient_colors' => ['#0F3D2E', '#D4A537'], 'gradient_direction' => 'diagonal',
            'product_ids' => $ids,
        ];
        $aIds = $aProducts->pluck('id')->push($a['product']->id)->all();
        $showcasesBefore = Showcase::where('seller_id', $a['store']->id)->count();

        $allB = $this->postJson('/api/v1/seller/showcases', $booking($bProducts->pluck('id')->all()));
        $mixed = $this->postJson('/api/v1/seller/showcases', $booking(array_merge(array_slice($aIds, 0, 4), [$b['product']->id])));
        foreach ([$allB, $mixed] as $r) {
            $this->assertContains($r->getStatusCode(), [403, 404, 422]);
            $r->assertJsonValidationErrors('product_ids'); // السبب هو المنتجات لا حقل آخر ناقص
        }
        $this->assertSame($showcasesBefore, Showcase::where('seller_id', $a['store']->id)->count(), 'showcase created for A');
        $this->assertSame($balance, $a['store']->walletOrCreate()->fresh()->balance, 'wallet was debited');

        // --- عرض يومي بمنتج من B (بلا عرض سابق عليه، فلا يأتي الرفض من تعارض الفترات) ---
        $deal = fn (int $productId) => [
            'product_id' => $productId, 'discount_percentage' => 10,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDays(2)->toDateTimeString(),
        ];
        $dealsBefore = DailyDeal::count();
        $r = $this->postJson('/api/v1/seller/daily-deals', $deal($bProducts[0]->id));
        $this->assertContains($r->getStatusCode(), [403, 404, 422]);
        $this->assertSame($dealsBefore, DailyDeal::count(), 'deal created');

        // تعديل عرض A ليشير إلى منتج B
        $r = $this->putJson('/api/v1/seller/daily-deals/'.$a['deal']->id, $deal($bProducts[0]->id));
        $this->assertContains($r->getStatusCode(), [403, 404, 422]);
        $this->assertSame($a['product']->id, $a['deal']->fresh()->product_id, 'deal re-pointed to B product');

        // --- كوبون البائع (مسار الويب، لا مسار API) ---
        $coupon = fn (array $over) => $over + [
            'title' => 'Coupon', 'type' => 'percentage', 'value' => 10, 'max_discount' => 20000,
            'scope' => 'products', 'audience' => 'collectable',
            'ends_at' => now()->addDays(14)->format('Y-m-d'), 'total_limit' => 100, 'per_customer_limit' => 1,
        ];
        $campaignsBefore = CouponCampaign::count();
        $r = $this->actingAs($a['seller'])->postJson('/seller/coupons', $coupon(['product_ids' => [$b['product']->id]]));
        $r->assertStatus(422)->assertJsonValidationErrors('product_ids.0');
        $gift = $coupon(['type' => 'gift', 'buy_product_id' => $b['product']->id, 'gift_product_id' => $bProducts[0]->id, 'scope' => 'all']);
        $r = $this->actingAs($a['seller'])->postJson('/seller/coupons', $gift);
        $r->assertStatus(422)->assertJsonValidationErrors(['buy_product_id', 'gift_product_id']);
        $this->assertSame($campaignsBefore, CouponCampaign::count(), 'campaign created');

        // --- الشواهد الإيجابية: نفس الحمولات بمنتجات A تنجح، فالرفض أعلاه سببه معرّف B فقط ---
        $this->actingAs($a['seller'], 'sanctum');
        $this->postJson('/api/v1/seller/showcases', $booking($aIds))->assertCreated();
        $this->postJson('/api/v1/seller/daily-deals', $deal($aProducts[0]->id))->assertCreated();
        $this->actingAs($a['seller'])->postJson('/seller/coupons', $coupon(['product_ids' => [$a['product']->id]]))->assertRedirect(route('seller.coupons.index'));
        $this->assertSame($campaignsBefore + 1, CouponCampaign::count());
    }

    public function test_shared_order_shows_only_own_items(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller'], 'sanctum');

        $order = Order::create([
            'order_number' => 'TS-SHARED-'.Str::upper(Str::random(6)), 'user_id' => $a['customer']->id,
            'status' => 'processing', 'payment_status' => 'paid', 'subtotal' => 88888, 'shipping_fee' => 0,
            'discount_amount' => 0, 'total' => 88888, 'shipping_name' => 'X', 'shipping_phone' => '0900000000', 'shipping_address' => 'X',
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $a['product']->id, 'vendor_id' => $a['store']->id,
            'product_name' => 'AAA-OWN-ITEM', 'unit_price' => 11100, 'quantity' => 1, 'subtotal' => 11100,
            'commission_amount' => 1234, 'vendor_earning' => 9866,
        ]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $b['product']->id, 'vendor_id' => $b['store']->id,
            'product_name' => 'BBB-OTHER-STORE-ITEM', 'unit_price' => 77700, 'quantity' => 1, 'subtotal' => 77700,
            'commission_amount' => 4321, 'vendor_earning' => 73379,
        ]);

        $response = $this->getJson('/api/v1/seller/orders')->assertOk();
        $names = collect($response->json('data'))->pluck('product_name');
        $this->assertTrue($names->contains('AAA-OWN-ITEM'), 'own item missing');
        $this->assertFalse($names->contains('BBB-OTHER-STORE-ITEM'));

        $raw = $response->getContent();
        foreach (['BBB-OTHER-STORE-ITEM', '777', '4321', '73379', 'commission_amount', 'vendor_earning'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw, "response leaks $needle");
        }

        // الطلب المشترك: البائع يغيّر طرده فقط، ولا يُكشف بند المتجر الآخر في الرد
        $r = $this->putJson('/api/v1/seller/orders/'.$order->id.'/status', ['status' => 'shipped']);
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('processing', $order->shipments()->where('seller_profile_id', $b['store']->id)->value('status'));
        $this->assertStringNotContainsString('BBB-OTHER-STORE-ITEM', $r->getContent());
        $this->assertStringNotContainsString('73379', $r->getContent());
    }

    public function test_positive_controls_owner_can_use_own_resources(): void
    {
        $a = $this->tenant('a');
        $this->actingAs($a['seller'], 'sanctum');
        $a['order']->update(['status' => 'processing']);

        $this->putJson('/api/v1/seller/products/'.$a['product']->id.'/quick-stock', ['set' => 99])->assertSuccessful();
        $this->assertSame(99, $a['product']->fresh()->stock_quantity);
        $this->postJson('/api/v1/seller/products/'.$a['product']->id.'/images/'.$a['image']->id.'/primary')->assertSuccessful();
        $this->postJson('/api/v1/seller/daily-deals/'.$a['deal']->id.'/toggle')->assertSuccessful();
        $this->postJson('/api/v1/seller/wallet/payment-methods/'.$a['paymentMethod']->id.'/default')->assertSuccessful();
        $this->getJson('/api/v1/seller/showcases/'.$a['showcase']->id)->assertOk();
        $this->getJson('/api/v1/seller/support/'.$a['supportConversation']->id)->assertOk();
        $this->postJson('/api/v1/seller/support/'.$a['supportConversation']->id.'/message', ['body' => 'x'])->assertSuccessful();
        $this->postJson('/api/v1/seller/questions/'.$a['question']->id.'/answer', ['answer' => 'xx'])->assertSuccessful();
        $this->putJson('/api/v1/seller/orders/'.$a['order']->id.'/status', ['status' => 'shipped'])->assertOk();
        $this->postJson('/api/v1/seller/showcases/'.$a['showcase']->id.'/cancel')->assertOk();
        $this->getJson('/api/v1/seller/returns/'.$a['returnRequest']->id)->assertOk();
        $this->getJson('/api/v1/seller/returns')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/seller/returns/'.$a['returnRequest']->id.'/approve')->assertOk();
        $this->postJson('/api/v1/seller/returns/'.$a['returnRequest']->id.'/received')->assertOk();
        $this->deleteJson('/api/v1/seller/stories/'.$a['story']->id)->assertSuccessful();
        $this->deleteJson('/api/v1/seller/products/'.$a['product']->id.'/images/'.$a['image']->id)->assertSuccessful();
    }

    private function makeProduct(SellerProfile $store, string $name): Product
    {
        $category = Category::firstOrCreate(['slug' => 'tenant-shared'], ['name' => 'Tenant shared', 'is_active' => true]);

        return Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id, 'name' => $name,
            'slug' => 'p-'.Str::random(8), 'description' => $name, 'sku' => 'SKU-'.Str::random(8),
            'price' => 10000, 'stock_quantity' => 10, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }
}
