<?php

namespace Tests\Concerns;

use App\Coupons\Models\CouponCampaign;
use App\GiftCards\Models\GiftCard;
use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\CustomerCard;
use App\Models\DailyDeal;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductQuestion;
use App\Models\SellerProfile;
use App\Models\Showcase;
use App\Models\ShowcaseSlot;
use App\Models\StaffConversation;
use App\Models\StaffMessage;
use App\Models\Story;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\WithdrawalRequest;
use App\Returns\Models\ReturnRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * يبني «مستأجراً» كاملاً (زبون + بائع + متجر وكل ما يملكه) لاختبارات
 * الوصول الأفقي: مستأجران منفصلان A وB، ويجب ألا يلمس أحدهما موارد الآخر.
 */
trait BuildsTenants
{
    /** @return array<string, Model> */
    protected function tenant(string $tag): array
    {
        // رقم هاتف فريد لكل مستخدم حتى لا يتصادم مستأجران.
        static $phoneSeq = 0;
        $phone = function () use (&$phoneSeq) {
            return '09'.str_pad((string) (++$phoneSeq + 50000000), 8, '0', STR_PAD_LEFT);
        };

        $customer = User::create([
            'first_name' => "Customer $tag", 'last_name' => 'Tenant', 'phone' => $phone(),
            'email' => "customer-$tag@tenant.test", 'password' => 'password', 'role' => 'customer', 'status' => 'active',
        ]);
        $seller = User::create([
            'first_name' => "Seller $tag", 'last_name' => 'Tenant', 'phone' => $phone(),
            'email' => "seller-$tag@tenant.test", 'password' => 'password', 'role' => 'seller', 'status' => 'active',
        ]);

        $store = SellerProfile::create([
            'user_id' => $seller->id, 'store_name' => "Store $tag", 'status' => 'approved',
            'identity_verified_at' => now(),
        ]);

        $category = Category::firstOrCreate(['slug' => 'tenant-shared'], ['name' => 'Tenant shared', 'is_active' => true]);

        $product = Product::create([
            'vendor_id' => $store->id, 'category_id' => $category->id, 'name' => "Product $tag",
            'slug' => "product-$tag-".Str::random(6), 'description' => "Product $tag description",
            'sku' => "SKU-$tag-".Str::random(6), 'price' => 10000, 'stock_quantity' => 10,
            'status' => 'active', 'admin_approved_at' => now(),
        ]);

        $image = ProductImage::create([
            'product_id' => $product->id, 'path' => "products/tenant-$tag.jpg", 'is_primary' => true, 'sort_order' => 0,
        ]);

        $cartItem = CartItem::create([
            'user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1,
            'unit_price' => $product->price, 'original_unit_price' => $product->price,
        ]);

        $order = Order::create([
            'order_number' => 'TS-'.strtoupper($tag).'-'.Str::upper(Str::random(8)),
            'user_id' => $customer->id, 'status' => 'delivered', 'payment_status' => 'paid',
            'subtotal' => 10000, 'shipping_fee' => 0, 'discount_amount' => 0, 'total' => 10000,
            'shipping_name' => "Customer $tag", 'shipping_phone' => $customer->phone, 'shipping_address' => "Address $tag",
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'vendor_id' => $store->id,
            'product_name' => $product->name, 'unit_price' => 10000, 'quantity' => 1, 'subtotal' => 10000,
        ]);

        // البند مُسوّى ضمن مهلة الإرجاع ليكون قابلاً للإرجاع في مصفوفات الوصول.
        $orderItem->update(['commission_settled_at' => now()]);
        $returnRequest = ReturnRequest::create([
            'number' => 'RT-'.strtoupper($tag).'-'.Str::upper(Str::random(8)),
            'order_id' => $order->id, 'user_id' => $customer->id, 'seller_profile_id' => $store->id,
            'status' => 'requested', 'reason' => 'changed_mind', 'description' => "Return $tag",
            'refund_amount' => 10000, 'shipping_refund' => 0, 'respond_by' => now()->addDays(3),
        ]);
        $returnRequest->items()->create([
            'order_item_id' => $orderItem->id, 'quantity' => 1, 'refund_amount' => 10000,
            'seller_reversal' => 0, 'commission_reversal' => 0,
        ]);

        $address = new UserAddress(['label' => 'home', 'city' => "City $tag", 'details' => "Details $tag", 'is_default' => true]);
        $address->user_id = $customer->id;
        $address->save();

        $card = CustomerCard::create([
            'user_id' => $customer->id, 'gateway' => 'test', 'reference' => (string) Str::uuid(),
            'token' => "tok-$tag-".Str::random(8), 'brand' => 'visa', 'last4' => '4242',
            'exp_month' => 12, 'exp_year' => (int) date('Y') + 3, 'holder_name' => "Customer $tag",
            'status' => 'active', 'is_default' => true,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id, 'method' => 'card', 'status' => 'paid', 'amount' => 10000,
            'reference' => (string) Str::uuid(), 'paid_at' => now(),
        ]);

        $notification = AppNotification::create([
            'user_id' => $customer->id, 'type' => 'order_update', 'group' => 'orders',
            'title' => "Notification $tag", 'body' => "Body $tag",
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id, 'vendor_id' => $store->id, 'last_message_at' => now(),
        ]);
        Message::create(['conversation_id' => $conversation->id, 'sender_id' => $customer->id, 'body' => "Hello $tag"]);

        $question = ProductQuestion::create([
            'product_id' => $product->id, 'user_id' => $customer->id, 'question' => "Question $tag?",
        ]);

        $staff = User::create([
            'first_name' => "Staff $tag", 'last_name' => 'Tenant', 'phone' => $phone(),
            'email' => "staff-$tag@tenant.test", 'password' => 'password', 'role' => 'employee', 'status' => 'active',
        ]);
        $supportConversation = StaffConversation::create([
            'vendor_id' => $store->id, 'staff_id' => $staff->id, 'subject' => "Support $tag", 'status' => 'open', 'last_message_at' => now(),
        ]);
        StaffMessage::create(['conversation_id' => $supportConversation->id, 'sender_id' => $seller->id, 'body' => "Help $tag"]);

        $deal = DailyDeal::create([
            'product_id' => $product->id, 'discount_percentage' => 10, 'special_price' => 9000,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'max_quantity' => 5, 'is_active' => true,
        ]);

        $story = Story::create([
            'vendor_id' => $store->id, 'image' => "stories/tenant-$tag.jpg", 'caption' => "Story $tag",
            'is_active' => true, 'expires_at' => now()->addDay(),
        ]);

        $paymentMethod = PaymentMethod::create([
            'vendor_id' => $store->id, 'type' => 'electronic_wallet', 'provider' => 'syriatel_cash',
            'account_number' => '0999'.random_int(100000, 999999), 'account_holder_name' => "Seller $tag", 'is_default' => true,
        ]);

        $slot = ShowcaseSlot::firstOrCreate(['key' => 'home_top'], ['name' => 'Home top', 'price_per_day' => 1000]);
        $showcase = Showcase::create([
            'slot_id' => $slot->id, 'seller_id' => $store->id, 'title' => "Showcase $tag",
            'gradient_colors' => ['#0F3D2E', '#D4A537'], 'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(), 'amount' => 3000, 'status' => 'pending_review',
        ]);

        $campaign = CouponCampaign::create([
            'funder' => 'seller', 'seller_profile_id' => $store->id, 'title' => "Campaign $tag",
            'type' => 'percentage', 'value' => 10, 'scope' => 'all', 'audience' => 'all_customers',
            'status' => 'active', 'created_by' => $seller->id,
        ]);

        $withdrawal = WithdrawalRequest::create([
            'vendor_id' => $store->id, 'amount' => 5000, 'status' => 'pending', 'note' => "Withdrawal $tag",
        ]);

        // بطاقة هدية اشتراها الزبون (مدفوعة، لم تُستخدم)
        $giftCard = GiftCard::create([
            'purchaser_id' => $customer->id, 'amount' => 5000000, 'status' => GiftCard::ACTIVE,
            'reference' => 'gift-'.$tag.'-'.Str::random(8), 'gateway' => 'sandbox',
            'code' => 'TSGIFT'.strtoupper($tag).Str::upper(Str::random(6)), 'code_hash' => hash('sha256', Str::random(20)),
            'paid_at' => now(), 'expires_at' => now()->addYear(),
        ]);

        return compact(
            'customer', 'seller', 'store', 'product', 'image', 'cartItem', 'order', 'orderItem', 'address', 'card',
            'payment', 'notification', 'conversation', 'question', 'supportConversation', 'deal', 'story',
            'paymentMethod', 'showcase', 'campaign', 'withdrawal', 'returnRequest', 'giftCard',
        );
    }

