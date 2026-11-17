<?php

use App\Http\Controllers\AccountClosureController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\Accounting\AccountingController;
use App\Http\Controllers\Admin\Accounting\StatementController;
use App\Http\Controllers\Admin\AssistantChatController;
use App\Http\Controllers\Admin\AssistantFaqController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CouponCampaignController;
use App\Http\Controllers\Admin\DailyDealController as AdminDailyDealController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\LoyaltyController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PushBroadcastController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Admin\SellerController as AdminSellerController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\ShipmentController as AdminShipmentController;
use App\Http\Controllers\Admin\ShippingCompanyController as AdminShippingCompanyController;
use App\Http\Controllers\Admin\ShowcaseAdminController;
use App\Http\Controllers\Admin\ShowcaseSlotAdminController;
use App\Http\Controllers\Admin\StoryController as AdminStoryController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\TwoFactorController as AdminTwoFactorController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Auth\EmailAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatAssistantController;
use App\Http\Controllers\CouponCollectController;
use App\Http\Controllers\Customer\CardController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\ConversationController;
use App\Http\Controllers\Customer\CouponWalletController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\FavoriteController;
use App\Http\Controllers\Customer\FollowedStoreController;
use App\Http\Controllers\Customer\GiftCardController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\QuestionController as CustomerQuestionController;
use App\Http\Controllers\Customer\ReferralController;
use App\Http\Controllers\Customer\ReturnController as CustomerReturnController;
use App\Http\Controllers\Customer\ReviewController;
use App\Http\Controllers\Customer\SellerApplicationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicStoreController;
use App\Http\Controllers\ReverseGeocodeController;
use App\Http\Controllers\SandboxBankController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Seller\AssistantController;
use App\Http\Controllers\Seller\CouponCampaignController as SellerCouponController;
use App\Http\Controllers\Seller\DailyDealController as SellerDailyDealController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboard;
use App\Http\Controllers\Seller\OrderController as SellerOrderController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Http\Controllers\Seller\QuestionController;
use App\Http\Controllers\Seller\ReturnController as SellerReturnController;
use App\Http\Controllers\Seller\ShowcaseController as SellerShowcaseController;
use App\Http\Controllers\Seller\StoryController as SellerStoryController;
use App\Http\Controllers\Seller\SupportController;
use App\Http\Controllers\Seller\WalletController as SellerWalletController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StoreFollowController;
use App\Http\Controllers\VerifyController;
use App\Support\RecentlyViewed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// الصفحة الرئيسية
Route::get('/', [HomeController::class, 'index'])->name('home');
// لمحركات البحث
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
// رابط «دعوة صديق» العام
Route::get('/invite/{code}', [ReferralController::class, 'invite'])->middleware('throttle:30,1')->name('referrals.invite');

Route::post('/preferences/language', function (Request $request) {
    $validated = $request->validate(['locale' => ['required', 'in:ar,en,tr']]);
    $request->session()->put('locale', $validated['locale']);

    $referer = $request->headers->get('referer');
    $returnTo = $referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()
        ? $referer
        : route('home');

    return redirect()->to($returnTo)->withCookie(cookie('locale', $validated['locale'], 60 * 24 * 365));
})->name('preferences.language');

// البحث
Route::get('/search', [SearchController::class, 'index'])->name('search');

// الصفحات القانونية
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/account/delete', [AccountClosureController::class, 'info'])->name('legal.account-deletion');
Route::get('/refund-policy', [LegalController::class, 'refundPolicy'])->name('legal.refund-policy');

// صفحات عامة
Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/help', [PageController::class, 'helpCenter'])->name('pages.help');

// صفحات عامة للمنتجات والأقسام
Route::get('/product/{product}', [ProductController::class, 'show'])->name('products.show');
Route::post('/recently-viewed/clear', function () {
    RecentlyViewed::forget();

    return back();
})->name('recently-viewed.clear');

