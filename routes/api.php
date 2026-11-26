<?php

use App\Http\Controllers\Api\AccountClosureController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Admin\AccountingController as AdminAccountingController;
use App\Http\Controllers\Api\Admin\AssistantController as AdminAssistantController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\CouponCampaignController;
use App\Http\Controllers\Api\Admin\DailyDealController as AdminDailyDealController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\GiftCardController as AdminGiftCardController;
use App\Http\Controllers\Api\Admin\LoyaltyController as AdminLoyaltyController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\PushBroadcastController as AdminPushBroadcastController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Api\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Api\Admin\SellerCouponController as AdminSellerCouponController;
use App\Http\Controllers\Api\Admin\SellerDebtController as AdminSellerDebtController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\ShippingController as AdminShippingController;
use App\Http\Controllers\Api\Admin\ShowcaseController as AdminShowcaseController;
use App\Http\Controllers\Api\Admin\StatementController as AdminStatementController;
use App\Http\Controllers\Api\Admin\StoryController as AdminStoryController;
use App\Http\Controllers\Api\Admin\SupportController as ApiAdminSupportController;
use App\Http\Controllers\Api\Admin\TrashController as AdminTrashController;
use App\Http\Controllers\Api\Admin\TwoFactorController as AdminTwoFactorController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerSellerConversationController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CouponCollectController;
use App\Http\Controllers\Api\CouponWalletController;
use App\Http\Controllers\Api\DailyDealController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\GiftCardController;
use App\Http\Controllers\Api\HomeShowcaseController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\LoyaltyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductQuestionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicStoreController;
use App\Http\Controllers\Api\PushDeviceController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\Seller\ApplicationController as SellerApplicationController;
use App\Http\Controllers\Api\Seller\CouponController as SellerCouponApiController;
use App\Http\Controllers\Api\Seller\DailyDealController as SellerDailyDealController;
use App\Http\Controllers\Api\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Api\Seller\IdentityDocumentController;
use App\Http\Controllers\Api\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Api\Seller\PaymentMethodController;
use App\Http\Controllers\Api\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Api\Seller\ProductOptionsController;
use App\Http\Controllers\Api\Seller\ProfileController as SellerProfileController;
use App\Http\Controllers\Api\Seller\QuestionController;
use App\Http\Controllers\Api\Seller\ReturnController as SellerReturnController;
use App\Http\Controllers\Api\Seller\ShippingSettingsController as SellerShippingSettingsController;
use App\Http\Controllers\Api\Seller\ShowcaseController as SellerShowcaseController;
use App\Http\Controllers\Api\Seller\StoryController as SellerStoryController;
use App\Http\Controllers\Api\Seller\SupportController;
use App\Http\Controllers\Api\Seller\WalletController as SellerWalletController;
use App\Http\Controllers\Api\SellerReviewController;
use App\Http\Controllers\Api\ShippingCenterController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\SocialLinkController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreFollowController;
use App\Http\Controllers\Api\StoriesController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\ChatAssistantController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReverseGeocodeController;
use App\Http\Controllers\Seller\AssistantController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ===== عامة - بدون تسجيل دخول =====
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:otp');
    Route::post('/auth/register/verify', [AuthController::class, 'verifyRegistration'])->middleware('throttle:verify');
    Route::get('/auth/social/providers', [SocialAuthController::class, 'providers']);
    Route::post('/auth/social/{provider}', [SocialAuthController::class, 'login'])
        ->whereIn('provider', ['google', 'facebook'])->middleware('throttle:login');
    Route::post('/auth/two-factor', [ApiTwoFactorController::class, 'store'])->middleware('throttle:verify');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::post('/auth/forgot-password', [AuthController::class, 'requestReset'])->middleware('throttle:otp');
    Route::post('/auth/password/forgot', [AuthController::class, 'requestReset'])->middleware('throttle:otp');
    Route::post('/auth/password/request', [AuthController::class, 'requestReset'])->middleware('throttle:otp');
    Route::post('/auth/request-password-reset', [AuthController::class, 'requestReset'])->middleware('throttle:otp');
    Route::post('/auth/password/email', [AuthController::class, 'requestReset'])->middleware('throttle:otp');
    Route::post('/auth/forgot_password', [AuthController::class, 'requestReset'])->middleware('throttle:otp');

    Route::post('/auth/reset-password', [AuthController::class, 'reset'])->middleware('throttle:verify');
    Route::post('/auth/password/reset', [AuthController::class, 'reset'])->middleware('throttle:verify');
    Route::post('/auth/password/update-reset', [AuthController::class, 'reset'])->middleware('throttle:verify');

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/shipping-centers', [ShippingCenterController::class, 'index']);
    Route::get('/social-links', SocialLinkController::class);
    Route::get('/app-version', AppVersionController::class);
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1');
    Route::get('/categories/{category}', [CategoryController::class, 'show']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}/recommendations', [ProductController::class, 'recommendations']);
    Route::get('/products/{product}/reviews', [ProductController::class, 'reviews']);
    Route::get('/products/{product}/questions', [ProductQuestionController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/stores/{sellerProfile}', [PublicStoreController::class, 'show']);
    Route::get('/stores/{sellerProfile}/products', [PublicStoreController::class, 'products']);
    Route::get('/stores/{sellerProfile}/reviews', [SellerReviewController::class, 'index']);

    Route::get('/search', [SearchController::class, 'index']);
    Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
    Route::get('/search/trending', [SearchController::class, 'trending']);
    Route::get('/stories', [StoriesController::class, 'index']);

    // ===== الشاشة الرئيسية - مساحات العرض المؤجرة (Showcases) =====
    Route::get('/home/showcases', [HomeShowcaseController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/showcases/track', [HomeShowcaseController::class, 'track'])->middleware('throttle:30,1');

    // عروض اليوم - عامة للجميع (تُستخدم في الصفحة الرئيسية للتطبيق)
    Route::get('/daily-deal', [DailyDealController::class, 'index']);

    Route::post('/forgot-password', [AuthController::class, 'requestReset'])->middleware('throttle:otp');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:verify');

    // مساعد خدمة العملاء الذكي - عام (يدعم الزبون المسجّل والزائر بنفس الوقت،
    // الـ Controller يتحقق من التوكن يدوياً لو موجود دون أن يفرضه).
    Route::prefix('chat')->group(function () {
        Route::get('/', [ChatAssistantController::class, 'show'])->middleware('throttle:60,1');
        Route::post('/send', [ChatAssistantController::class, 'send'])->middleware('throttle:20,1');
    });

    // ===== محمية - تحتاج توكن Sanctum =====
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->middleware('throttle:10,1');
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/stores/{sellerProfile}/follow', [StoreFollowController::class, 'toggle']);
        Route::get('/conversations', [BuyerSellerConversationController::class, 'index']);
        Route::post('/stores/{sellerProfile}/conversations', [BuyerSellerConversationController::class, 'start'])
            ->middleware('throttle:10,1');
        Route::get('/conversations/{conversation}', [BuyerSellerConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [BuyerSellerConversationController::class, 'send'])
            ->middleware('throttle:10,1');

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::get('/profile/followed-stores', [ProfileController::class, 'followedStores']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:verify');
        Route::get('/profile/account', [AccountClosureController::class, 'show']);
        Route::post('/profile/account/deactivate', [AccountClosureController::class, 'deactivate'])->middleware('throttle:5,1');
        Route::post('/profile/account/delete', [AccountClosureController::class, 'destroy'])->middleware('throttle:5,1');
        Route::get('/profile/addresses', [AddressController::class, 'index']);
        Route::get('/profile/addresses/reverse-geocode', ReverseGeocodeController::class)->middleware('throttle:10,1');
        Route::post('/profile/addresses', [AddressController::class, 'store'])->middleware('throttle:20,1');
        Route::put('/profile/addresses/{address}', [AddressController::class, 'update'])->whereNumber('address');
        Route::post('/profile/addresses/{address}/default', [AddressController::class, 'makeDefault'])->whereNumber('address');
        Route::delete('/profile/addresses/{address}', [AddressController::class, 'destroy'])->whereNumber('address');
        Route::get('/seller/profile', [SellerProfileController::class, 'show']);
        Route::post('/seller-application', [SellerApplicationController::class, 'store']);

        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::put('/cart/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);
        Route::delete('/cart', [CartController::class, 'clearAll']);

        // الشراء للعملاء فقط (مثل الموقع): حساب البائع/المدير لا يُنشئ طلب شراء
        Route::middleware('role:customer')->group(function () {
            Route::get('/checkout/summary', [CheckoutController::class, 'summary']);
            Route::post('/checkout', [CheckoutController::class, 'store']);
        });
        Route::get('/coupons', [CouponWalletController::class, 'index']);
        Route::post('/coupons/{campaign}/collect', [CouponCollectController::class, 'store'])
            ->middleware(['role:customer', 'throttle:30,1'])->whereNumber('campaign');
        // نقاط الولاء (للعملاء)
        Route::middleware('role:customer')->group(function () {
            Route::get('/loyalty', [LoyaltyController::class, 'show']);
            Route::get('/loyalty/transactions', [LoyaltyController::class, 'transactions']);
            Route::post('/loyalty/convert', [LoyaltyController::class, 'convert'])->middleware('throttle:10,1');
            // دعوة صديق
            Route::get('/referrals', [ReferralController::class, 'show']);
            Route::post('/referrals', [ReferralController::class, 'apply'])->middleware('throttle:10,1');
            // بطاقات الهدايا
            Route::get('/gift-cards', [GiftCardController::class, 'index']);
            Route::get('/gift-cards/{giftCard}', [GiftCardController::class, 'show'])->whereNumber('giftCard');
            Route::post('/gift-cards/purchase', [GiftCardController::class, 'purchase'])->middleware('throttle:10,1');
            Route::post('/gift-cards/redeem', [GiftCardController::class, 'redeem'])->middleware('throttle:10,1');
        });

        // إرجاع المنتجات (للزبائن فقط)
        Route::middleware('role:customer')->group(function () {
            Route::get('/returns', [ReturnController::class, 'index']);
            Route::get('/returns/{returnRequest}', [ReturnController::class, 'show'])->whereNumber('returnRequest');
            Route::post('/returns/{returnRequest}/withdraw', [ReturnController::class, 'withdraw'])->whereNumber('returnRequest');
            Route::post('/returns/{returnRequest}/dispute', [ReturnController::class, 'dispute'])->middleware('throttle:10,1')->whereNumber('returnRequest');
            Route::get('/orders/{order}/returnable', [ReturnController::class, 'returnable']);
            Route::post('/orders/{order}/returns', [ReturnController::class, 'store'])->middleware('throttle:10,1');
        });

        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show']);
        Route::post('/payments/start', [PaymentController::class, 'start'])->middleware('throttle:10,1');
        Route::get('/payments/config', [PaymentController::class, 'config']);

        // بطاقاتي البنكية (توكنات البنك فقط)
        Route::get('/cards', [PaymentController::class, 'cards']);
        Route::post('/cards', [PaymentController::class, 'addCard'])->middleware('throttle:10,1');
        Route::get('/cards/status/{reference}', [PaymentController::class, 'cardStatus']);
        Route::post('/cards/{card}/default', [PaymentController::class, 'setDefaultCard']);
        Route::delete('/cards/{card}', [PaymentController::class, 'deleteCard']);
        Route::get('/orders/{order}/payment-status', [PaymentController::class, 'status']);
        Route::get('/orders/{order}/items', [OrderController::class, 'items']);
        Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show']); // PDF لصاحب الطلب أو المدير
        // ===== تقييم البائع (نجوم للمتجر وليس للمنتج فقط) =====
        Route::post('/order-items/{orderItem}/seller-review', [SellerReviewController::class, 'store']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{order}/parcels/{shipment}/cancel', [OrderController::class, 'cancelParcel']);

        Route::post('/order-items/{orderItem}/review', [ReviewController::class, 'store']);
        Route::post('/products/{product}/reviews', [ReviewController::class, 'storeForProduct']);

        // ===== الأسئلة والأجوبة على المنتجات (Soru-Cevap) =====
        Route::post('/products/{product}/questions', [ProductQuestionController::class, 'store']);
        Route::post('/questions/{question}/answers', [ProductQuestionController::class, 'answer']);
        Route::delete('/questions/{question}', [ProductQuestionController::class, 'destroy']);
        Route::post('/questions/{question}/helpful', [ProductQuestionController::class, 'toggleHelpful']);

        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/{product}/toggle', [FavoriteController::class, 'toggle']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        // الإشعارات المنبثقة على الهاتف
        Route::post('/push/devices', [PushDeviceController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('/push/devices', [PushDeviceController::class, 'destroy'])->middleware('throttle:30,1');
        Route::get('/notification-settings', [NotificationSettingsController::class, 'show']);
        Route::put('/notification-settings', [NotificationSettingsController::class, 'update'])->middleware('throttle:30,1');
    });

    // ===== البائع - تحتاج توكن + دور seller =====
    Route::middleware(['auth:sanctum', 'role:seller', 'seller.store'])->prefix('seller')->group(function () {
        Route::post('/assistant/describe', [AssistantController::class, 'describe'])->middleware('throttle:30,1');
        Route::get('/profile', [SellerProfileController::class, 'show']);
        Route::post('/application', [SellerApplicationController::class, 'store']);
        Route::post('/identity-document', [IdentityDocumentController::class, 'store']);
        Route::get('/dashboard', [SellerDashboardController::class, 'show']);
        Route::get('/dashboard/analytics', [SellerDashboardController::class, 'analytics']);
        Route::put('/store', [SellerDashboardController::class, 'updateStore']);
        Route::get('/shipping', [SellerShippingSettingsController::class, 'show']);
        Route::put('/shipping', [SellerShippingSettingsController::class, 'update']);

        Route::get('/product-options', ProductOptionsController::class);
        Route::get('/products', [SellerProductController::class, 'index']);
        Route::post('/products', [SellerProductController::class, 'store']);
        Route::post('/products/{product}', [SellerProductController::class, 'update']); // POST + _method=PUT لدعم رفع الصور
        Route::delete('/products/{product}', [SellerProductController::class, 'destroy']);
        Route::put('/products/{product}/quick-stock', [SellerProductController::class, 'quickStock']);
        Route::get('/low-stock', [SellerProductController::class, 'lowStock']);
        Route::delete('/products/{product}/images/{imageId}', [SellerProductController::class, 'destroyImage']);
        Route::post('/products/{product}/images/{imageId}/primary', [SellerProductController::class, 'setPrimaryImage']);

        Route::get('/orders', [SellerOrderController::class, 'index']);

        Route::get('/returns', [SellerReturnController::class, 'index']);
        Route::get('/returns/{returnRequest}', [SellerReturnController::class, 'show'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/approve', [SellerReturnController::class, 'approve'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/reject', [SellerReturnController::class, 'reject'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/received', [SellerReturnController::class, 'received'])->whereNumber('returnRequest');

        Route::get('/wallet', [SellerWalletController::class, 'show']);
        Route::post('/wallet/withdraw', [SellerWalletController::class, 'requestWithdrawal']);
        Route::get('/wallet/withdrawals', [SellerWalletController::class, 'withdrawals']);

        Route::get('/stories', [SellerStoryController::class, 'index']);
        Route::post('/stories', [SellerStoryController::class, 'store']);
        Route::delete('/stories/{story}', [SellerStoryController::class, 'destroy']);
        Route::get('/coupons', [SellerCouponApiController::class, 'index']);
        Route::post('/coupons', [SellerCouponApiController::class, 'store']);
        Route::get('/coupons/{campaign}', [SellerCouponApiController::class, 'show'])->whereNumber('campaign');
        Route::put('/coupons/{campaign}', [SellerCouponApiController::class, 'update'])->whereNumber('campaign');
        Route::post('/coupons/{campaign}/toggle', [SellerCouponApiController::class, 'toggle'])->whereNumber('campaign');
        Route::delete('/coupons/{campaign}', [SellerCouponApiController::class, 'destroy'])->whereNumber('campaign');
        Route::get('/daily-deals', [SellerDailyDealController::class, 'index']);
        Route::post('/daily-deals', [SellerDailyDealController::class, 'store']);
        Route::put('/daily-deals/{deal}', [SellerDailyDealController::class, 'update']);
        Route::delete('/daily-deals/{deal}', [SellerDailyDealController::class, 'destroy']);
        Route::post('/daily-deals/{deal}/toggle', [SellerDailyDealController::class, 'toggle']);
        Route::put('/orders/{order}/status', [SellerOrderController::class, 'updateStatus']);
        Route::get('/orders/{order}', [SellerOrderController::class, 'show']);
        Route::get('/orders/{order}/label', [SellerOrderController::class, 'label']);

        // دعم البائع - التواصل مع الإدارة
        Route::get('/support', [SupportController::class, 'index'])->name('api.seller.support.index');
        Route::post('/support', [SupportController::class, 'store'])->name('api.seller.support.store');
        Route::get('/support/{conversation}', [SupportController::class, 'show'])->name('api.seller.support.show');
        Route::post('/support/{conversation}/message', [SupportController::class, 'sendMessage'])->name('api.seller.support.message');

        // أسئلة المنتجات - الرد كبائع
        Route::get('/questions', [QuestionController::class, 'index'])->name('api.seller.questions.index');
        Route::post('/questions/{question}/answer', [QuestionController::class, 'answer'])->name('api.seller.questions.answer');

        // طرق الدفع للبائع
        Route::get('/wallet/payment-methods', [PaymentMethodController::class, 'index'])->name('api.seller.payment-methods.index');
        Route::get('/wallet/payout-options', [PaymentMethodController::class, 'options']);
        Route::post('/wallet/payment-methods', [PaymentMethodController::class, 'store'])->name('api.seller.payment-methods.store');
        Route::delete('/wallet/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('api.seller.payment-methods.destroy');
        Route::post('/wallet/payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->name('api.seller.payment-methods.default');

        // مساحات العرض المؤجرة (Showcases)
        Route::get('/showcase-slots', [SellerShowcaseController::class, 'slots']);
        Route::get('/showcases', [SellerShowcaseController::class, 'index']);
        Route::get('/showcases/{showcase}', [SellerShowcaseController::class, 'show']);
        Route::post('/showcases', [SellerShowcaseController::class, 'store'])->middleware('throttle:10,1');
        Route::post('/showcases/{showcase}/cancel', [SellerShowcaseController::class, 'cancel']);
    });

    Route::middleware(['auth:sanctum', 'role:employee'])->prefix('employee')->group(function () {
        Route::get('/dashboard', [EmployeeController::class, 'dashboard']);

        Route::get('/orders', [EmployeeController::class, 'orders']);
        Route::get('/orders/{order}', [EmployeeController::class, 'showOrder']);
        Route::put('/orders/{order}/status', [EmployeeController::class, 'updateOrderStatus']);
        Route::post('/orders/{order}/archive', [EmployeeController::class, 'archiveOrder']);

        Route::get('/products', [EmployeeController::class, 'products']);
        Route::put('/products/{product}', [EmployeeController::class, 'updateProduct']);
        Route::post('/products/{product}/archive', [EmployeeController::class, 'archiveProduct']);
        Route::post('/products/{product}/approve', [AdminProductController::class, 'approve']);
        Route::post('/products/{product}/reject', [AdminProductController::class, 'reject']);

        // نفس صفحات المدير في الدعم ومحادثات العملاء والمرتجعات (إرجاع المال للمدير وحده)
        Route::get('/support', [ApiAdminSupportController::class, 'index']);
        Route::get('/support/{conversation}', [ApiAdminSupportController::class, 'show']);
        Route::post('/support/{conversation}/message', [ApiAdminSupportController::class, 'sendMessage']);
        Route::post('/support/{conversation}/close', [ApiAdminSupportController::class, 'close']);
        Route::post('/support/{conversation}/reopen', [ApiAdminSupportController::class, 'reopen']);
        Route::get('/assistant-chats', [AdminAssistantController::class, 'chats']);
        Route::get('/assistant-chats/{conversation}', [AdminAssistantController::class, 'chat']);
        Route::post('/assistant-chats/{conversation}/reply', [AdminAssistantController::class, 'reply']);
        Route::post('/assistant-chats/{conversation}/close', [AdminAssistantController::class, 'close']);
        Route::get('/returns', [AdminReturnController::class, 'index']);
        Route::get('/returns/{returnRequest}', [AdminReturnController::class, 'show'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/approve', [AdminReturnController::class, 'approve'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/reject', [AdminReturnController::class, 'reject'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/received', [AdminReturnController::class, 'received'])->whereNumber('returnRequest');
    });

    // ===== المدير - تحتاج توكن + دور admin =====
    // أمان حساب المدير: يعمل قبل إعداد التحقق بخطوتين (باقي اللوحة مقفلة حتى يُعدّ)
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/security')->group(function () {
        Route::get('/two-factor', [AdminTwoFactorController::class, 'show']);
        Route::post('/two-factor', [AdminTwoFactorController::class, 'confirm'])->middleware('throttle:verify');
        Route::post('/two-factor/recovery-codes', [AdminTwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:verify');
    });

    Route::middleware(['auth:sanctum', 'role:admin', 'admin.2fa'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'show']);
        Route::get('/analytics', [AdminDashboardController::class, 'analytics']);

        Route::get('/sellers', [AdminSellerController::class, 'index']);
        Route::delete('/sellers/{sellerProfile}', [AdminSellerController::class, 'destroy']);
        Route::post('/sellers/{sellerProfile}/suspend', [AdminSellerController::class, 'suspend']);
        Route::post('/sellers/{sellerProfile}/unsuspend', [AdminSellerController::class, 'unsuspend']);
        Route::get('/sellers/{sellerProfile}/identity-document', [AdminSellerController::class, 'identityDocument']);
        Route::get('/employees', [AdminSellerController::class, 'employees']);
        Route::post('/employees/{user}/approve', [AdminSellerController::class, 'approveEmployee']);
        Route::post('/employees/{user}/reject', [AdminSellerController::class, 'rejectEmployee']);
        Route::post('/sellers/{sellerProfile}/approve', [AdminSellerController::class, 'approve']);
        Route::post('/sellers/{sellerProfile}/reject', [AdminSellerController::class, 'reject']);
        Route::put('/sellers/{sellerProfile}/commission', [AdminSellerController::class, 'updateCommission']);
        Route::get('/sellers/{sellerProfile}/wallet', [AdminSellerController::class, 'walletStatement']);

        Route::get('/products', [AdminProductController::class, 'index']);
        Route::post('/products/{product}/approve', [AdminProductController::class, 'approve']);
        Route::post('/products/{product}/reject', [AdminProductController::class, 'reject']);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
        Route::post('/withdrawals/{withdrawalRequest}/approve', [AdminWithdrawalController::class, 'approve']);
        Route::post('/withdrawals/{withdrawalRequest}/reject', [AdminWithdrawalController::class, 'reject']);
        Route::post('/withdrawals/{withdrawalRequest}/paid', [AdminWithdrawalController::class, 'markPaid']);
        Route::post('/withdrawals/{withdrawalRequest}/failed', [AdminWithdrawalController::class, 'markFailed']);
        Route::post('/withdrawals/{withdrawalRequest}/send', [AdminWithdrawalController::class, 'send']);
        Route::post('/payout-methods/{paymentMethod}/verify', [AdminWithdrawalController::class, 'verifyMethod']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

        Route::get('/coupon-campaigns', [CouponCampaignController::class, 'index']);
        Route::post('/coupon-campaigns', [CouponCampaignController::class, 'store']);
        Route::post('/coupon-campaigns/{campaign}/toggle', [CouponCampaignController::class, 'toggle']);

        Route::get('/stories', [AdminStoryController::class, 'index']);
        Route::post('/stories', [AdminStoryController::class, 'store']);
        Route::delete('/stories/{story}', [AdminStoryController::class, 'destroy']);

        // المحذوفات، والمساعد الذكي (نفس صفحات الموقع)
        Route::get('/trash', [AdminTrashController::class, 'index']);
        Route::post('/trash/{type}/{id}/restore', [AdminTrashController::class, 'restore'])->whereNumber('id');
        Route::get('/assistant-chats', [AdminAssistantController::class, 'chats']);
        Route::get('/assistant-chats/{conversation}', [AdminAssistantController::class, 'chat']);
        Route::post('/assistant-chats/{conversation}/reply', [AdminAssistantController::class, 'reply']);
        Route::post('/assistant-chats/{conversation}/close', [AdminAssistantController::class, 'close']);
        Route::get('/assistant-faqs', [AdminAssistantController::class, 'faqs']);
        Route::post('/assistant-faqs', [AdminAssistantController::class, 'storeFaq']);
        Route::put('/assistant-faqs/{assistantFaq}', [AdminAssistantController::class, 'updateFaq']);
        Route::delete('/assistant-faqs/{assistantFaq}', [AdminAssistantController::class, 'destroyFaq']);
        // الإشعار الجماعي، وبطاقات الهدايا، ونقاط الولاء (نفس صفحات الموقع)
        Route::get('/push-broadcasts', [AdminPushBroadcastController::class, 'index']);
        Route::post('/push-broadcasts', [AdminPushBroadcastController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/gift-cards', [AdminGiftCardController::class, 'index']);
        Route::get('/loyalty', [AdminLoyaltyController::class, 'index']);
        Route::put('/loyalty/settings', [AdminLoyaltyController::class, 'updateSettings']);
        Route::get('/loyalty/customers/{user}', [AdminLoyaltyController::class, 'show']);
        Route::post('/loyalty/customers/{user}/adjust', [AdminLoyaltyController::class, 'adjust']);
        // ديون المتاجر، والمرتجعات، وكوبونات البائعين (نفس صفحات الموقع)
        Route::get('/accounting/debts', [AdminSellerDebtController::class, 'index']);
        Route::post('/accounting/debts/{sellerProfile}/pay', [AdminSellerDebtController::class, 'pay']);
        Route::get('/returns', [AdminReturnController::class, 'index']);
        Route::get('/returns/{returnRequest}', [AdminReturnController::class, 'show'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/approve', [AdminReturnController::class, 'approve'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/reject', [AdminReturnController::class, 'reject'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/received', [AdminReturnController::class, 'received'])->whereNumber('returnRequest');
        Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'refund'])->whereNumber('returnRequest');
        Route::get('/seller-coupons', [AdminSellerCouponController::class, 'index']);
        Route::post('/seller-coupons/{campaign}/approve', [AdminSellerCouponController::class, 'approve']);
        Route::post('/seller-coupons/{campaign}/reject', [AdminSellerCouponController::class, 'reject']);
        Route::post('/seller-coupons/{campaign}/suspend', [AdminSellerCouponController::class, 'suspend']);
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::post('/invitations/{invitation}/revoke', [InvitationController::class, 'revoke']);
        Route::get('/settings', [AdminSettingController::class, 'show']);
        Route::put('/settings', [AdminSettingController::class, 'update']);
        Route::post('/store/visit', [StoreController::class, 'trackVisit']);

        // دعم الإدارة - التواصل مع البائعين
        Route::get('/support', [ApiAdminSupportController::class, 'index'])->name('api.admin.support.index');
        Route::get('/support/{conversation}', [ApiAdminSupportController::class, 'show'])->name('api.admin.support.show');
        Route::post('/support/{conversation}/message', [ApiAdminSupportController::class, 'sendMessage'])->name('api.admin.support.message');
        Route::post('/support/{conversation}/close', [ApiAdminSupportController::class, 'close'])->name('api.admin.support.close');
        Route::post('/support/{conversation}/reopen', [ApiAdminSupportController::class, 'reopen'])->name('api.admin.support.reopen');

        // الشحن والدفع على الطلب
        Route::get('/shipping-companies', [AdminShippingController::class, 'companies']);
        Route::post('/shipping-companies', [AdminShippingController::class, 'storeCompany']);
        Route::post('/shipping-companies/{shippingCompany}/toggle', [AdminShippingController::class, 'toggleCompany']);
        Route::delete('/shipping-companies/{shippingCompany}', [AdminShippingController::class, 'destroyCompany']);
        Route::put('/orders/{order}/shipments/{shipment}', [AdminShippingController::class, 'update']);
        Route::post('/orders/{order}/payment/mark-paid', [AdminPaymentController::class, 'markPaid']);
        Route::post('/orders/{order}/payment/mark-failed', [AdminPaymentController::class, 'markFailed']);

        // عروض اليوم
        Route::get('/daily-deals', [AdminDailyDealController::class, 'index']);
        Route::get('/daily-deals/products', [AdminDailyDealController::class, 'create']);
        Route::post('/daily-deals', [AdminDailyDealController::class, 'store']);
        Route::put('/daily-deals/{deal}', [AdminDailyDealController::class, 'update']);
        Route::delete('/daily-deals/{deal}', [AdminDailyDealController::class, 'destroy']);
        Route::post('/daily-deals/{deal}/toggle', [AdminDailyDealController::class, 'toggleStatus']);

        // مساحات العرض المؤجّرة
        Route::get('/showcases', [AdminShowcaseController::class, 'index']);
        Route::post('/showcases/{showcase}/approve', [AdminShowcaseController::class, 'approve']);
        Route::post('/showcases/{showcase}/reject', [AdminShowcaseController::class, 'reject']);
        Route::post('/showcases/{showcase}/remove', [AdminShowcaseController::class, 'remove']);
        Route::get('/showcase-slots', [AdminShowcaseController::class, 'slots']);
        Route::post('/showcase-slots', [AdminShowcaseController::class, 'storeSlot']);
        Route::put('/showcase-slots/{showcaseSlot}', [AdminShowcaseController::class, 'updateSlot']);
        Route::delete('/showcase-slots/{showcaseSlot}', [AdminShowcaseController::class, 'destroySlot']);

        // التقارير والمحاسبة ({seller} = معرّف المستخدم البائع)
        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::get('/reports/export', [AdminReportController::class, 'exportPdf']);
        Route::get('/accounting', [AdminAccountingController::class, 'dashboard']);
        Route::get('/accounting/sellers', [AdminAccountingController::class, 'sellers']);
        Route::get('/accounting/sellers/{seller}', [AdminAccountingController::class, 'show']);
        Route::post('/accounting/sellers/{seller}/adjustments', [AdminAccountingController::class, 'adjust']);
        Route::get('/accounting/sellers/{seller}/export', [AdminAccountingController::class, 'exportCsv']);
        Route::post('/accounting/sellers/{seller}/statements', [AdminStatementController::class, 'store']);
        Route::get('/accounting/statements/{statement}/download', [AdminStatementController::class, 'download']);
    });

    Route::middleware(['auth:sanctum', 'role:admin', 'admin.2fa'])->group(function () {
        Route::put('/admin/orders/{order}', [AdminOrderController::class, 'updateStatus']);
    });
});

// إشعار البنك: بدون مصادقة المستخدم، والحماية بالتوقيع داخل verifyWebhook
Route::post('v1/payments/webhook/{driver}', [PaymentController::class, 'webhook'])
    ->name('payments.webhook')
    ->middleware('throttle:60,1');

// إشعار البنك بنتيجة حوالة أرباح بائع
Route::post('v1/payouts/webhook/{driver}', [PaymentController::class, 'payoutWebhook'])
    ->name('payouts.webhook')
    ->middleware('throttle:60,1');
