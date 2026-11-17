<?php

namespace App\Providers;

use App\Assistant\AssistantBrain;
use App\Assistant\Brains\RuleBasedBrain;
use App\Coupons\Models\CouponCampaign;
use App\Models\AppNotification;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\CustomerCard;
use App\Models\DailyDeal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\Showcase;
use App\Models\StaffConversation;
use App\Models\Story;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\WithdrawalRequest;
use App\Observers\ProductObserver;
use App\Observers\StoryObserver;
use App\Policies\AppNotificationPolicy;
use App\Policies\CartItemPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\CouponCampaignPolicy;
use App\Policies\CustomerCardPolicy;
use App\Policies\DailyDealPolicy;
use App\Policies\OrderItemPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductQuestionPolicy;
use App\Policies\ReturnRequestPolicy;
use App\Policies\ShowcasePolicy;
use App\Policies\StaffConversationPolicy;
use App\Policies\StoryPolicy;
use App\Policies\UserAddressPolicy;
use App\Policies\WithdrawalRequestPolicy;
use App\Referrals\ReferralCookie;
use App\Returns\Models\ReturnRequest;
use App\Showcases\ShowcaseWallet;
use App\Showcases\WalletBridge;
use App\Support\RateLimits;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShowcaseWallet::class, WalletBridge::class);

        // عقل المساعد من الإعدادات؛ أي سائق غير معروف يرجع للمجاني
        $this->app->bind(AssistantBrain::class, function ($app) {
            $class = config('assistant.drivers', [])[config('assistant.driver')] ?? null;
            $brain = $class && class_exists($class) ? $app->make($class) : null;

            return $brain instanceof AssistantBrain ? $brain : $app->make(RuleBasedBrain::class);
        });
    }

    public function boot(): void
    {
        RateLimits::register();

        // Security/launch: force HTTPS for generated URLs in production or when the app URL is HTTPS.
        // This protects payment redirects/return/webhook endpoint URLs behind TLS.
        if (app()->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Product::observe(ProductObserver::class);
        Story::observe(StoryObserver::class);
        // كود «دعوة صديق» المحفوظ من رابط الدعوة يُطبَّق عند إنشاء الحساب
        User::created(fn (User $user) => ReferralCookie::applyTo($user));

        // سياسات الموارد المملوكة للزبون (تسجيل صريح)
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(OrderItem::class, OrderItemPolicy::class);
        Gate::policy(UserAddress::class, UserAddressPolicy::class);
        Gate::policy(CustomerCard::class, CustomerCardPolicy::class);
        Gate::policy(CartItem::class, CartItemPolicy::class);
        Gate::policy(AppNotification::class, AppNotificationPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(ProductQuestion::class, ProductQuestionPolicy::class);

        // سياسات الموارد المملوكة للبائع (تسجيل صريح)
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(DailyDeal::class, DailyDealPolicy::class);
        Gate::policy(Story::class, StoryPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentMethodPolicy::class);
        Gate::policy(Showcase::class, ShowcasePolicy::class);
        Gate::policy(StaffConversation::class, StaffConversationPolicy::class);
        Gate::policy(CouponCampaign::class, CouponCampaignPolicy::class);
        Gate::policy(WithdrawalRequest::class, WithdrawalRequestPolicy::class);
        Gate::policy(ReturnRequest::class, ReturnRequestPolicy::class);
    }
}