// المساعد الذكي من الموقع: نفس الكنترولر، لكن بجلسة الموقع ليتعرّف على العميل المسجّل
Route::get('/assistant/chat', [ChatAssistantController::class, 'show'])->middleware('throttle:60,1')->name('assistant.chat.show');
Route::post('/assistant/chat/send', [ChatAssistantController::class, 'send'])->middleware('throttle:20,1')->name('assistant.chat.send');
// التحقق من المستندات (رمز QR على كل PDF) — عامة بلا دخول، محدودة المحاولات
Route::get('/verify', [VerifyController::class, 'index'])->middleware('throttle:30,1')->name('verify.index');
Route::post('/verify/file', [VerifyController::class, 'checkFile'])->middleware('throttle:10,1')->name('verify.file');
Route::get('/verify/{code}', [VerifyController::class, 'show'])->middleware('throttle:30,1')->name('verify.show');

Route::get('/store/{sellerProfile}', [PublicStoreController::class, 'show'])->name('stores.show');
Route::get('/store/{sellerProfile}/products', [PublicStoreController::class, 'products'])->name('stores.products');
Route::post('/store/{sellerProfile}/message', [ConversationController::class, 'startWithVendor'])
    ->middleware(['auth', 'role:customer', 'throttle:10,1'])->name('stores.message');
Route::post('/store/{sellerProfile}/follow', [StoreFollowController::class, 'toggle'])
    ->middleware(['auth', 'role:customer'])->name('stores.follow');
Route::post('/customer/notification-settings/offers', [StoreFollowController::class, 'offers'])
    ->middleware(['auth', 'role:customer', 'throttle:20,1'])->name('customer.notification-settings.offers');
Route::post('/coupons/{campaign}/collect', [CouponCollectController::class, 'store'])
    ->middleware(['auth', 'role:customer', 'throttle:30,1'])->whereNumber('campaign')->name('coupons.collect');
Route::get('/category/{category}', [CategoryController::class, 'show'])->name('categories.show');

// تسجيل الدخول (موحّد: بريد إلكتروني أو هاتف + كلمة سر)
Route::get('/login', fn () => view('auth.login'))->name('login');
Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.verify')->middleware('throttle:verify');
});
Route::middleware(['guest', 'throttle:login'])->whereIn('provider', ['google', 'facebook'])->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});
Route::post('/login', [EmailAuthController::class, 'login'])->name('login.email')->middleware('throttle:login');
Route::post('/logout', [EmailAuthController::class, 'logout'])->middleware('auth')->name('logout');

// استعادة كلمة السر
Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:otp');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:verify');

// phone
Route::get('/forgot-password/phone', [PasswordResetController::class, 'showPhoneRequestForm'])->name('password.phone.request');
Route::post('/forgot-password/phone', [PasswordResetController::class, 'sendPhoneResetCode'])->name('password.phone.send')->middleware(['throttle:otp', 'throttle:5,1']);
Route::get('/reset-password/phone/verify', [PasswordResetController::class, 'showPhoneVerifyForm'])->name('password.phone.verify');
Route::post('/reset-password/phone/verify', [PasswordResetController::class, 'verifyPhoneResetCode'])->name('password.phone.verify.submit')->middleware(['throttle:verify', 'throttle:5,1']);
Route::get('/reset-password/phone', [PasswordResetController::class, 'showPhoneResetForm'])->name('password.phone.reset');
Route::post('/reset-password/phone', [PasswordResetController::class, 'phoneReset'])->name('password.phone.update')->middleware(['throttle:verify', 'throttle:3,1']);

// إنشاء حساب (بخطوتين: تعبئة البيانات ثم تأكيد رقم الهاتف برمز OTP)
Route::get('/register', [EmailAuthController::class, 'showRegister'])->name('register');
Route::get('/employee/registration-pending', [EmailAuthController::class, 'employeeRegistrationPending'])->name('employee.registration-pending');
Route::post('/register/start', [EmailAuthController::class, 'registerStart'])->name('register.start')->middleware('throttle:otp');
Route::post('/register/resend', [EmailAuthController::class, 'resendRegisterOtp'])->name('register.resend')->middleware('throttle:otp');
Route::post('/register/verify', [EmailAuthController::class, 'registerVerify'])->name('register.verify')->middleware('throttle:verify');

