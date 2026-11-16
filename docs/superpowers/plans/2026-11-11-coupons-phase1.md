# محفظة الكوبونات — المرحلة 1 (الأساس) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** محفظة كوبونات بلا رموز، تمويل حسب المنشئ، كوبونات المدير (جدد/الكل/عميل معيّن) بأنواع نسبة/مبلغ/شحن مجاني، اختيار أفضل كوبون في الدفع، في الموقع والتطبيق.

**Architecture:** وحدة جديدة `app/Coupons/` (نماذج + خدمات) بجانب نظام الرموز القديم الذي يُزال من مسار الدفع (جدول `coupons` القديم يبقى فارغاً حتى المرحلة 2). قاعدة واحدة لكل شيء: `CouponEligibility` (صلاحية + سبب + توفير)، `CouponSelector` (الأفضل)، `CouponIssuer` (إصدار للمحفظة)، `CouponRedeemer` (تطبيق/عكس تحت قفل). التسوية المالية تقرأ `discount_funder` لكل بند.

**Tech Stack:** Laravel 12, MySQL, Blade + Bootstrap RTL, Sanctum API, Flutter (Provider).

**Spec:** `docs/superpowers/specs/2026-11-11-coupons-design.md`

## Global Constraints
- المبالغ بالوحدة الصغرى (int، ×100) كما في `orders.total`. رسم الشحن الحالي 15000 (`CheckoutController::SHIPPING_FEE`).
- كوبون واحد لكل طلب. لا رموز. كل النصوص للمستخدم بالعربية.
- "عميل جديد" = `role=customer` + لا طلبات غير ملغاة + `phone_verified_at` غير فارغ.
- المتجر الموقوف ← كوبوناته غير صالحة (المرحلة 2؛ في المرحلة 1 نطاق "متاجر" يستثني الموقوف).
- لا تعديل على قاعدة البيانات الحقيقية إلا بعد نسخة احتياطية.

## Review Focus
1. عميل يفتح الدفع في تبويبين ويستخدم نفس الكوبون مرتين → الطلب الثاني بلا خصم (قفل `customer_coupons` + حالة `reserved/used`).
2. إلغاء طلب بكوبون → الكوبون يعود `available` إن لم ينتهِ، وتُعكس أرباح/خصم البائع، `redeemed_count` ينقص.
3. سلة تغيّرت بعد اختيار الكوبون (حذف منتج) → إعادة الحساب في الخادم، والكوبون غير الصالح يسقط بصمت مع رسالة.
4. كوبون نسبة على سلة ضخمة → لا يتجاوز `max_discount`؛ مبلغ ثابت أكبر من السلة → الخصم = قيمة المشمول فقط.
5. كوبون "عملاء جدد" لعميل لديه طلب سابق أو هاتف غير متحقق → لا يُصدَر.

---

### Task 1: الجداول والنماذج
**Files:** Create `database/migrations/2026_11_14_000000_create_coupon_wallet_tables.php`, `app/Coupons/Models/{CouponCampaign,CustomerCoupon,CouponRedemption}.php`; Test `tests/Feature/Coupons/CouponModelsTest.php`
**Produces:**
- `coupon_campaigns`: id, funder enum(platform,seller), seller_profile_id null, title, type enum(percentage,fixed,free_shipping,gift), value int, max_discount int null, min_order_amount int default 0, scope enum(all,products,categories,stores), audience enum(new_customers,all_customers,specific,collectable,followers,automatic), trigger string null, starts_at, ends_at null, valid_days int null, per_customer_limit int default 1, total_limit int null, issued_count, redeemed_count, status enum(active,paused) , created_by, timestamps.
- `coupon_campaign_targets`: campaign_id, target_type enum(product,category,store,user), target_id; unique(campaign_id,target_type,target_id).
- `customer_coupons`: id, campaign_id, user_id, status enum(available,reserved,used,expired,revoked), expires_at null, source string, used_at null, timestamps; index(user_id,status).
- `coupon_redemptions`: id, customer_coupon_id unique-per-active, campaign_id, order_id, user_id, funder, seller_profile_id null, discount_amount int, shipping_discount int, status enum(applied,reversed), timestamps.
- `orders.customer_coupon_id` null; `order_items.discount_funder` enum(platform,seller) null.
- `CouponCampaign::isRunning(): bool` (active + بين التاريخين + لم يبلغ total_limit), علاقات targets(), customerCoupons().
- `CustomerCoupon::isUsable(): bool` (available + غير منتهٍ + الحملة تعمل).
- [ ] اختبار: إنشاء حملة ونسخة محفظة، `isUsable` true، بعد `ends_at` الماضي false، بعد `status=paused` false → FAIL ثم تنفيذ → PASS

