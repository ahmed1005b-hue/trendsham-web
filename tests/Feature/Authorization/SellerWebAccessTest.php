<?php

namespace Tests\Feature\Authorization;

use App\Models\Category;
use App\Models\DailyDeal;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * مصفوفة الوصول الأفقي لموقع البائع (جلسة ويب): البائع A يحاول قراءة/تعديل موارد متجر B،
 * ومسارات مشتركة (فاتورة، إشعار، عناوين) يهاجمها الزبون A.
 */
class SellerWebAccessTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    /** المهاجم: البائع A. [method, uriTemplate, tenantKey, payload] — في الحمولة `{key.attr}` تُحلّ من موارد B. */
    public const CASES = [
        ['DELETE', 'seller/coupons/{campaign}', 'campaign', []],
        ['GET', 'seller/coupons/{campaign}', 'campaign', []],
        ['GET', 'seller/coupons/{campaign}/edit', 'campaign', []],
        ['POST', 'seller/coupons/{campaign}/toggle', 'campaign', []],
        ['PUT', 'seller/coupons/{campaign}', 'campaign', [
            'title' => 'Hijacked', 'type' => 'fixed', 'value' => 500, 'scope' => 'all', 'audience' => 'collectable',
            'ends_at' => '2099-01-01', 'total_limit' => 10, 'per_customer_limit' => 1,
        ]],
        ['DELETE', 'seller/daily-deals/{deal}', 'deal', []],
        ['GET', 'seller/daily-deals/{deal}/edit', 'deal', []],
        ['POST', 'seller/daily-deals/{deal}/toggle', 'deal', []],
        ['PUT', 'seller/daily-deals/{deal}', 'deal', [
            'product_id' => '{product.id}', 'discount_percentage' => 20, 'starts_at' => '2030-01-01 00:00:00', 'ends_at' => '2030-01-03 00:00:00',
        ]],
        ['DELETE', 'seller/products/{product}', 'product', []],
        ['GET', 'seller/products/{product}/edit', 'product', []],
        ['PUT', 'seller/products/{product}', 'product', [
            'category_id' => '{product.category_id}', 'name' => 'Hijacked', 'sku' => '{product.sku}', 'price' => 5,
            'compare_at_price' => '', 'stock_quantity' => 3, 'status' => 'active',
        ]],
        ['DELETE', 'seller/products/{product}/images/{image}', 'image', []],
        ['POST', 'seller/products/{product}/images/{image}/primary', 'image', []],
        ['DELETE', 'seller/stories/{story}', 'story', []],
        ['DELETE', 'seller/wallet/payment-methods/{paymentMethod}', 'paymentMethod', []],
        ['POST', 'seller/wallet/payment-methods/{paymentMethod}/default', 'paymentMethod', []],
        ['GET', 'seller/conversations/{conversation}', 'conversation', []],
        ['POST', 'seller/conversations/{conversation}/messages', 'conversation', ['body' => 'x']],
        ['GET', 'seller/orders/{order}', 'order', []],
        ['POST', 'seller/orders/{order}/status', 'order', ['status' => 'shipped']],
        ['GET', 'seller/returns/{returnRequest}', 'returnRequest', []],
        ['POST', 'seller/returns/{returnRequest}/approve', 'returnRequest', ['note' => 'x']],
        ['POST', 'seller/returns/{returnRequest}/reject', 'returnRequest', ['note' => 'x']],
        ['POST', 'seller/returns/{returnRequest}/received', 'returnRequest', []],
        ['GET', 'seller/showcases/{showcase}', 'showcase', []],
        ['POST', 'seller/showcases/{showcase}/cancel', 'showcase', []],
        ['GET', 'seller/support/{supportConversation}', 'supportConversation', []],
        ['POST', 'seller/support/{supportConversation}/message', 'supportConversation', ['body' => 'x']],
        ['POST', 'seller/questions/{question}/answer', 'question', ['answer' => 'xx']],
        ['GET', 'withdrawals/{withdrawal}/receipt', 'withdrawal', []],
    ];

    /** المهاجم: الزبون A. المسارات المشتركة. */
    public const SHARED_CASES = [
        ['GET', 'invoices/{order}', 'order', []],
        ['GET', 'notifications/{notification}/read', 'notification', []],
        ['DELETE', 'profile/addresses/{address}', 'address', []],
        ['PUT', 'profile/addresses/{address}', 'address', ['label' => 'home', 'city' => 'Hijacked', 'details' => 'Hijacked details']],
        ['POST', 'profile/addresses/{address}/default', 'address', []],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('local');
    }

    /** قيم تعرّف المستأجر B ولا تظهر عند A. */
    private function identifyingValues(array $b): array
    {
        return [
            'Product b', 'Store b', 'Campaign b', 'Help b', 'Hello b', 'Support b', 'Showcase b', 'Question b?', 'Withdrawal b',
            'Notification b', 'Body b', 'City b', 'Details b', 'Address b', 'Customer b', 'Seller b', 'Story b',
            $b['order']->order_number, $b['paymentMethod']->account_number, $b['customer']->email, $b['seller']->email,
        ];
    }

    /** يجعل للسحب إيصالاً فعلياً حتى لا يكون 404 سببه غياب الإيصال لا الملكية. */
    private function withReceipt($withdrawal): void
    {
        $path = "receipts/withdrawal-{$withdrawal->id}.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4 receipt');
        $withdrawal->forceFill(['status' => 'paid', 'receipt_path' => $path, 'transfer_reference' => 'REF-'.$withdrawal->id])->save();
    }

    private function resolvePayload(array $payload, array $b): array
    {
        return array_map(function ($v) use ($b) {
            if (is_string($v) && preg_match('/^\{(\w+)\.(\w+)\}$/', $v, $m)) {
                return $b[$m[1]]->{$m[2]};
            }

            return $v;
        }, $payload);
    }

    private function assertUnchanged(array $cases, array $b, array $values): void
    {
        foreach ($cases as [$method, $template, $key, $payload]) {
            $model = $b[$key];
            $before = $model->fresh()->getAttributes();
            $uri = $this->resolveUri($template, $b);
            $case = "$method $uri";

            $response = $this->call($method, $uri, $this->resolvePayload($payload, $b));
            $this->assertWebDenied($response, $case, $values, $uri);

            $after = $model->fresh();
            $this->assertNotNull($after, "$case: row was deleted");
            $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($after->getAttributes()), "$case: resource changed");
        }
    }

    public function test_seller_cannot_touch_another_stores_resources(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->withReceipt($b['withdrawal']);
        $this->actingAs($a['seller']);

        $this->assertUnchanged(self::CASES, $b, $this->identifyingValues($b));
    }

    public function test_customer_cannot_touch_another_users_shared_resources(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['customer']);

        $this->assertUnchanged(self::SHARED_CASES, $b, $this->identifyingValues($b));
    }

    public function test_seller_cannot_download_invoice_of_another_stores_order(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller']);

        $uri = '/invoices/'.$b['order']->getRouteKey();
        $this->assertWebDenied($this->get($uri), "GET $uri", $this->identifyingValues($b), $uri);
    }

    public function test_ownership_is_checked_before_state_on_orders_showcases_coupons_and_deals(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller']);

        // حالات لا تقبل أي انتقال: لو فُحصت الحالة أولاً لجاء رد حالة بدل الرفض بسبب الملكية
        $b['order']->update(['status' => 'cancelled']);
        foreach (['shipped', 'processing', 'cancelled', 'bogus'] as $status) {
            $this->post('/seller/orders/'.$b['order']->id.'/status', ['status' => $status])->assertForbidden();
        }
        $this->assertSame('cancelled', $b['order']->fresh()->status);

        $b['showcase']->update(['status' => 'rejected']);
        $this->post('/seller/showcases/'.$b['showcase']->id.'/cancel')->assertForbidden();
        $b['showcase']->update(['status' => 'pending_review', 'start_date' => today()->subDay()]);
        $this->post('/seller/showcases/'.$b['showcase']->id.'/cancel')->assertForbidden();
        $this->assertSame('pending_review', $b['showcase']->fresh()->status);

        // حملة موقوفة/مقفلة: الحالة تعطي رداً مختلفاً (تحويل برسالة أو 403) لو فُحصت قبل الملكية؛ المطلوب 404 دائماً
        foreach ([['suspended', 0], ['rejected', 0], ['pending_review', 0], ['active', 3]] as [$status, $issued]) {
            $b['campaign']->forceFill(['status' => $status, 'issued_count' => $issued])->save();
            $id = $b['campaign']->id;
            $this->post("/seller/coupons/$id/toggle")->assertNotFound();
            $this->delete("/seller/coupons/$id")->assertNotFound();
            $this->get("/seller/coupons/$id/edit")->assertNotFound();
            $this->put("/seller/coupons/$id", ['ends_at' => '2099-01-01', 'total_limit' => 10])->assertNotFound();
        }
        $this->assertNotNull($b['campaign']->fresh());

        // عرض B غير نشط: التبديل/التعديل يُرفض 403 بسبب الملكية
        $b['deal']->update(['is_active' => false]);
        $this->post('/seller/daily-deals/'.$b['deal']->id.'/toggle')->assertForbidden();
        $this->assertFalse((bool) $b['deal']->fresh()->is_active);
    }

    public function test_deal_update_with_sellers_own_product_cannot_hijack_another_stores_deal(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller']);

        // منتج A صالح لمدقق الحقول (مفعّل ومعتمد وبلا عرض آخر عليه)، فلا يأتي الرفض من 404 للمنتج
        $a['deal']->delete();
        $this->put('/seller/daily-deals/'.$b['deal']->id, [
            'product_id' => $a['product']->id, 'discount_percentage' => 20,
            'starts_at' => '2030-01-01 00:00:00', 'ends_at' => '2030-01-03 00:00:00',
        ])->assertForbidden();
        $this->assertSame($b['product']->id, $b['deal']->fresh()->product_id);
        $this->assertSame(10, (int) $b['deal']->fresh()->discount_percentage);
    }

    public function test_nested_image_of_another_product_is_denied(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller']);

        $uri = '/seller/products/'.$a['product']->id.'/images/'.$b['image']->id;
        $before = $b['image']->fresh()->getAttributes();
        $this->assertWebDenied($this->delete($uri), "DELETE $uri", $this->identifyingValues($b), 'images/'.$b['image']->id);
        $this->assertWebDenied($this->post($uri.'/primary'), "POST $uri/primary", $this->identifyingValues($b), 'images/'.$b['image']->id);

        $this->assertNotNull($b['image']->fresh(), 'image of B was deleted');
        $this->assertEquals($this->stableAttributes($before), $this->stableAttributes($b['image']->fresh()->getAttributes()));
    }

    public function test_positive_controls_owner_can_use_own_resources(): void
    {
        // بعض صفحات البائع (محادثات/دعم) تترك مخزن إخراج مفتوحاً بعد رسمها؛ نغلق ما زاد حتى لا يُعدّ الاختبار محفوفاً بالمخاطر
        $obLevel = ob_get_level();
        $this->beforeApplicationDestroyed(function () use ($obLevel) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
        });

        $a = $this->tenant('a');
        $this->withReceipt($a['withdrawal']);
        $this->actingAs($a['seller']);
        $a['order']->update(['status' => 'processing']);

        $this->get('/seller/coupons/'.$a['campaign']->id)->assertOk();
        $this->get('/seller/coupons/'.$a['campaign']->id.'/edit')->assertOk();
        $this->put('/seller/coupons/'.$a['campaign']->id, [
            'title' => 'Renamed', 'type' => 'fixed', 'value' => 500, 'scope' => 'all', 'audience' => 'collectable',
            'ends_at' => now()->addDays(10)->format('Y-m-d'), 'total_limit' => 10, 'per_customer_limit' => 1,
        ])->assertRedirect(route('seller.coupons.show', $a['campaign']));
        $this->assertSame('Renamed', $a['campaign']->fresh()->title);
        $this->post('/seller/coupons/'.$a['campaign']->id.'/toggle')->assertRedirect();
        $this->assertSame('paused', $a['campaign']->fresh()->status);
        $this->delete('/seller/coupons/'.$a['campaign']->id)->assertRedirect(route('seller.coupons.index'));
        $this->assertSoftDeleted($a['campaign']);

        $this->get('/seller/daily-deals/'.$a['deal']->id.'/edit')->assertOk();
        $this->put('/seller/daily-deals/'.$a['deal']->id, [
            'product_id' => $a['product']->id, 'discount_percentage' => 25,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertRedirect(route('seller.daily-deals.index'));
        $this->assertSame(25, (int) $a['deal']->fresh()->discount_percentage);
        $this->post('/seller/daily-deals/'.$a['deal']->id.'/toggle')->assertRedirect();
        $this->assertFalse((bool) $a['deal']->fresh()->is_active);
        $this->delete('/seller/daily-deals/'.$a['deal']->id)->assertRedirect();
        $this->assertSoftDeleted($a['deal']);

        $this->get('/seller/products/'.$a['product']->id.'/edit')->assertOk();
        $this->put('/seller/products/'.$a['product']->id, [
            'category_id' => $a['product']->category_id, 'name' => 'Renamed product', 'sku' => $a['product']->sku,
            'price' => 5, 'compare_at_price' => '', 'stock_quantity' => 3, 'status' => 'active',
        ])->assertRedirect(route('seller.products.index'));
        $this->assertSame('Renamed product', $a['product']->fresh()->name);
        $this->post('/seller/products/'.$a['product']->id.'/images/'.$a['image']->id.'/primary')->assertRedirect();
        $this->delete('/seller/products/'.$a['product']->id.'/images/'.$a['image']->id)->assertRedirect();
        $this->assertNull($a['image']->fresh());

        $this->delete('/seller/stories/'.$a['story']->id)->assertRedirect();
        $this->assertFalse((bool) $a['story']->fresh()->is_active);

        $this->post('/seller/wallet/payment-methods/'.$a['paymentMethod']->id.'/default')->assertRedirect();
        $this->delete('/seller/wallet/payment-methods/'.$a['paymentMethod']->id)->assertRedirect();
        $this->assertNull($a['paymentMethod']->fresh());

        $this->get('/seller/conversations/'.$a['conversation']->id)->assertOk();
        $this->post('/seller/conversations/'.$a['conversation']->id.'/messages', ['body' => 'hi'])->assertRedirect();

        $this->get('/seller/orders/'.$a['order']->id)->assertOk();
        $this->post('/seller/orders/'.$a['order']->id.'/status', ['status' => 'shipped'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('shipped', $a['order']->fresh()->status);

        $this->get('/seller/showcases/'.$a['showcase']->id)->assertOk();
        $this->post('/seller/showcases/'.$a['showcase']->id.'/cancel')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('cancelled', $a['showcase']->fresh()->status);

        $this->get('/seller/support/'.$a['supportConversation']->id)->assertOk();
        $this->post('/seller/support/'.$a['supportConversation']->id.'/message', ['body' => 'hi'])->assertRedirect();

        $this->post('/seller/questions/'.$a['question']->id.'/answer', ['answer' => 'xx'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, $a['question']->answers()->count());

        $receipt = $this->get('/withdrawals/'.$a['withdrawal']->id.'/receipt')->assertOk();
        $this->assertStringContainsString('receipt', $receipt->streamedContent());
    }

    public function test_positive_controls_owner_handles_own_return(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $this->actingAs($a['seller']);
        $id = $a['returnRequest']->id;

        $this->get("/seller/returns/$id")->assertOk()->assertSee($a['returnRequest']->number);
        $this->post("/seller/returns/$id/approve", ['note' => 'ok'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('approved', $a['returnRequest']->fresh()->status);
        $this->post("/seller/returns/$id/received")->assertRedirect()->assertSessionHas('success');
        $this->assertSame('received', $a['returnRequest']->fresh()->status);

        $this->actingAs($b['seller']);
        $this->post('/seller/returns/'.$b['returnRequest']->id.'/reject', ['note' => 'no'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('rejected', $b['returnRequest']->fresh()->status);
    }

    public function test_positive_controls_shared_routes_for_the_owner(): void
    {
        $a = $this->tenant('a');
        $this->actingAs($a['customer']);

        $this->get('/invoices/'.$a['order']->getRouteKey())->assertOk();
        $this->get('/notifications/'.$a['notification']->getRouteKey().'/read')->assertRedirect();
        $this->assertNotNull($a['notification']->fresh()->read_at);
        $this->put('/profile/addresses/'.$a['address']->id, ['label' => 'work', 'city' => 'Renamed', 'details' => 'New details'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Renamed', $a['address']->fresh()->city);
        $this->post('/profile/addresses/'.$a['address']->id.'/default')->assertRedirect()->assertSessionHas('success');
        $this->delete('/profile/addresses/'.$a['address']->id)->assertRedirect()->assertSessionHas('success');
        $this->assertSoftDeleted($a['address']);
    }

    public function test_daily_deal_store_rejects_another_stores_product_id(): void
    {
        $a = $this->tenant('a');
        $b = $this->tenant('b');
        $category = Category::firstOrCreate(['slug' => 'tenant-shared'], ['name' => 'Tenant shared', 'is_active' => true]);
        $make = fn (SellerProfile $store, string $name) => Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id, 'name' => $name, 'slug' => 'p-'.Str::random(8),
            'description' => $name, 'sku' => 'SKU-'.Str::random(8), 'price' => 10000, 'stock_quantity' => 10,
            'status' => 'active', 'admin_approved_at' => now(),
        ]);
        // منتجان بلا عروض سابقة، فلا يأتي الرفض من تعارض الفترات
        $bProduct = $make($b['store'], 'B free product');
        $aProduct = $make($a['store'], 'A free product');
        $deal = fn (int $productId) => [
            'product_id' => $productId, 'discount_percentage' => 10,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDays(2)->toDateTimeString(),
        ];
        $this->actingAs($a['seller']);
        $dealsBefore = DailyDeal::count();

        $response = $this->post('/seller/daily-deals', $deal($bProduct->id));
        $this->assertContains($response->getStatusCode(), [403, 404], 'B product accepted or failed for another reason');
        $this->assertSame($dealsBefore, DailyDeal::count(), 'deal created');
        $this->assertSame(0, DailyDeal::where('product_id', $bProduct->id)->count());

        $this->post('/seller/daily-deals', $deal($aProduct->id))->assertRedirect(route('seller.daily-deals.index'));
        $this->assertSame(1, DailyDeal::where('product_id', $aProduct->id)->count(), 'own product deal was not created');
    }
}