// الإشعارات - مشتركة لكل الأدوار المسجّلة
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update')->middleware('throttle:verify');
    Route::post('/profile/account/deactivate', [AccountClosureController::class, 'deactivate'])->name('profile.account.deactivate')->middleware('throttle:5,1');
    Route::post('/profile/account/delete', [AccountClosureController::class, 'destroy'])->name('profile.account.delete')->middleware('throttle:5,1');
    Route::get('/profile/addresses/reverse-geocode', ReverseGeocodeController::class)->name('profile.addresses.reverse-geocode')->middleware('throttle:10,1');
    Route::post('/profile/addresses', [AddressController::class, 'store'])->name('profile.addresses.store')->middleware('throttle:20,1');
    Route::put('/profile/addresses/{address}', [AddressController::class, 'update'])->name('profile.addresses.update')->whereNumber('address');
    Route::post('/profile/addresses/{address}/default', [AddressController::class, 'makeDefault'])->name('profile.addresses.default')->whereNumber('address');
    Route::delete('/profile/addresses/{address}', [AddressController::class, 'destroy'])->name('profile.addresses.destroy')->whereNumber('address');

    Route::get('/invoices/{order}', [InvoiceController::class, 'show'])->name('invoices.show');
});

Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->middleware('throttle:10,1')->name('conversations.send');
});