### Task 2: الصلاحية والتوفير (`CouponEligibility`)
**Files:** Create `app/Coupons/CouponEligibility.php`, `app/Coupons/EligibilityResult.php`; Test `tests/Feature/Coupons/CouponEligibilityTest.php`
**Interfaces:** `check(CustomerCoupon $c, Collection $lines, int $shippingFee): EligibilityResult` حيث lines = عناصر لها `product` و`subtotal` (int). `EligibilityResult{bool ok; ?string reason; int discount; int shippingDiscount; array lineDiscounts /* key line index => int */}`.
- القواعد: نطاق (all/products/categories/stores)؛ مجموع المشمول ≥ min_order_amount وإلا reason "أضف X ل.س لتستفيد"؛ نسبة → round(eligible×value/100) بحد max_discount؛ fixed → min(value, eligible)؛ free_shipping → shippingDiscount = shippingFee؛ توزيع الخصم على البنود المشمولة نسبياً والباقي على آخر بند؛ أسباب: "انتهت صلاحيته"، "يعمل على منتجات … فقط"، "يبدأ في …".
- [ ] اختبارات: نسبة بحد أعلى (20% من 1,000,000 بحد 50,000 = 50,000)، fixed أكبر من السلة، حد أدنى غير محقق مع نص السبب، نطاق قسم يستثني بنداً آخر، free_shipping، مجموع lineDiscounts = discount.

### Task 3: الإصدار (`CouponIssuer`) + اختيار الأفضل (`CouponSelector`)
**Files:** Create `app/Coupons/CouponIssuer.php`, `app/Coupons/CouponSelector.php`; Test `tests/Feature/Coupons/CouponIssuerTest.php`
**Interfaces:** `CouponIssuer::issue(CouponCampaign, User, string $source): ?CustomerCoupon` (يحترم per_customer_limit وtotal_limit، `lockForUpdate` على الحملة، إشعار `coupon_received`)؛ `syncFor(User): void` يصدر حملات المنصة الجارية للجمهور all_customers/new_customers المستحق لها؛ `issueToSpecific(CouponCampaign, User)`.
`CouponSelector::best(User, Collection $lines, int $shippingFee): array{chosen:?CustomerCoupon, result:?EligibilityResult, options: list<array{coupon, result}>}` — الأفضل = أكبر (discount+shippingDiscount).
- [ ] اختبارات: syncFor يصدر all_customers مرة واحدة فقط عند الاستدعاء مرتين؛ new_customers لا يصدر لمن لديه طلب أو هاتف غير متحقق؛ specific يصل للعميل المحدد فقط؛ total_limit يمنع الإصدار الزائد؛ best يختار الأكبر توفيراً.

### Task 4: التطبيق عند الدفع والعكس عند الإلغاء + التسوية (`CouponRedeemer`)
**Files:** Create `app/Coupons/CouponRedeemer.php`; Modify `app/Http/Controllers/Customer/CheckoutController.php`, `app/Http/Controllers/Api/CheckoutController.php`, `app/Models/Order.php` (مسار الإلغاء)، `app/Models/OrderItem.php` (settleCommission)؛ Test `tests/Feature/Coupons/CouponRedemptionTest.php`
**Interfaces:** `redeem(int $customerCouponId, User, Order, Collection $lines, int $shippingFee): ?EligibilityResult` داخل معاملة الدفع (قفل الصف، تحقق الملكية والصلاحية، status=used، redemption، redeemed_count++)؛ `reverse(Order): void` (redemption=reversed، الكوبون available إن لم ينتهِ، redeemed_count--).
- التسوية: `settleCommission` ← الأساس = subtotal − (discount_funder=='seller' ? discount_amount : 0).
- [ ] اختبارات (أرقام التصميم): كوبون منصة 20,000 على 100,000 عمولة 10% → محفظة البائع 90,000؛ كوبون بائع → 72,000؛ شحن مجاني → shipping_fee=0 وtotal = subtotal؛ نفس الكوبون في طلبين → الثاني بلا خصم؛ إلغاء → الكوبون available وredeemed_count=0 وأرباح البائع معكوسة؛ كوبون عميل آخر → مرفوض.