    /** يؤكد أن الطلب رُفض (403 أو 404) ويذكر الحالة والسيناريو عند الفشل. */
    protected function assertDenied(TestResponse $response, string $case): void
    {
        $status = $response->getStatusCode();
        $this->assertContains($status, [403, 404], "$case: expected 403/404 but got $status");
    }

    /** رفض الويب: 403/404، أو تحويل مقبول فقط إن لم يتسرب شيء من B ولم يشر إلى صفحة موردها. */
    protected function assertWebDenied(TestResponse $response, string $case, array $values, string $uri): void
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getContent();
        foreach ($values as $value) {
            $this->assertStringNotContainsString($value, $body, "$case: response leaks B value '$value'");
        }
        if (in_array($status, [403, 404], true)) {
            return;
        }
        $this->assertTrue($status >= 300 && $status < 400, "$case: expected 403/404/3xx but got $status");
        $location = (string) $response->headers->get('Location');
        $this->assertStringNotContainsString($uri, $location, "$case: redirects to B resource page");
    }

    /** يحوّل قالب المسار `{key}` أو `{key.attribute}` إلى مسار فعلي من موارد المستأجر $tenant. */
    protected function resolveUri(string $template, array $tenant): string
    {
        return '/'.preg_replace_callback('/\{(\w+)(?:\.(\w+))?\}/', function ($m) use ($tenant) {
            $target = $tenant[$m[1]];

            return isset($m[2]) ? (string) $target->{$m[2]} : (string) $target->getRouteKey();
        }, $template);
    }

    /** خصائص الصف بدون updated_at وبتواريخ نصية، للمقارنة قبل/بعد. */
    protected function stableAttributes(array $attributes): array
    {
        unset($attributes['updated_at']);

        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('c') : $v, $attributes);
    }
}
