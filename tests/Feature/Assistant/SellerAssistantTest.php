<?php

namespace Tests\Feature\Assistant;

use App\Assistant\Seller\DescriptionWriter;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerAssistantTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: SellerProfile} */
    private function seller(string $store = 'متجر النور'): array
    {
        $user = User::factory()->create(['role' => 'seller', 'first_name' => 'أحمد']);
        $profile = SellerProfile::create(['user_id' => $user->id, 'store_name' => $store, 'status' => 'approved']);

        return [$user, $profile];
    }

    private function product(SellerProfile $vendor, string $name, array $attrs = []): Product
    {
        return Product::create($attrs + [
            'vendor_id' => $vendor->id,
            'category_id' => Category::firstOrCreate(['slug' => 'clothes'], ['name' => 'ملابس رجالية'])->id,
            'name' => $name, 'slug' => Str::slug(Str::random(10)), 'sku' => 'SKU-'.Str::random(8),
            'description' => str_repeat('وصف جيد للمنتج ', 10),
            'price' => 10000, 'stock_quantity' => 20, 'low_stock_threshold' => 5,
            'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    /** طلب فيه سطر لكل [منتج، مبلغ] */
    private function order(array $lines, string $status = 'pending', $at = null): Order
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(8)), 'user_id' => $customer->id,
            'status' => $status, 'payment_status' => 'unpaid',
            'subtotal' => 0, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 0,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
        foreach ($lines as [$product, $amount]) {
            OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $product->vendor_id,
                'product_name' => $product->name, 'unit_price' => $amount, 'quantity' => 1, 'subtotal' => $amount,
            ]);
        }
        if ($at) {
            $order->forceFill(['created_at' => $at])->save();
        }

        return $order;
    }

    private function ask(User $user, string $message): array
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/chat/send', ['message' => $message])->assertOk()->json('reply');
    }

    public function test_sales_summary_counts_only_own_items_and_skips_cancelled_orders(): void
    {
        [$me, $mine] = $this->seller();
        [, $other] = $this->seller('متجر آخر');
        $shirt = $this->product($mine, 'قميص قطن');
        $foreign = $this->product($other, 'ساعة');

        $this->order([[$shirt, 150000], [$foreign, 900000]]);   // طلب مشترك بين متجرين
        $this->order([[$shirt, 50000]], 'delivered');
        $this->order([[$shirt, 777700]], 'cancelled');          // ملغي: لا يُحسب

        $reply = $this->ask($me, 'قديش مبيعاتي هالشهر');

        $this->assertSame('sales', $reply['meta']['intent']);
        $this->assertStringContainsString('2,000', $reply['content']);   // (150000 + 50000) / 100
        $this->assertStringContainsString('2 طلب', $reply['content']);
        $this->assertStringNotContainsString('9,000', $reply['content']);
        $this->assertStringNotContainsString('7,777', $reply['content']);
        $this->assertStringContainsString('قميص قطن', $reply['content']);  // الأكثر مبيعاً
    }

    public function test_sales_period_today_excludes_older_orders(): void
    {
        [$me, $mine] = $this->seller();
        $p = $this->product($mine, 'قميص');
        $this->order([[$p, 30000]]);
        $this->order([[$p, 990000]], 'delivered', now()->subDays(3));

        $reply = $this->ask($me, 'مبيعات اليوم');

        $this->assertStringContainsString('اليوم', $reply['content']);
        $this->assertStringContainsString('300', $reply['content']);
        $this->assertStringNotContainsString('9,900', $reply['content']);
    }

    public function test_pending_orders_lists_only_orders_waiting_for_this_seller(): void
    {
        [$me, $mine] = $this->seller();
        [, $other] = $this->seller('متجر آخر');
        $p = $this->product($mine, 'قميص');
        $waiting = $this->order([[$p, 10000]], 'pending');
        $shipped = $this->order([[$p, 10000]], 'shipped');
        $foreign = $this->order([[$this->product($other, 'ساعة'), 10000]], 'pending');

        $reply = $this->ask($me, 'شو الطلبات اللي بانتظاري');

        $this->assertSame('pending_orders', $reply['meta']['intent']);
        $this->assertStringContainsString($waiting->order_number, $reply['content']);
        $this->assertStringNotContainsString($shipped->order_number, $reply['content']);
        $this->assertStringNotContainsString($foreign->order_number, $reply['content']);
    }

    public function test_low_stock_lists_products_and_variants_at_or_below_threshold(): void
    {
        [$me, $mine] = $this->seller();
        $this->product($mine, 'قميص قارب ينفد', ['stock_quantity' => 3]);
        $this->product($mine, 'قميص متوفر', ['stock_quantity' => 50]);
        $withVariant = $this->product($mine, 'بنطال جينز', ['stock_quantity' => 40]);
        ProductVariant::create(['product_id' => $withVariant->id, 'color' => '#000', 'color_name' => 'أسود', 'size' => 'L', 'stock_quantity' => 1, 'is_active' => true]);

        $reply = $this->ask($me, 'شو المنتجات اللي قربت تخلص');

        $this->assertSame('low_stock', $reply['meta']['intent']);
        $this->assertStringContainsString('قميص قارب ينفد', $reply['content']);
        $this->assertStringContainsString('بنطال جينز', $reply['content']);
        $this->assertStringContainsString('أسود', $reply['content']);
        $this->assertStringNotContainsString('قميص متوفر', $reply['content']);
    }

    public function test_wallet_balance(): void
    {
        [$me, $mine] = $this->seller();
        $mine->walletOrCreate()->update(['balance' => 1234500]);

        $reply = $this->ask($me, 'كم رصيدي');

        $this->assertSame('wallet', $reply['meta']['intent']);
        $this->assertStringContainsString('12,345', $reply['content']);
    }

    public function test_tips_point_at_weak_products(): void
    {
        [$me, $mine] = $this->seller();
        $this->product($mine, 'منتج بلا صور ووصف', ['description' => 'قصير']);
        $good = $this->product($mine, 'منتج ممتاز');
        foreach (range(1, 3) as $i) {
            ProductImage::create(['product_id' => $good->id, 'path' => "p/{$i}.jpg", 'is_primary' => $i === 1]);
        }
        $this->product($mine, 'منتج نافد', ['stock_quantity' => 0]);

        $reply = $this->ask($me, 'عطيني نصائح لزيد مبيعاتي');

        $this->assertSame('tips', $reply['meta']['intent']);
        $this->assertStringContainsString('منتج بلا صور ووصف', $reply['content']);
        $this->assertStringContainsString('منتج نافد', $reply['content']);
        $this->assertStringNotContainsString('منتج ممتاز', $reply['content']);
    }

    public function test_chat_can_write_a_description_for_own_product_only(): void
    {
        [$me, $mine] = $this->seller();
        [, $other] = $this->seller('متجر آخر');
        $p = $this->product($mine, 'قميص كتان صيفي');
        ProductVariant::create(['product_id' => $p->id, 'color' => '#fff', 'color_name' => 'أبيض', 'size' => 'M', 'stock_quantity' => 3, 'is_active' => true]);
        $this->product($other, 'ساعة فاخرة');

        $reply = $this->ask($me, 'اكتبلي وصف لمنتج قميص كتان');
        $this->assertSame('describe', $reply['meta']['intent']);
        $this->assertStringContainsString('قميص كتان صيفي', $reply['content']);
        $this->assertStringContainsString('أبيض', $reply['content']);

        $reply = $this->ask($me, 'اكتبلي وصف لمنتج ساعة فاخرة');
        $this->assertStringNotContainsString('ساعة فاخرة —', $reply['content']);
        $this->assertStringContainsString('ما لقيت', $reply['content']);
    }

    public function test_customer_still_gets_the_customer_brain(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $reply = $this->postJson('/api/v1/chat/send', ['message' => 'مبيعاتي'])->assertOk()->json('reply');

        $this->assertNotSame('sales', $reply['meta']['intent'] ?? null);
    }

    public function test_seller_greeting_offers_seller_chips(): void
    {
        [$me] = $this->seller();

        $reply = $this->ask($me, 'مرحبا');

        $this->assertContains('مبيعاتي هالشهر', $reply['meta']['chips']);
    }

    // ------------------------------------------------------------ مولّد الوصف

    public function test_description_writer_uses_only_given_facts(): void
    {
        $text = (new DescriptionWriter)->write('قميص كتان صيفي', 'ملابس رجالية', ['أبيض', 'أزرق'], ['M', 'L'], 'متجر النور');

        $this->assertStringContainsString('قميص كتان صيفي', $text);
        $this->assertStringContainsString('أبيض، أزرق', $text);
        $this->assertStringContainsString('M، L', $text);
        $this->assertStringContainsString('متجر النور', $text);
        $this->assertStringNotContainsString('%', $text);
    }

    public function test_describe_endpoint_for_sellers_web_and_api(): void
    {
        [$me] = $this->seller();
        $payload = ['name' => 'حذاء رياضي خفيف', 'category' => 'أحذية', 'colors' => ['أسود'], 'sizes' => ['42', '43']];

        $this->actingAs($me)->postJson(route('seller.assistant.describe'), $payload)
            ->assertOk()->assertJsonPath('description', fn ($d) => str_contains($d, 'حذاء رياضي خفيف') && str_contains($d, '42، 43'));

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($me);
        $this->postJson('/api/v1/seller/assistant/describe', $payload)->assertOk()->assertJsonStructure(['description']);

        $this->postJson('/api/v1/seller/assistant/describe', ['name' => ''])->assertUnprocessable();
    }

    public function test_describe_endpoint_rejects_customers(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'customer']));

        $this->postJson('/api/v1/seller/assistant/describe', ['name' => 'x'])->assertForbidden();
    }

    public function test_product_form_shows_the_describe_button(): void
    {
        [$me] = $this->seller();
        Category::firstOrCreate(['slug' => 'clothes'], ['name' => 'ملابس رجالية']);

        $this->actingAs($me)->get(route('seller.products.create'))
            ->assertOk()
            ->assertSee('id="ts-describe-btn"', false)
            ->assertSee(route('seller.assistant.describe'), false);
    }

    // ------------------------------------------------------------ إصلاحات المراجعة

    public function test_seller_policy_questions_fall_back_to_admin_faq(): void
    {
        $this->seed(\Database\Seeders\AssistantFaqSeeder::class);
        [$me] = $this->seller();

        $reply = $this->ask($me, 'قديش رسوم الشحن');

        $this->assertStringContainsString('150', $reply['content']);
    }

    public function test_wallet_shows_withdrawable_amount_after_pending_withdrawals(): void
    {
        [$me, $mine] = $this->seller();
        $mine->walletOrCreate()->update(['balance' => 5000000]);
        \App\Models\WithdrawalRequest::create(['vendor_id' => $mine->id, 'amount' => 2000000, 'status' => 'pending']);

        $reply = $this->ask($me, 'كم رصيدي');

        $this->assertStringContainsString('30,000', $reply['content']);   // المتاح للسحب
        $this->assertStringContainsString('20,000', $reply['content']);   // طلبات سحب معلّقة
    }

    public function test_pending_orders_reports_the_real_total_when_listing_is_capped(): void
    {
        [$me, $mine] = $this->seller();
        $p = $this->product($mine, 'قميص');
        foreach (range(1, 11) as $i) {
            $this->order([[$p, 10000]], 'pending');
        }

        $reply = $this->ask($me, 'الطلبات اللي بانتظاري');

        $this->assertStringContainsString('عندك 11 طلبات', $reply['content']);
    }
}