### Task 5: الموقع — كوبوناتي + الدفع + لوحة المدير
**Files:** Create `app/Http/Controllers/Customer/CouponWalletController.php`, `resources/views/customer/coupons/index.blade.php`, `app/Http/Controllers/Admin/CouponCampaignController.php`, `resources/views/admin/coupon-campaigns/{index,create}.blade.php`; Modify `routes/web.php`, `resources/views/customer/checkout/show.blade.php`, `customer/partials/account-shell.blade.php`, admin nav؛ Test `tests/Feature/Coupons/CouponWebTest.php`
- مسارات: `customer.coupons.index`، `POST customer.checkout.coupon` {customer_coupon_id|none} (يحفظ الاختيار في الجلسة)، `admin.coupon-campaigns.{index,create,store,toggle}`. تُزال `checkout.applyCoupon/removeCoupon` ونموذج الرمز.
- الدفع: `CouponSelector::best` يطبق الأفضل افتراضياً، وقائمة "تغيير" (صالح + توفير / غير صالح + سبب)، سطر الخصم بمصدره.
- المدير: نموذج (النوع، القيمة، الحد الأعلى، الحد الأدنى، النطاق مع اختيار أقسام/متاجر، الجمهور مع بحث عميل بالهاتف/البريد، التواريخ، الحدود)؛ تأكيد عدد المستهدفين لـ"كل العملاء".
- [ ] اختبارات: صفحة كوبوناتي تعرض كوبون all_customers بعد syncFor؛ الدفع يطبق الأفضل تلقائياً ويُنشئ redemption؛ اختيار "بدون كوبون"؛ المدير ينشئ حملة لعميل معيّن فتصل محفظته فقط؛ غير المدير ممنوع.

### Task 6: واجهة التطبيق (API)
**Files:** Create `app/Http/Controllers/Api/CouponWalletController.php`, `app/Http/Controllers/Api/Admin/CouponCampaignController.php`; Modify `routes/api.php`, `app/Http/Controllers/Api/CheckoutController.php`؛ Test `tests/Feature/Coupons/CouponApiTest.php`
- `GET /coupons` (available/used/expired)، `POST /checkout/coupons/preview` {} → {best_id, options[{id,title,funder,ok,reason,saving}]}، `POST /checkout` يقبل `customer_coupon_id` (null = بلا كوبون، غائب = الأفضل). المدير: `GET/POST /admin/coupon-campaigns`, `POST /admin/coupon-campaigns/{id}/toggle`.
- [ ] اختبارات مقابلة لما سبق + طلب بكوبون لا يملكه العميل → 422.

### Task 7: تطبيق Flutter
**Files:** Create `lib/models/wallet_coupon.dart`, `lib/services/coupon_service.dart`, `lib/screens/coupons/my_coupons_screen.dart`, `lib/screens/admin/admin_coupon_campaigns_screen.dart`; Modify `lib/screens/checkout/checkout_screen.dart`, profile entry, admin dashboard entry (يستبدل `admin_coupons_screen`)؛ Test `test/wallet_coupon_test.dart`
- [ ] اختبار نموذج (تحليل JSON، الحالة، نص التوفير) → تنفيذ → `flutter analyze` نظيف و`flutter test` ناجح.

### Task 8: الإزالة والتحقق
- إزالة مسار الرمز القديم من الدفع (موقع/تطبيق) وتحديث اختبارات `CustomerCheckoutTest` الثلاثة الخاصة بالرمز لتصبح اختبارات محفظة مكافئة (منتهي/حد أدنى/تطبيق وعدّ).
- [ ] `php artisan test --compact` كله ناجح، `flutter test` و`flutter analyze`.
- [ ] مراجعة مستقلة، ثم فحص أمني (`/security-review` أو يدوي إن تعذّر).
- [ ] نسخة احتياطية ثم الترحيل على قاعدة البيانات الحقيقية.
