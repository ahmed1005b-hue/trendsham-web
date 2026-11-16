<?php

namespace Tests\Feature\Assistant;

use App\Assistant\AssistantBrain;
use App\Assistant\Brains\RuleBasedBrain;
use App\Assistant\Models\AssistantFaq;
use App\Coupons\Models\CouponCampaign;
use App\Coupons\Models\CustomerCoupon;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantChatTest extends TestCase
{
    use RefreshDatabase;

    private ?SellerProfile $vendor = null;

    private function product(string $name, int $priceLira, array $attrs = []): Product
    {
        if (! $this->vendor) {
            $seller = User::factory()->create(['role' => 'seller']);
            $this->vendor = SellerProfile::create(['user_id' => $seller->id, 'store_name' => 'متجر', 'status' => 'approved']);
        }

        return Product::create($attrs + [
            'vendor_id' => $this->vendor->id,
            'category_id' => Category::firstOrCreate(['slug' => 'c1'], ['name' => 'إلكترونيات'])->id,
            'name' => $name, 'slug' => Str::slug(Str::random(8)), 'sku' => 'SKU-'.Str::random(6),
            'price' => $priceLira * 100, 'stock_quantity' => 5, 'status' => 'active', 'admin_approved_at' => now(),
        ]);
    }

    private function guestSend(string $message, string $guest = 'guest-1'): array
    {
        return $this->withHeader('X-Guest-Id', $guest)->postJson('/api/v1/chat/send', ['message' => $message])
            ->assertOk()->json();
    }

    private function customer(): User
    {
        $u = User::factory()->create(['role' => 'customer', 'first_name' => 'سامر']);
        Sanctum::actingAs($u);

        return $u;
    }

    private function send(string $message): array
    {
        return $this->postJson('/api/v1/chat/send', ['message' => $message])->assertOk()->json();
    }

    private function order(User $user, string $status = 'pending', ?Product $product = null): Order
    {
        $order = Order::create([
            'order_number' => 'TS-'.strtoupper(Str::random(8)), 'user_id' => $user->id,
            'status' => $status, 'payment_status' => 'unpaid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => 'a', 'shipping_phone' => '0999999999', 'shipping_address' => 'b',
        ]);
        if ($product) {
            OrderItem::create([
                'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $product->vendor_id,
                'product_name' => $product->name, 'unit_price' => 10000, 'quantity' => 1, 'subtotal' => 10000,
            ]);
        }

        return $order;
    }

    // ------------------------------------------------------------ البحث

    public function test_search_understands_synonyms_and_price_cap_and_returns_cards(): void
    {
        $this->product('موبايل سامسونج A15', 150000);
        $this->product('موبايل ايفون 15', 900000);
        $this->product('سماعة بلوتوث', 20000);

        $res = $this->guestSend('بدي جوال تحت 200 ألف');

        $cards = $res['reply']['meta']['cards'];
        $this->assertCount(1, $cards);
        $this->assertSame('product', $cards[0]['type']);
        $this->assertSame('موبايل سامسونج A15', $cards[0]['name']);
        $this->assertSame('150,000 ل.س', $cards[0]['price_label']);
        $this->assertNotEmpty($cards[0]['url']);
        $this->assertSame('product_search', $res['reply']['meta']['intent']);
    }

    public function test_search_filters_by_variant_color_and_hides_unpublished(): void
    {
        $red = $this->product('قميص قطن', 30000);
        ProductVariant::create(['product_id' => $red->id, 'color' => '#f00', 'color_name' => 'أحمر', 'size' => 'XL', 'stock_quantity' => 3, 'is_active' => true]);
        $blue = $this->product('قميص كتان', 30000);
        ProductVariant::create(['product_id' => $blue->id, 'color' => '#00f', 'color_name' => 'أزرق', 'size' => 'XL', 'stock_quantity' => 3, 'is_active' => true]);
        $this->product('قميص مخفي', 30000, ['admin_approved_at' => null]);

        $names = collect($this->guestSend('ابحث عن قميص احمر')['reply']['meta']['cards'])->pluck('name');

        $this->assertSame(['قميص قطن'], $names->all());
    }

    public function test_search_with_no_match_says_so_without_dumping_catalogue(): void
    {
        $this->product('موبايل سامسونج', 150000);

        $res = $this->guestSend('ابحث عن طيارة');

        $this->assertSame([], $res['reply']['meta']['cards'] ?? []);
        $this->assertStringContainsString('ما لقيت', $res['reply']['content']);
    }

    public function test_deals_list_discounted_products(): void
    {
        $this->product('ساعة ذكية', 50000, ['compare_at_price' => 80000 * 100]);
        $this->product('ساعة عادية', 50000);

        $names = collect($this->guestSend('شو في عروض اليوم')['reply']['meta']['cards'])->pluck('name');

        $this->assertSame(['ساعة ذكية'], $names->all());
    }

    public function test_compare_two_products(): void
    {
        $this->product('موبايل سامسونج A15', 150000);
        $this->product('موبايل ايفون 15', 900000);

        $res = $this->guestSend('قارن بين سامسونج و ايفون');

        $this->assertCount(2, $res['reply']['meta']['cards']);
        $this->assertStringContainsString('150,000', $res['reply']['content']);
        $this->assertStringContainsString('900,000', $res['reply']['content']);
    }

    // ------------------------------------------------------------ الطلبات

    public function test_guest_asking_about_orders_gets_login_prompt_and_no_data(): void
    {
        $other = User::factory()->create(['role' => 'customer']);
        $order = $this->order($other);

        $res = $this->guestSend("وين طلبي {$order->order_number}");

        $this->assertStringContainsString('سجّل دخول', $res['reply']['content']);
        $this->assertStringNotContainsString(Order::STATUS_LABELS['pending'], $res['reply']['content']);
    }

    public function test_customer_sees_own_order_status_but_not_someone_elses(): void
    {
        $other = User::factory()->create(['role' => 'customer']);
        $foreign = $this->order($other, 'shipped');
        $me = $this->customer();
        $mine = $this->order($me, 'shipped');

        $res = $this->send("حالة الطلب {$mine->order_number}");
        $this->assertStringContainsString('تم الشحن', $res['reply']['content']);

        $res = $this->send("حالة الطلب {$foreign->order_number}");
        $this->assertStringContainsString('ما لقيت', $res['reply']['content']);
    }

    public function test_cancel_flow_asks_confirmation_then_cancels_and_restocks(): void
    {
        $product = $this->product('ساعة', 100);
        $me = $this->customer();
        $order = $this->order($me, 'pending', $product);

        $res = $this->send('بدي الغي طلبي');
        $this->assertSame('pending', $order->refresh()->status);   // لا شيء قبل التأكيد
        $this->assertStringContainsString($order->order_number, $res['reply']['content']);
        $this->assertTrue($res['reply']['meta']['awaiting_confirmation']);
        $this->assertArrayNotHasKey('pending', $res['reply']['meta']);   // تفاصيل الإجراء لا تخرج للعميل

        $this->send('نعم');

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(6, $product->refresh()->stock_quantity);
    }

    public function test_confirmation_is_only_valid_right_after_the_question(): void
    {
        $me = $this->customer();
        $order = $this->order($me, 'pending');

        $this->send('بدي الغي طلبي');
        $this->send('مرحبا');
        $this->send('نعم');

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_yes_without_any_question_cancels_nothing(): void
    {
        $me = $this->customer();
        $order = $this->order($me, 'pending');

        $this->send('نعم');

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_cannot_cancel_order_that_is_already_processing(): void
    {
        $me = $this->customer();
        $order = $this->order($me, 'processing');

        $res = $this->send("بدي الغي الطلب {$order->order_number}");

        $this->assertArrayNotHasKey('awaiting_confirmation', $res['reply']['meta']);
        $this->assertSame('processing', $order->refresh()->status);
    }

    // ------------------------------------------------------------ الكوبونات

    public function test_customer_coupons_are_listed_and_guest_is_asked_to_login(): void
    {
        $this->assertStringContainsString('سجّل دخول', $this->guestSend('كوبوناتي')['reply']['content']);

        $me = $this->customer();
        $campaign = CouponCampaign::create([
            'funder' => 'platform', 'title' => 'خصم الصيف', 'type' => 'percentage', 'value' => 10,
            'scope' => 'all', 'audience' => 'specific', 'starts_at' => now()->subDay(),
            'per_customer_limit' => 1, 'status' => 'active',
        ]);
        CustomerCoupon::create(['campaign_id' => $campaign->id, 'user_id' => $me->id, 'status' => 'available', 'source' => 'test']);

        $res = $this->send('شو عندي كوبونات');

        $this->assertStringContainsString('خصم الصيف', $res['reply']['content']);
    }

    // ------------------------------------------------------------ الأسئلة الشائعة والتحويل

    public function test_faq_answer_written_by_admin_is_used(): void
    {
        AssistantFaq::create(['question' => 'كم مدة التوصيل؟', 'keywords' => 'توصيل, مدة التوصيل, بيوصل', 'answer' => 'التوصيل خلال 3 أيام عمل.', 'is_active' => true]);
        AssistantFaq::create(['question' => 'مخفي', 'keywords' => 'توصيل', 'answer' => 'جواب معطل', 'is_active' => false]);

        $res = $this->guestSend('كم يوم بياخد التوصيل');

        $this->assertSame('التوصيل خلال 3 أيام عمل.', $res['reply']['content']);
    }

    public function test_seeded_faqs_answer_common_questions_even_when_an_order_is_mentioned(): void
    {
        $this->seed(\Database\Seeders\AssistantFaqSeeder::class);

        $this->assertStringContainsString('3 أيام', $this->guestSend('بدي رجّع طلبي وصل مكسور')['reply']['content']);
        $this->assertStringContainsString('150', $this->guestSend('قديش رسوم الشحن')['reply']['content']);
        $this->assertStringContainsString('عند الاستلام', $this->guestSend('شو طرق الدفع')['reply']['content']);
        $this->assertStringContainsString('نسيت كلمة السر', $this->guestSend('نسيت كلمة السر')['reply']['content']);
        // رقم طلب صريح = سؤال عن الطلب نفسه، لا عن السياسة
        $this->assertSame('order_status', $this->guestSend('وين طلبي TS-ABCD1234')['reply']['meta']['intent']);

        $this->seed(\Database\Seeders\AssistantFaqSeeder::class);   // التشغيل مرتين لا يكرر الأسئلة
        $this->assertSame(AssistantFaq::count(), count(\Database\Seeders\AssistantFaqSeeder::FAQS));
    }

    public function test_policy_question_without_faq_offers_handoff_instead_of_guessing(): void
    {
        $res = $this->guestSend('شو سياسة الارجاع عندكن');

        $this->assertContains('تحدث مع موظف', $res['reply']['meta']['chips']);
        $this->assertSame('open', ChatConversation::first()->status);
    }

    public function test_two_unknown_messages_offer_handoff(): void
    {
        $first = $this->guestSend('zzqx');
        $this->assertNotContains('تحدث مع موظف', $first['reply']['meta']['chips'] ?? []);

        $second = $this->guestSend('qqzz');
        $this->assertContains('تحدث مع موظف', $second['reply']['meta']['chips']);
    }

    public function test_handoff_escalates_and_bot_goes_quiet(): void
    {
        $res = $this->guestSend('بدي احكي مع موظف');

        $conversation = ChatConversation::first();
        $this->assertSame('escalated', $conversation->status);
        $this->assertNotNull($conversation->escalated_at);
        $this->assertTrue($res['reply']['meta']['handoff']);

        // بعد التحويل: لا ردود آلية، فقط تأكيد واحد
        $ack = $this->guestSend('مرحبا؟');
        $this->assertNotNull($ack['reply']);
        $this->assertNull($this->guestSend('في حدا؟')['reply']);
        $this->assertSame(5, $conversation->messages()->count());
    }

    public function test_history_exposes_meta_and_status(): void
    {
        $this->product('موبايل سامسونج', 150000);
        $this->guestSend('بدي موبايل');

        $res = $this->withHeader('X-Guest-Id', 'guest-1')->getJson('/api/v1/chat')->assertOk()->json();

        $this->assertSame('open', $res['status']);
        $this->assertNotEmpty($res['messages'][1]['meta']['cards']);
        $this->assertArrayNotHasKey('pending', $res['messages'][1]['meta']);
    }

    public function test_greeting_has_suggestion_chips(): void
    {
        $res = $this->guestSend('مرحبا');

        $this->assertContains('تتبع طلبي', $res['reply']['meta']['chips']);
    }

    // ------------------------------------------------------------ العقل القابل للتبديل

    public function test_brain_comes_from_config_and_unknown_driver_falls_back_to_rules(): void
    {
        $this->assertInstanceOf(RuleBasedBrain::class, app(AssistantBrain::class));

        config(['assistant.driver' => 'does-not-exist']);
        $this->app->forgetInstance(AssistantBrain::class);
        $this->assertInstanceOf(RuleBasedBrain::class, app(AssistantBrain::class));
    }

    // ------------------------------------------------------------ إصلاحات المراجعة

    public function test_logged_in_web_customer_is_recognised_by_the_web_chat_routes(): void
    {
        $me = User::factory()->create(['role' => 'customer']);
        $order = $this->order($me, 'shipped');
        $this->actingAs($me);   // جلسة الموقع، لا توكن

        $this->postJson(route('assistant.chat.send'), ['message' => 'وين طلبي'])
            ->assertOk()
            ->assertJsonPath('reply.meta.intent', 'order_status')
            ->assertSee($order->order_number);
    }

    public function test_policy_wording_beats_compare_deals_and_order_intents(): void
    {
        $this->seed(\Database\Seeders\AssistantFaqSeeder::class);

        $this->assertStringContainsString('عند الاستلام', $this->guestSend('كيف بدفع ثمن الطلب')['reply']['content']);
        $this->assertContains('تحدث مع موظف', $this->guestSend('هل في عروض على الشحن')['reply']['meta']['chips']);
        $this->assertSame('faq', $this->guestSend('شو الفرق بين الشحن العادي والسريع')['reply']['meta']['intent']);
        $this->assertStringContainsString('3 أيام', $this->guestSend('بدي ارجع المنتج')['reply']['content']);
    }

    public function test_send_returns_the_stored_user_message_with_its_id(): void
    {
        $res = $this->guestSend('مرحبا');

        $this->assertSame('مرحبا', $res['message']['content']);
        $this->assertGreaterThan(0, $res['message']['id']);
        $this->assertGreaterThan($res['message']['id'], $res['reply']['id']);
    }

    public function test_history_get_does_not_create_conversations(): void
    {
        $this->withHeader('X-Guest-Id', 'nobody')->getJson('/api/v1/chat')
            ->assertOk()->assertJsonPath('messages', [])->assertJsonPath('status', 'open');

        $this->assertDatabaseCount('chat_conversations', 0);
    }

    public function test_recently_closed_conversation_still_shows_the_agent_reply(): void
    {
        $c = ChatConversation::create(['guest_session_id' => 'guest-1', 'status' => 'closed', 'escalated_at' => now()->subMinute()]);
        $c->messages()->create(['role' => 'assistant', 'content' => 'تم حل مشكلتك', 'tool_calls' => ['intent' => 'agent', 'agent' => true]]);

        $res = $this->withHeader('X-Guest-Id', 'guest-1')->getJson('/api/v1/chat')->assertOk()->json();

        $this->assertSame('closed', $res['status']);
        $this->assertSame('تم حل مشكلتك', end($res['messages'])['content']);
    }

    public function test_customer_can_return_to_the_bot_and_unanswered_handoff_expires(): void
    {
        $this->guestSend('بدي احكي مع موظف');
        $back = $this->guestSend('رجوع للمساعد');
        $this->assertSame('open', ChatConversation::first()->status);
        $this->assertNotNull($back['reply']);

        $this->guestSend('بدي احكي مع موظف');
        ChatConversation::first()->update(['escalated_at' => now()->subDay()->subMinute()]);
        $res = $this->guestSend('مرحبا');
        $this->assertSame('greeting', $res['reply']['meta']['intent']);
    }

    public function test_cancel_confirmation_expires(): void
    {
        $me = $this->customer();
        $order = $this->order($me, 'pending');

        $this->send('بدي الغي طلبي');
        $this->travel(11)->minutes();
        $this->send('نعم');

        $this->assertSame('pending', $order->refresh()->status);
    }

    public function test_messages_keep_insertion_order_within_the_same_second(): void
    {
        $c = ChatConversation::create(['guest_session_id' => 'g', 'status' => 'open']);
        $at = now();
        $second = $c->messages()->create(['role' => 'assistant', 'content' => 'ب']);
        $first = $c->messages()->create(['role' => 'user', 'content' => 'أ']);
        $second->forceFill(['created_at' => $at])->save();
        $first->forceFill(['created_at' => $at])->save();

        $this->assertSame(['ب', 'أ'], $c->messages()->pluck('content')->all());
    }
}
