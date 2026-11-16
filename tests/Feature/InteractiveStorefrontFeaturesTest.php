<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\Story;
use App\Models\User;
use App\Services\BusinessHoursStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InteractiveStorefrontFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(string $name = 'متجر الاختبار'): SellerProfile
    {
        $seller = User::factory()->create(['role' => 'seller']);

        return SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => $name,
            'status' => 'approved',
        ]);
    }

    private function makeProduct(SellerProfile $store, string $name, int $price, int $stock = 5, bool $live = true): Product
    {
        $category = Category::create(['name' => 'قسم', 'slug' => 'cat-'.uniqid()]);

        return Product::create([
            'vendor_id' => $store->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => 'product-'.uniqid(),
            'sku' => 'sku-'.uniqid(),
            'price' => $price,
            'stock_quantity' => $stock,
            'status' => 'active',
            'admin_approved_at' => $live ? now() : null,
        ]);
    }

    private function review(Product $product, int $rating): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => 'TEST-'.uniqid(),
            'user_id' => $customer->id,
            'status' => 'delivered',
            'subtotal' => $product->price,
            'shipping_fee' => 0,
            'total' => $product->price,
            'shipping_name' => 'عميل',
            'shipping_phone' => '0999999999',
            'shipping_address' => 'عنوان تجريبي',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'vendor_id' => $product->vendor_id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 1,
            'subtotal' => $product->price,
        ]);
        Review::create([
            'product_id' => $product->id,
            'user_id' => $customer->id,
            'order_item_id' => $item->id,
            'rating' => $rating,
        ]);
    }

    public function test_customer_can_follow_and_unfollow_store_only_once(): void
    {
        $store = $this->makeStore();
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/stores/{$store->id}/follow")
            ->assertOk()->assertJsonPath('followed', true)->assertJsonPath('followers_count', 1);
        $customer->favorites()->create(['product_id' => $this->makeProduct($store, 'محفوظ', 2000)->id]);
        $this->getJson("/api/v1/stores/{$store->id}")
            ->assertOk()
            ->assertJsonPath('store.is_followed', true)
            ->assertJsonPath('products.data.0.is_favorited', true);
        $this->postJson("/api/v1/stores/{$store->id}/follow")
            ->assertOk()->assertJsonPath('followed', false)->assertJsonPath('followers_count', 0);
        $this->assertDatabaseCount('store_followers', 0);

        $this->actingAs(User::factory()->create(['role' => 'seller']))
            ->postJson("/api/v1/stores/{$store->id}/follow")
            ->assertForbidden();
    }

    public function test_followers_are_notified_once_when_a_product_goes_public_and_on_offer(): void
    {
        $store = $this->makeStore();
        $customer = User::factory()->create(['role' => 'customer']);
        $store->followers()->create(['user_id' => $customer->id]);
        $product = $this->makeProduct($store, 'منتج جديد', 12000, 4, false);
        $this->assertDatabaseMissing('notifications', ['user_id' => $customer->id, 'type' => 'store_new_product']);

        $product->update(['admin_approved_at' => now()]);
        $product->update(['name' => 'تم تعديل العنوان']);
        $this->assertSame(1, AppNotification::where('user_id', $customer->id)->where('type', 'store_new_product')->count());
        $this->assertSame('أضاف المتجر منتجاً جديداً. اكتشفه الآن.', AppNotification::where('type', 'store_new_product')->first()->body);

        Story::create([
            'vendor_id' => $store->id,
            'image' => 'stories/offer.jpg',
            'caption' => 'عرض',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'store_new_offer',
            'body' => 'نشر المتجر عرضاً جديداً.',
        ]);
    }

    public function test_server_side_store_filters_cover_full_public_catalog_and_pagination(): void
    {
        $store = $this->makeStore();
        $rated = $this->makeProduct($store, 'منتج متوفر', 5000);
        $this->review($rated, 5);
        $this->makeProduct($store, 'منتج غير متوفر', 10000, 0);
        $this->makeProduct($this->makeStore('متجر آخر'), 'منتج خارجي', 8000);

        $this->getJson("/api/v1/stores/{$store->id}?price_min=20&rating_min=4&in_stock=1&per_page=1")
            ->assertOk()
            ->assertJsonPath('products.meta.total', 1)
            ->assertJsonPath('products.data.0.id', $rated->id);

        $this->getJson("/api/v1/stores/{$store->id}?price_min=50&price_max=10")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_min');

        $this->getJson("/store/{$store->id}/products?price_min=20&rating_min=4&in_stock=1")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rated->id);
    }

    public function test_buyer_seller_messages_are_private_and_notifications_hide_message_contents(): void
    {
        $store = $this->makeStore();
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);
        $start = $this->postJson("/api/v1/stores/{$store->id}/conversations")->assertCreated();
        $conversationId = $start->json('data.id');

        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'تفاصيل خاصة جداً'])
            ->assertCreated();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $store->user_id,
            'type' => 'new_message',
            'body' => 'افتح المحادثة للرد على الرسالة.',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $store->user_id,
            'body' => 'تفاصيل خاصة جداً',
        ]);

        $outsider = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/conversations/{$conversationId}")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'تطفل'])->assertForbidden();

        Sanctum::actingAs($store->user);
        $this->getJson("/api/v1/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('data.messages.0.body', 'تفاصيل خاصة جداً');
        $this->postJson("/api/v1/conversations/{$conversationId}/messages", ['body' => 'رد البائع'])
            ->assertCreated();
    }

    public function test_web_buyer_and_seller_conversation_pages_use_the_existing_private_thread(): void
    {
        $store = $this->makeStore();
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)->post("/store/{$store->id}/message");
        $conversation = Conversation::firstOrFail();
        $response->assertRedirect(route('customer.conversations.show', $conversation));
        $this->post(route('customer.conversations.send', $conversation), ['body' => 'هل المنتج متاح؟'])
            ->assertRedirect();

        $this->actingAs($store->user)
            ->post(route('seller.conversations.send', $conversation), ['body' => 'نعم، متاح.'])
            ->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type' => 'new_message',
            'body' => 'افتح المحادثة لقراءة الرد.',
        ]);
    }

    public function test_legacy_free_text_hours_are_never_reported_as_open(): void
    {
        $store = $this->makeStore();
        $store->update(['business_hours' => 'يومياً من الصباح حتى المساء']);

        $this->getJson("/api/v1/stores/{$store->id}")
            ->assertOk()
            ->assertJsonPath('store.business_hours_status.state', 'unknown')
            ->assertJsonPath('store.business_hours_status.label', 'أوقات العمل غير محددة');
    }

    public function test_business_hours_respect_multiple_periods_closed_days_timezone_and_legacy_text(): void
    {
        $service = new BusinessHoursStatus();
        $schedule = [
            'timezone' => 'Asia/Damascus',
            'days' => [
                'mon' => [
                    ['open' => '09:00', 'close' => '13:00'],
                    ['open' => '15:00', 'close' => '20:00'],
                ],
                'tue' => [],
            ],
        ];

        $open = $service->forSchedule($schedule, CarbonImmutable::parse('2026-11-02 12:00', 'Asia/Damascus'));
        $this->assertSame('open', $open['state']);
        $this->assertSame('مفتوح الآن', $open['label']);

        $betweenPeriods = $service->forSchedule($schedule, CarbonImmutable::parse('2026-11-02 13:00', 'Asia/Damascus'));
        $this->assertSame('closed', $betweenPeriods['state']);
        $this->assertSame('2026-11-02T15:00:00+03:00', $betweenPeriods['next_change_at']);

        $legacy = $service->forSchedule(null, CarbonImmutable::parse('2026-11-02 10:00', 'Asia/Damascus'));
        $this->assertSame('unknown', $legacy['state']);
        $this->assertNull($legacy['next_change_at']);
    }

    public function test_seller_can_save_a_machine_readable_business_schedule(): void
    {
        $store = $this->makeStore();
        Sanctum::actingAs($store->user);

        $this->putJson('/api/v1/seller/store', [
            'store_name' => $store->store_name,
            'business_hours_schedule' => [
                'timezone' => 'Asia/Damascus',
                'days' => [
                    'mon' => [
                        ['open' => '09:00', 'close' => '13:00'],
                        ['open' => '15:00', 'close' => '20:00'],
                    ],
                    'tue' => [],
                ],
            ],
        ])->assertOk();

        $this->assertSame(
            '15:00',
            $store->fresh()->business_hours_schedule['days']['mon'][1]['open']
        );
        $this->putJson('/api/v1/seller/store', [
            'store_name' => $store->store_name,
            'business_hours_schedule' => [
                'timezone' => 'Asia/Damascus',
                'days' => ['monday' => [['open' => '09:00', 'close' => '17:00']]],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('business_hours_schedule.days');
    }

    public function test_store_page_has_product_qa_entry_follow_message_and_filter_controls(): void
    {
        $store = $this->makeStore();
        $this->makeProduct($store, 'منتج', 15000);

        $this->get("/store/{$store->id}")
            ->assertOk()
            ->assertSee('سجّل الدخول للمتابعة')
            ->assertSee('راسل البائع')
            ->assertSee('filter-price-min')
            ->assertSee('الأسئلة والأجوبة');
    }
}