Route::middleware(['auth', 'role:seller', 'seller.store'])->prefix('seller')->name('seller.')->group(function () {
    // مساعد البائع: مسودة وصف المنتج
    Route::post('/assistant/describe', [AssistantController::class, 'describe'])->middleware('throttle:30,1')->name('assistant.describe');
    Route::get('/conversations', [App\Http\Controllers\Seller\ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [App\Http\Controllers\Seller\ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversation}/messages', [App\Http\Controllers\Seller\ConversationController::class, 'sendMessage'])
        ->middleware('throttle:10,1')->name('conversations.send');
});

// لوحة المدير
Route::middleware(['auth', 'role:admin', 'admin.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/security/two-factor', [AdminTwoFactorController::class, 'show'])->name('security.two-factor');
    Route::post('/security/two-factor', [AdminTwoFactorController::class, 'confirm'])->name('security.two-factor.confirm')->middleware('throttle:verify');
    Route::post('/security/two-factor/recovery-codes', [AdminTwoFactorController::class, 'regenerateRecoveryCodes'])->name('security.two-factor.recovery')->middleware('throttle:verify');

    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AdminDashboard::class, 'analytics'])->name('analytics');
    Route::get('/sellers/{sellerProfile}/identity-document', [AdminSellerController::class, 'identityDocument'])->name('sellers.identity-document');
    Route::post('/sellers/{sellerProfile}/approve', [AdminDashboard::class, 'approveSeller'])->name('sellers.approve');
    Route::post('/sellers/{sellerProfile}/reject', [AdminDashboard::class, 'rejectSeller'])->name('sellers.reject');

    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{returnRequest}', [AdminReturnController::class, 'show'])->name('returns.show')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/approve', [AdminReturnController::class, 'approve'])->name('returns.approve')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/reject', [AdminReturnController::class, 'reject'])->name('returns.reject')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/received', [AdminReturnController::class, 'received'])->name('returns.received')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/refund', [AdminReturnController::class, 'refund'])->name('returns.refund')->whereNumber('returnRequest');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::post('/orders/{order}/refused', [AdminOrderController::class, 'markRefused'])->name('orders.refused');

    Route::get('/withdrawals', [AdminWithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('/withdrawals/{withdrawalRequest}/approve', [AdminWithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{withdrawalRequest}/reject', [AdminWithdrawalController::class, 'reject'])->name('withdrawals.reject');
    Route::post('/withdrawals/{withdrawalRequest}/paid', [AdminWithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
    Route::post('/withdrawals/{withdrawalRequest}/failed', [AdminWithdrawalController::class, 'markFailed'])->name('withdrawals.failed');
    Route::post('/withdrawals/{withdrawalRequest}/send', [AdminWithdrawalController::class, 'send'])->name('withdrawals.send');
    Route::post('/payout-methods/{paymentMethod}/verify', [AdminWithdrawalController::class, 'verifyMethod'])->name('payout-methods.verify');

    Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

    // كوبونات البائعين: موافقة (فوق 50%) / رفض / إيقاف
    Route::get('/seller-coupons', [App\Http\Controllers\Admin\SellerCouponController::class, 'index'])->name('seller-coupons.index');
    Route::post('/seller-coupons/{campaign}/approve', [App\Http\Controllers\Admin\SellerCouponController::class, 'approve'])->name('seller-coupons.approve');
    Route::post('/seller-coupons/{campaign}/reject', [App\Http\Controllers\Admin\SellerCouponController::class, 'reject'])->name('seller-coupons.reject');
    Route::post('/seller-coupons/{campaign}/suspend', [App\Http\Controllers\Admin\SellerCouponController::class, 'suspend'])->name('seller-coupons.suspend');
    Route::get('/push-broadcasts', [PushBroadcastController::class, 'index'])->name('push-broadcasts.index');
    Route::post('/push-broadcasts', [PushBroadcastController::class, 'store'])->middleware('throttle:10,1')->name('push-broadcasts.store');

    // حملات كوبونات المحفظة (النظام الجديد بلا رموز)
    Route::get('/coupon-campaigns', [CouponCampaignController::class, 'index'])->name('coupon-campaigns.index');
    Route::get('/coupon-campaigns/create', [CouponCampaignController::class, 'create'])->name('coupon-campaigns.create');
    Route::post('/coupon-campaigns', [CouponCampaignController::class, 'store'])->name('coupon-campaigns.store');
    Route::post('/coupon-campaigns/{campaign}/toggle', [CouponCampaignController::class, 'toggle'])->name('coupon-campaigns.toggle');

    // نقاط الولاء
    Route::get('/loyalty', [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::get('/gift-cards', [App\Http\Controllers\Admin\GiftCardController::class, 'index'])->name('gift-cards.index');
    Route::put('/loyalty/settings', [LoyaltyController::class, 'updateSettings'])->name('loyalty.settings');
    Route::get('/loyalty/customers/{user}', [LoyaltyController::class, 'show'])->name('loyalty.show');
    Route::post('/loyalty/customers/{user}/adjust', [LoyaltyController::class, 'adjust'])->name('loyalty.adjust');

    // نظام الرموز القديم استُبدل بمحفظة الكوبونات
    Route::redirect('/coupons', '/admin/coupon-campaigns')->name('coupons.index');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::post('/products/{product}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject'])->name('products.reject');

    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::delete('/sellers/{sellerProfile}', [AdminSellerController::class, 'destroy'])->name('sellers.destroy');
    Route::post('/sellers/{sellerProfile}/suspend', [AdminSellerController::class, 'suspend'])->name('sellers.suspend');
    Route::post('/sellers/{sellerProfile}/unsuspend', [AdminSellerController::class, 'unsuspend'])->name('sellers.unsuspend');
    Route::put('/sellers/{sellerProfile}/commission', [AdminSellerController::class, 'updateCommission'])->name('sellers.updateCommission');
    Route::get('/employees', [AdminSellerController::class, 'employees'])->name('employees.index');
    Route::post('/employees/{user}/approve', [AdminSellerController::class, 'approveEmployee'])->name('employees.approve');
    Route::post('/employees/{user}/reject', [AdminSellerController::class, 'rejectEmployee'])->name('employees.reject');

    Route::get('/shipping-companies', [AdminShippingCompanyController::class, 'index'])->name('shippingCompanies.index');
    Route::post('/shipping-companies', [AdminShippingCompanyController::class, 'store'])->name('shippingCompanies.store');
    Route::post('/shipping-companies/{shippingCompany}/toggle', [AdminShippingCompanyController::class, 'toggle'])->name('shippingCompanies.toggle');
    Route::delete('/shipping-companies/{shippingCompany}', [AdminShippingCompanyController::class, 'destroy'])->name('shippingCompanies.destroy');

    Route::put('/orders/{order}/shipments/{shipment}', [AdminShipmentController::class, 'update'])->name('shipments.update');
    Route::post('/orders/{order}/payment/mark-paid', [AdminPaymentController::class, 'markPaid'])->name('payments.markPaid');
    Route::post('/orders/{order}/payment/mark-failed', [AdminPaymentController::class, 'markFailed'])->name('payments.markFailed');

    Route::get('/stories', [AdminStoryController::class, 'index'])->name('stories.index');
    Route::post('/stories', [AdminStoryController::class, 'store'])->name('stories.store');
    Route::delete('/stories/{story}', [AdminStoryController::class, 'destroy'])->name('stories.destroy');

    Route::get('/reports', [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [AdminReportController::class, 'exportPdf'])->name('reports.export');

    // المحاسبة - دفاتر البائعين والكشوف
    Route::prefix('accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'dashboard'])->name('dashboard');
        Route::get('sellers', [AccountingController::class, 'sellers'])->name('sellers');
        Route::get('sellers/{seller}', [AccountingController::class, 'show'])->name('seller');
        Route::get('sellers/{seller}/export', [AccountingController::class, 'exportCsv'])->name('export');
        Route::post('sellers/{seller}/adjustments', [AccountingController::class, 'adjust'])->name('adjust');

        Route::post('sellers/{seller}/statements', [StatementController::class, 'store'])->name('statements.store');
        Route::get('statements/{statement}/download', [StatementController::class, 'download'])->name('statements.download');
    });

    // المساعد الذكي: أجوبة الأسئلة الشائعة + المحادثات المحوّلة لموظف
    Route::resource('/assistant-faqs', AssistantFaqController::class)->except(['show']);
    Route::get('/assistant-chats', [AssistantChatController::class, 'index'])->name('assistant-chats.index');
    Route::get('/assistant-chats/{conversation}', [AssistantChatController::class, 'show'])->name('assistant-chats.show');
    Route::post('/assistant-chats/{conversation}/reply', [AssistantChatController::class, 'reply'])->name('assistant-chats.reply');
    Route::post('/assistant-chats/{conversation}/close', [AssistantChatController::class, 'close'])->name('assistant-chats.close');

    // دعم الإدارة - التواصل مع البائعين
    Route::get('/support', [AdminSupportController::class, 'index'])->name('support.index');
    Route::get('/support/{conversation}', [AdminSupportController::class, 'show'])->name('support.show');
    Route::post('/support/{conversation}/message', [AdminSupportController::class, 'sendMessage'])->name('support.message');
    Route::post('/support/{conversation}/close', [AdminSupportController::class, 'close'])->name('support.close');
    Route::post('/support/{conversation}/reopen', [AdminSupportController::class, 'reopen'])->name('support.reopen');

    // عروض اليوم - إدارة
    Route::resource('/daily-deals', AdminDailyDealController::class)->except(['show'])
        ->parameters(['daily-deals' => 'deal']); // الدوال تستقبل DailyDeal $deal
    Route::post('/daily-deals/{deal}/toggle', [AdminDailyDealController::class, 'toggleStatus'])->name('daily-deals.toggle');

    // مساحات العرض المؤجرة (Showcases)
    Route::get('showcases', [ShowcaseAdminController::class, 'index'])->name('showcases.index');
    Route::post('showcases/{showcase}/approve', [ShowcaseAdminController::class, 'approve'])->name('showcases.approve');
    Route::post('showcases/{showcase}/reject', [ShowcaseAdminController::class, 'reject'])->name('showcases.reject');
    Route::post('showcases/{showcase}/remove', [ShowcaseAdminController::class, 'remove'])->name('showcases.remove');

    Route::resource('showcase-slots', ShowcaseSlotAdminController::class)->except(['show']);
});

Route::middleware(['auth', 'role:employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/dashboard', fn () => view('employee.dashboard'))->name('dashboard');

    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::put('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::post('/orders/{order}/refused', [AdminOrderController::class, 'markRefused'])->name('orders.refused');
    Route::post('/orders/{order}/archive', [AdminOrderController::class, 'archive'])->name('orders.archive');

    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/archive', [AdminProductController::class, 'archive'])->name('products.archive');
    Route::post('/products/{product}/approve', [AdminProductController::class, 'approve'])->name('products.approve');
    Route::post('/products/{product}/reject', [AdminProductController::class, 'reject'])->name('products.reject');
});

// لوحة البائع
Route::middleware(['auth', 'role:seller', 'seller.store'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [SellerDashboard::class, 'index'])->name('dashboard');
    Route::get('/analytics', [SellerDashboard::class, 'analytics'])->name('analytics');
    Route::get('/store/edit', [SellerDashboard::class, 'editStore'])->name('store.edit');
    Route::put('/store', [SellerDashboard::class, 'updateProfile'])->name('store.update');
    Route::put('/profile', [SellerDashboard::class, 'updateProfile'])->name('profile.update');
    Route::get('/identity-verification', [SellerApplicationController::class, 'create'])->name('identity-verification.create');
    Route::post('/identity-verification', [SellerApplicationController::class, 'store'])->name('identity-verification.store');

    Route::get('/products', [SellerProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [SellerProductController::class, 'create'])->name('products.create');
    Route::post('/products', [SellerProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [SellerProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [SellerProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [SellerProductController::class, 'destroy'])->name('products.destroy');
    Route::delete('/products/{product}/images/{image}', [SellerProductController::class, 'destroyImage'])->name('products.images.destroy');
    Route::post('/products/{product}/images/{image}/primary', [SellerProductController::class, 'setPrimaryImage'])->name('products.images.setPrimary');

    Route::get('/orders', [SellerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [SellerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/status', [SellerOrderController::class, 'updateStatus'])->name('orders.updateStatus');

    Route::get('/returns', [SellerReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{returnRequest}', [SellerReturnController::class, 'show'])->name('returns.show')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/approve', [SellerReturnController::class, 'approve'])->name('returns.approve')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/reject', [SellerReturnController::class, 'reject'])->name('returns.reject')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/received', [SellerReturnController::class, 'received'])->name('returns.received')->whereNumber('returnRequest');

    Route::get('/wallet', [SellerWalletController::class, 'index'])->name('wallet.index');
    Route::post('/wallet/withdraw', [SellerWalletController::class, 'requestWithdrawal'])->name('wallet.withdraw');
    Route::get('/wallet/payment-methods', [SellerWalletController::class, 'paymentMethods'])->name('wallet.payment-methods');
    Route::post('/wallet/payment-methods', [SellerWalletController::class, 'addPaymentMethod'])->name('wallet.payment-methods.add');
    Route::delete('/wallet/payment-methods/{paymentMethod}', [SellerWalletController::class, 'removePaymentMethod'])->name('wallet.payment-methods.remove');
    Route::post('/wallet/payment-methods/{paymentMethod}/default', [SellerWalletController::class, 'setDefaultPaymentMethod'])->name('wallet.payment-methods.default');

    Route::get('/stories', [SellerStoryController::class, 'index'])->name('stories.index');
    Route::get('/stories/create', [SellerStoryController::class, 'create'])->name('stories.create');
    Route::post('/stories', [SellerStoryController::class, 'store'])->name('stories.store');
    Route::delete('/stories/{story}', [SellerStoryController::class, 'destroy'])->name('stories.destroy');

    // عروض اليوم - إدارة البائع لعروضه على منتجاته
    Route::get('/daily-deals', [SellerDailyDealController::class, 'index'])->name('daily-deals.index');
    Route::get('/daily-deals/create', [SellerDailyDealController::class, 'create'])->name('daily-deals.create');
    Route::post('/daily-deals', [SellerDailyDealController::class, 'store'])->name('daily-deals.store');
    Route::get('/daily-deals/{deal}/edit', [SellerDailyDealController::class, 'edit'])->name('daily-deals.edit');
    Route::put('/daily-deals/{deal}', [SellerDailyDealController::class, 'update'])->name('daily-deals.update');
    Route::delete('/daily-deals/{deal}', [SellerDailyDealController::class, 'destroy'])->name('daily-deals.destroy');
    Route::post('/daily-deals/{deal}/toggle', [SellerDailyDealController::class, 'toggleStatus'])->name('daily-deals.toggle');

    // أسئلة المنتجات - الرد كبائع
    Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::post('/questions/{question}/answer', [QuestionController::class, 'answer'])->name('questions.answer');

    // دعم البائع - التواصل مع الإدارة
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{conversation}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{conversation}/message', [SupportController::class, 'sendMessage'])->name('support.message');

    // مساحات العرض المؤجّرة (Showcases)
    // كوبونات البائع (يتحمّل خصمها من أرباحه)
    Route::get('/coupons', [SellerCouponController::class, 'index'])->name('coupons.index');
    Route::get('/coupons/create', [SellerCouponController::class, 'create'])->name('coupons.create');
    Route::post('/coupons', [SellerCouponController::class, 'store'])->name('coupons.store');
    Route::get('/coupons/{campaign}', [SellerCouponController::class, 'show'])->name('coupons.show')->whereNumber('campaign');
    Route::get('/coupons/{campaign}/edit', [SellerCouponController::class, 'edit'])->name('coupons.edit')->whereNumber('campaign');
    Route::put('/coupons/{campaign}', [SellerCouponController::class, 'update'])->name('coupons.update')->whereNumber('campaign');
    Route::post('/coupons/{campaign}/toggle', [SellerCouponController::class, 'toggle'])->name('coupons.toggle')->whereNumber('campaign');
    Route::delete('/coupons/{campaign}', [SellerCouponController::class, 'destroy'])->name('coupons.destroy')->whereNumber('campaign');

    Route::get('/showcases', [SellerShowcaseController::class, 'index'])->name('showcases.index');
    Route::get('/showcases/create', [SellerShowcaseController::class, 'create'])->name('showcases.create');
    Route::post('/showcases', [SellerShowcaseController::class, 'store'])->name('showcases.store');
    Route::get('/showcases/{showcase}', [SellerShowcaseController::class, 'show'])->name('showcases.show')->whereNumber('showcase');
    Route::post('/showcases/{showcase}/cancel', [SellerShowcaseController::class, 'cancel'])->name('showcases.cancel')->whereNumber('showcase');

    // AJAX endpoints
    Route::get('/showcases/slots-availability', [SellerShowcaseController::class, 'slotsAvailability'])->name('showcases.slots-availability');
    Route::post('/showcases/preview-colors', [SellerShowcaseController::class, 'previewColors'])->name('showcases.preview-colors');
});

// لوحة العميل
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [CustomerDashboard::class, 'index'])->name('dashboard');

    Route::get('/returns', [CustomerReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/{returnRequest}', [CustomerReturnController::class, 'show'])->name('returns.show')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/withdraw', [CustomerReturnController::class, 'withdraw'])->name('returns.withdraw')->whereNumber('returnRequest');
    Route::post('/returns/{returnRequest}/dispute', [CustomerReturnController::class, 'dispute'])->middleware('throttle:10,1')->name('returns.dispute')->whereNumber('returnRequest');
    Route::get('/orders/{order}/returns/create', [CustomerReturnController::class, 'create'])->name('returns.create');
    Route::post('/orders/{order}/returns', [CustomerReturnController::class, 'store'])->middleware('throttle:10,1')->name('returns.store');

    Route::get('/become-seller', [SellerApplicationController::class, 'create'])->name('seller-application.create');
    Route::post('/become-seller', [SellerApplicationController::class, 'store'])->name('seller-application.store');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::put('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::delete('/cart', [CartController::class, 'clearAll'])->name('cart.clearAll');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/coupon', [CheckoutController::class, 'chooseCoupon'])->name('checkout.coupon');
    Route::get('/coupons', [CouponWalletController::class, 'index'])->name('coupons.index');
    // نقاط الولاء
    Route::get('/points', [App\Http\Controllers\Customer\LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::post('/points/convert', [App\Http\Controllers\Customer\LoyaltyController::class, 'convert'])->middleware('throttle:10,1')->name('loyalty.convert');
    // دعوة صديق
    Route::get('/invite', [ReferralController::class, 'index'])->name('referrals.index');
    Route::post('/invite', [ReferralController::class, 'apply'])->middleware('throttle:10,1')->name('referrals.apply');
    // بطاقات الهدايا
    Route::get('/gift-cards', [GiftCardController::class, 'index'])->name('gift-cards.index');
    Route::post('/gift-cards/purchase', [GiftCardController::class, 'purchase'])->middleware('throttle:10,1')->name('gift-cards.purchase');
    Route::post('/gift-cards/redeem', [GiftCardController::class, 'redeem'])->middleware('throttle:10,1')->name('gift-cards.redeem');

    Route::get('/checkout/pay/{order}', [CustomerPaymentController::class, 'show'])->name('checkout.pay');
    Route::post('/checkout/pay/{order}', [CustomerPaymentController::class, 'process'])->name('checkout.pay.process');

    // بطاقاتي البنكية (توكنات البنك فقط — لا أرقام بطاقات على خوادمنا)
    Route::get('/cards', [CardController::class, 'index'])->name('cards.index');
    Route::post('/cards', [CardController::class, 'store'])->name('cards.store')->middleware('throttle:10,1');
    Route::get('/cards/return/{reference}', [CardController::class, 'back'])->name('cards.return');
    Route::post('/cards/{card}/default', [CardController::class, 'setDefault'])->name('cards.default');
    Route::delete('/cards/{card}', [CardController::class, 'destroy'])->name('cards.destroy');

    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::get('/followed-stores', [FollowedStoreController::class, 'index'])->name('followed-stores.index');
    Route::post('/favorites/{product}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    Route::post('/order-items/{orderItem}/review', [ReviewController::class, 'store'])->name('reviews.store');

    // ===== الأسئلة والأجوبة على المنتجات (Soru-Cevap) =====
    Route::post('/products/{product}/questions', [CustomerQuestionController::class, 'store'])->name('questions.store');
    Route::post('/questions/{question}/answers', [CustomerQuestionController::class, 'answer'])->name('questions.answer');
    Route::delete('/questions/{question}', [CustomerQuestionController::class, 'destroy'])->name('questions.destroy');
});

Route::get('payments/return/{reference}', [PaymentController::class, 'back'])->name('payments.return');
Route::get('gift-cards/return/{reference}', [GiftCardController::class, 'back'])->name('gift-cards.return');

// إيصال حوالة أرباح البائع (للمدير أو البائع صاحب الطلب)
Route::get('withdrawals/{withdrawalRequest}/receipt', [AdminWithdrawalController::class, 'receipt'])
    ->middleware('auth')->name('withdrawals.receipt');

// البنك التجريبي (يعمل فقط خارج production — راجع SandboxGateway::enabled)
Route::middleware('auth')->prefix('sandbox-bank')->name('payments.sandbox.')->group(function () {
    Route::get('/{kind}/{reference}', [SandboxBankController::class, 'show'])->name('show')->whereIn('kind', ['payment', 'card', 'gift']);
    Route::post('/{kind}/{reference}', [SandboxBankController::class, 'complete'])->name('complete')->whereIn('kind', ['payment', 'card', 'gift']);
});
