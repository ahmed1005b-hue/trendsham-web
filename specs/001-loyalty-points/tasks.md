---

description: "قائمة مهام تنفيذ نظام نقاط الولاء"
---

# Tasks: نظام نقاط الولاء للعملاء

**Input**: Design documents from `/specs/001-loyalty-points/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: مُضمّنة. قواعد المشروع تشترط نجاح الاختبارات قبل اعتبار أي عمل منتهياً، و[quickstart.md](quickstart.md) يربط كل سيناريو باختبار Feature. تُكتب الاختبارات أولاً وتُشاهَد وهي تفشل.

**Organization**: المهام مجمّعة حسب قصص المستخدم ليُنفَّذ كل منها ويُختبر وحده.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: يمكن تنفيذها بالتوازي (ملفات مختلفة، بلا اعتماد على مهام غير منتهية)
- **[Story]**: القصة التي تخدمها المهمة (US1…US5 من spec.md)

## Path Conventions

- **الخادم والموقع**: `c:\xampp\htdocs\trendsham\` (`app/`, `database/`, `resources/views/`, `routes/`, `tests/`)
- **التطبيق**: `C:\Users\istanbul\Desktop\trendsham_flutter\` (`lib/`, `test/`) — يُشار إليه بـ`flutter:` قبل المسار
- المبالغ بالوحدة الصغرى (×100) في الخادم، وبالليرة في استجابات `/api/v1`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: تجهيز آمن قبل أي تعديل

- [X] T001 أخذ نسخة احتياطية من قاعدة `trendsham` إلى `C:\Users\istanbul\AppData\Local\Temp\claude\…\scratchpad\trendsham-before-loyalty.sql` بـ`C:\xampp\mysql\bin\mysqldump -u root trendsham`، و**طلب موافقة صاحب المشروع** على الهجرات قبل تشغيلها على القاعدة الحقيقية
- [X] T002 إنشاء مجلد المجال `app/Loyalty/` و`app/Loyalty/Models/` و`tests/Feature/Loyalty/` و`tests/Unit/Loyalty/`، ومساعد اختبارات `tests/Feature/Loyalty/LoyaltyTestHelpers.php` (trait: إنشاء عميل، طلب بمبلغ معيّن بحالة معيّنة، تسليمه عبر `Order::changeStatus('delivered')`، منح رصيد مباشر)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: الجداول والنماذج ودفتر النقاط — تعتمد عليها كل القصص

**⚠️ CRITICAL**: لا تبدأ أي قصة قبل اكتمال هذه المرحلة

- [X] T003 هجرة `database/migrations/2026_11_17_000000_create_loyalty_tables.php`: جدول `loyalty_accounts` (`user_id` FK فريد، `balance` int ≥ 0 default 0، `debt` int ≥ 0 default 0، `lifetime_earned` int default 0، timestamps)؛ جدول `loyalty_transactions` (`user_id` FK، `type` enum: `earn`, `restore`, `admin_add`, `redeem_checkout`, `redeem_coupon`, `reverse_earn`, `expire`, `admin_deduct`, `debt_settle`؛ `points` int موجب للإضافة وسالب للخصم؛ `remaining` int null للدفعات فقط؛ `expires_at` null؛ `expiry_notified_at` null؛ `order_id` FK null؛ `customer_coupon_id` FK null؛ `admin_id` FK users null؛ `reason` string null؛ `created_at`) مع `INDEX(user_id, expires_at)` و`INDEX(customer_coupon_id)`، وعمود مولّد `order_unique_key` = `CONCAT(type,'-',order_id)` فقط لأنواع `earn`/`reverse_earn`/`redeem_checkout` عليه `UNIQUE` لضمان "مرة واحدة لكل طلب"؛ جدول `loyalty_allocations` (`debit_transaction_id` FK، `lot_transaction_id` FK، `points` int > 0)
- [X] T004 هجرة `database/migrations/2026_11_17_000001_add_loyalty_columns.php`: في `orders`: `points_redeemed` int default 0، `points_discount` bigint default 0؛ في `platform_settings`: `loyalty_enabled` bool default true، `loyalty_spend_per_point` bigint default 1000000، `loyalty_point_value` bigint default 20000، `loyalty_min_redeem` int default 10، `loyalty_expiry_months` int default 12، `loyalty_coupon_valid_days` int default 30
- [X] T005 [P] النماذج `app/Loyalty/Models/LoyaltyAccount.php` و`LoyaltyTransaction.php` (ثوابت الأنواع، scopes: `lots()` للأنواع الدائنة، `active()` لـ`remaining > 0` وغير منتهية، `label()` نص عربي للحركة) و`LoyaltyAllocation.php`، وعلاقة `loyaltyAccount()` في `app/Models/User.php`
- [X] T006 [P] `app/Loyalty/LoyaltySettings.php`: يقرأ الأعمدة الست من `PlatformSetting::current()` بقيم افتراضية، ويعرض `enabled()`, `spendPerPoint()`, `pointValue()`, `minRedeem()`, `expiryMonths()`, `couponValidDays()`
- [X] T007 [P] اختبار وحدة `tests/Unit/Loyalty/LoyaltyCalculatorTest.php`: 47,500 ل.س → 4، 19,999 → 1، 9,999 → 0، القيمة `20 × 20000 = 400000` (4,000 ل.س)، `maxUsable` لا يتجاوز `subtotal − couponDiscount` ولا الرصيد، "أضف 2,000 ل.س" لسلة 8,000
- [X] T008 `app/Loyalty/LoyaltyCalculator.php`: `pointsFor(int $paidMinor): int` بالتقريب للأسفل، `valueOf(int $points): int`، `paidBase(Order $o): int` = `subtotal − discount_amount − points_discount` (دون الشحن)، `maxUsable(int $balance, int $payableMinor): int`، `hint(int $cartMinor): array{points:int, missing:int}`، `pending(User $u): int` من طلبات `pending|paid|processing|shipped` — حتى ينجح T007
- [X] T009 `app/Loyalty/LoyaltyLedger.php` (النواة): كل عملية داخل `DB::transaction` مع `lockForUpdate()` على `loyalty_accounts` (يُنشأ عند أول حركة):
  - `credit(User, type, points, ?Order, ?reason, ?adminId, ?expiresAt)`: يسدّد `debt` أولاً بحركة `debt_settle`، ثم ينشئ دفعة بـ`remaining` و`expires_at = now + expiryMonths`
  - `debit(User, type, points, …)`: يستهلك الدفعات `active()` مرتبة بـ`expires_at` ثم `id` (الأقدم أولاً)، ويسجّل `loyalty_allocations` لكل دفعة، ويرفض إن تجاوز `balance` (إلا إن مُرِّر `allowDebt` فيُحمّل الباقي على `debt`)
  - `restoreAllocations(LoyaltyTransaction $debit)`: يعيد النقاط إلى نفس الدفعات إن لم تنتهِ (`remaining += n`)، والمنتهي منها يُسجَّل `expire` فوراً
  - `expireDue(): int`: لكل دفعة منتهية بـ`remaining > 0` حركة `expire` وتصفير `remaining`
  - ثابت يُفحص في الاختبارات: `balance = Σ remaining (دفعات غير منتهية)` و`Σ points = balance − debt`
- [X] T010 اختبار `tests/Feature/Loyalty/LedgerTest.php`: FIFO، تسجيل التوزيع، الإرجاع لنفس الدفعات، الدفعة المنتهية لا تُرجَع، `debt` ثم سداده من كسب لاحق، رفض الخصم فوق الرصيد، والثابت `Σ points = balance − debt` بعد كل عملية
- [X] T011 تشغيل الهجرات على `trendsham_testing` عبر الاختبارات، ثم — بعد موافقة T001 — `php artisan migrate` على `trendsham`

**Checkpoint**: الدفتر يعمل ومختبر — يمكن بدء القصص

---

## Phase 3: User Story 1 - كسب النقاط من المشتريات (Priority: P1) 🎯 MVP

**Goal**: عند تسليم الطلب يكسب العميل نقطة لكل 10,000 ل.س مدفوعة فعلياً، وتُسحب عند الإرجاع

**Independent Test**: إتمام طلب وتسليمه ثم التحقق من الرصيد والسجل والإشعار (سيناريوهات quickstart 1–5 و17)

### Tests for User Story 1 ⚠️

- [X] T012 [P] [US1] `tests/Feature/Loyalty/EarningTest.php`: تسليم طلب 47,500 + شحن → 4 نقاط ودفعة تنتهي بعد 12 شهراً وإشعار `loyalty_earned`؛ تسليم مرتين (ذهاباً وإياباً بين الحالات) → 4 فقط؛ كوبون 5,000 على 47,500 → 4؛ إلغاء قبل التسليم → 0؛ إرجاع (إلغاء بعد التسليم) بعد صرف النقاط → `balance` 0 و`debt` بالباقي وإشعار `loyalty_reversed`، ثم كسب لاحق يسدّ الدين أولاً؛ طلب بائع أو مدير → 0؛ `loyalty_enabled=false` → 0

### Implementation for User Story 1

- [X] T013 [US1] في `app/Loyalty/LoyaltyLedger.php`: `earnForOrder(Order)` (للعملاء `role=customer` فقط، يتجاهل الصفر، `type=earn` مرة واحدة بالقيد الفريد) و`reverseEarnForOrder(Order)` (`reverse_earn` بقيمة نقاط `earn` لنفس الطلب مع `allowDebt`)
- [X] T014 [US1] ربطها في `app/Models/Order.php` داخل `settleForStatusChange()`: أول `delivered` → `earnForOrder`؛ أول `cancelled` بعد أن كان مسلّماً سابقاً (توجد حركة `earn` للطلب) → `reverseEarnForOrder` — بجانب `settleCommission()`/`reverseSettlement()` وتحت نفس القفل
- [X] T015 [US1] الإشعارات عبر `AppNotification::send()` بالنوعين `loyalty_earned` و`loyalty_reversed` ونصوص [contracts/web.md](contracts/web.md)، ورابط `route('customer.loyalty.index')`
- [X] T016 [US1] تشغيل `php artisan test --filter=Loyalty` حتى ينجح T012، ثم `php artisan test` كاملاً

**Checkpoint**: الكسب يعمل وحده — MVP

---

## Phase 4: User Story 2 - استبدال النقاط بخصم (Priority: P1)

**Goal**: الاستبدال مباشرة عند الدفع (ويب + تطبيق) أو بالتحويل لكوبون في المحفظة؛ المنصة تتحمّل الخصم

**Independent Test**: منح رصيد ثم الاستبدال بالطريقتين والتحقق من الخصم والرصيد وأرباح البائع (سيناريوهات 6–11 و18)

### Tests for User Story 2 ⚠️

- [X] T017 [P] [US2] `tests/Feature/Loyalty/CheckoutRedemptionTest.php` (ويب و`POST /api/v1/checkout`): رصيد 30 و`use_points=20` على سلة 50,000 → `points_discount=400000`، `total` ينقص 4,000، الرصيد 10، و`vendor_earning` للبنود كما هو بلا خصم بعد التسليم؛ `use_points=5` → خطأ `use_points` بلا طلب؛ `use_points` أكبر من الرصيد → خطأ؛ قيمة أكبر من السلة → تُقصّ و`total ≥ shipping_fee`؛ مع كوبون → النقاط على الباقي بعد الكوبون؛ إلغاء قبل التسليم → النقاط تعود لدفعاتها؛ تسليم الطلب → يكسب على `subtotal − discount − points_discount`؛ حساب موقوف → رفض
- [X] T018 [P] [US2] `tests/Feature/Loyalty/CouponConversionTest.php` (ويب و`POST /api/v1/loyalty/convert`): 20 نقطة → كوبون 4,000 ل.س في `customer_coupons` بـ`source='loyalty'` صالح 30 يوماً والرصيد ↓20؛ أقل من 10 أو أكثر من الرصيد → 422؛ الكوبون يُستخدم في الدفع بتمويل المنصة؛ كوبون منتهٍ غير مستخدم + `loyalty:daily` → النقاط تعود لدفعاتها؛ طلبان متزامنان → كوبون واحد

### Implementation for User Story 2

- [X] T019 [US2] في `app/Loyalty/LoyaltyLedger.php`: `redeemForOrder(User, Order, int $points)` (`type=redeem_checkout`، يفحص `minRedeem` والرصيد وأن الحساب غير موقوف) و`restoreRedeemedForOrder(Order)` (يستدعي `restoreAllocations` لحركة `redeem_checkout` للطلب، آمن للتكرار)
- [X] T020 [US2] ربط الإرجاع في `app/Models/Order.php` → `settleForStatusChange()`: أول `cancelled` → `restoreRedeemedForOrder` (بجانب `CouponRedeemer::reverse`)
- [X] T021 [US2] الدفع في الويب `app/Http/Controllers/Customer/CheckoutController.php`: `show()` يمرّر `loyalty` (`balance`, `max_usable`, `will_earn`)؛ `store()` يقبل `use_points` (`nullable|integer|min:0`)، وبعد حساب الكوبون داخل نفس المعاملة: `points = min(use_points, maxUsable)`، يحفظ `points_redeemed` و`points_discount`، ويطرح من `total`، ثم `redeemForOrder` بعد إنشاء الطلب
- [X] T022 [US2] واجهة الدفع `resources/views/customer/checkout/show.blade.php`: بطاقة "استخدم نقاطي" (الرصيد وقيمته، حقل عدد بحد أقصى `max_usable`، زر "استخدم الكل")، سطر "خصم النقاط" في الملخص يتحدث فوراً بـJS، وسطر "ستكسب X نقاط عند الاستلام" — RTL ومتوافقة مع `ts-dark.css`
- [X] T023 [US2] الدفع في التطبيق `app/Http/Controllers/Api/CheckoutController.php`: `summary()` يضيف كائن `loyalty` و`store()` يقبل `use_points` بنفس منطق T021 ويعيد `points_redeemed`/`points_discount` بالليرة ([contracts/api.md](contracts/api.md))
- [X] T024 [US2] `app/Loyalty/PointsCouponIssuer.php`: `convert(User, int $points): CustomerCoupon` — حملة `firstOrCreate` بعنوان "خصم نقاط الولاء {القيمة}" (`funder=platform`, `type=fixed`, `value=القيمة`, `audience=specific`, `scope=all`, `trigger='loyalty_points'`, `valid_days=couponValidDays`)، كوبون `source='loyalty'` بـ`expires_at`، وحركة `redeem_coupon` بـ`customer_coupon_id` — كلها في معاملة واحدة مع قفل الحساب
- [X] T025 [US2] إخفاء حملات `trigger='loyalty_points'` من قائمة حملات المدير في `app/Http/Controllers/Admin/CouponCampaignController.php` و`app/Http/Controllers/Api/Admin/CouponCampaignController.php`
- [X] T026 [US2] المتحكمات والمسارات: `app/Http/Controllers/Customer/LoyaltyController.php@convert` + مسار `POST /customer/points/convert` (`customer.loyalty.convert`) في `routes/web.php`؛ `app/Http/Controllers/Api/LoyaltyController.php@convert` + `POST /api/v1/loyalty/convert` في `routes/api.php` (throttle)
- [X] T027 [US2] `app/Console/Commands/LoyaltyDailyCommand.php` (`loyalty:daily`) — الجزء الخاص بكوبونات النقاط: `customer_coupons` بـ`source='loyalty'` و`status=available` و`expires_at < now` → `status=expired` و`restoreAllocations` لحركتها؛ جدولته يومياً في `routes/console.php`
- [X] T028 [P] [US2] التطبيق: `flutter:lib/services/loyalty_service.dart` (`fetchSummary`, `fetchTransactions`, `convert`) و`flutter:lib/services/checkout_service.dart` (قراءة `loyalty` من الملخص وإرسال `use_points`)
- [X] T029 [US2] التطبيق: مفتاح "استخدم نقاطي" وعدّاد النقاط وسطر الخصم في `flutter:lib/screens/checkout/checkout_screen.dart`
- [X] T030 [US2] تشغيل T017 وT018 حتى النجاح، ثم `php artisan test` و`flutter analyze`

**Checkpoint**: الكسب والاستبدال يعملان — قيمة كاملة للعميل

---

## Phase 5: User Story 3 - صفحة "نقاطي" (Priority: P2)

**Goal**: الرصيد والمعلّق والسجل وتنبيه الانتهاء، متطابقة في الموقع والتطبيق، مع إنهاء الصلاحية والتنبيه قبل 30 يوماً

**Independent Test**: عميل بحركات متنوعة: نفس الأرقام في `/customer/points` و`GET /api/v1/loyalty` (سيناريوهات 12–14 و19)

### Tests for User Story 3 ⚠️

- [X] T031 [P] [US3] `tests/Feature/Loyalty/MyPointsTest.php`: الصفحة والواجهة تعرضان نفس `balance` و`pending` والسجل؛ تنبيه "تنتهي خلال 30 يوماً"؛ عميل آخر لا يرى سجل غيره
- [X] T032 [P] [US3] `tests/Feature/Loyalty/ExpiryTest.php`: دفعة تجاوزت 12 شهراً + `loyalty:daily` → `expire` والرصيد ينقص؛ الاستبدال استهلك الأقدم أولاً؛ تنبيه `loyalty_expiring` مرة واحدة فقط عند التشغيل مرتين؛ الثابت `Σ points = balance − debt`

### Implementation for User Story 3

- [X] T033 [US3] إكمال `loyalty:daily` في `app/Console/Commands/LoyaltyDailyCommand.php`: `LoyaltyLedger::expireDue()`، ثم تنبيه الدفعات التي تنتهي خلال 30 يوماً ولم يُنبَّه لها (`expiry_notified_at`) بإشعار `loyalty_expiring`
- [X] T034 [US3] `app/Http/Controllers/Customer/LoyaltyController.php@index` + مسار `GET /customer/points` (`customer.loyalty.index`)، والصفحة `resources/views/customer/loyalty/index.blade.php` داخل `customer/partials/account-shell.blade.php`: بطاقة الرصيد وقيمته، المعلّقة، تنبيه الانتهاء، نموذج "حوّل إلى كوبون"، السجل مقسّماً (20) بنصوص `LoyaltyTransaction::label()` — RTL وداكن وجوال
- [X] T035 [US3] رابط "نقاطي" مع الرصيد في قائمة الحساب `resources/views/customer/partials/account-shell.blade.php`
- [X] T036 [US3] `app/Http/Controllers/Api/LoyaltyController.php@show` و`@transactions` + `GET /api/v1/loyalty` و`GET /api/v1/loyalty/transactions` بالشكل في [contracts/api.md](contracts/api.md)
- [X] T037 [US3] تفاصيل الطلب للعميل `resources/views/customer/orders/show.blade.php`: سطر "خصم النقاط" و"النقاط المكتسبة" بعد التسليم
- [X] T038 [P] [US3] التطبيق: `flutter:lib/screens/loyalty/my_points_screen.dart` (الرصيد، المعلّق، التنبيه، زر التحويل لكوبون، السجل مع تحميل الصفحات التالية) ومدخل "نقاطي" بالرصيد في `flutter:lib/screens/profile/profile_screen.dart`
- [X] T039 [P] [US3] اختبار التطبيق `flutter:test/loyalty_service_test.dart` لتحليل استجابات `/loyalty` و`/loyalty/transactions`
- [X] T040 [US3] تشغيل T031 وT032 و`flutter test` حتى النجاح

**Checkpoint**: العميل يرى كل شيء بوضوح في المنصتين

---

## Phase 6: User Story 4 - تحفيز العميل أثناء التسوّق (Priority: P3)

**Goal**: "ستكسب X نقاط" في صفحة المنتج والسلة والدفع

**Independent Test**: سلة 26,000 → "ستكسب نقطتين"؛ 8,000 → "أضف 2,000 ل.س لتكسب أول نقطة" (سيناريو 15)

### Tests for User Story 4 ⚠️

- [X] T041 [P] [US4] `tests/Feature/Loyalty/EarnHintTest.php`: العبارتان في صفحة المنتج والسلة، و`will_earn_points`/`points_hint` في `GET /api/v1/products/{product}` و`GET /api/v1/cart`؛ لا شيء عند `loyalty_enabled=false`

### Implementation for User Story 4

- [X] T042 [P] [US4] `resources/views/partials/loyalty-earn-hint.blade.php` (مدخل: المبلغ بالوحدة الصغرى، يستخدم `LoyaltyCalculator::hint`) وإدراجه في `resources/views/products/show.blade.php` و`resources/views/customer/cart/index.blade.php`
- [X] T043 [US4] إضافة `will_earn_points` و`points_hint` في استجابات `app/Http/Controllers/Api/ProductController.php@show` و`app/Http/Controllers/Api/CartController.php@index`
- [X] T044 [P] [US4] التطبيق: عرض العبارة في شاشة المنتج والسلة (`flutter:lib/screens/product/` و`flutter:lib/screens/cart/`)
- [X] T045 [US4] تشغيل T041 حتى النجاح

---

## Phase 7: User Story 5 - إدارة البرنامج (Priority: P3)

**Goal**: ملخص مالي للنقاط، تعديل يدوي بسبب، وإعدادات البرنامج

**Independent Test**: المدير يضيف 10 نقاط بسبب فتظهر في سجل العميل باسمه؛ خصم فوق الرصيد يُرفض (سيناريو 16)

### Tests for User Story 5 ⚠️

- [X] T046 [P] [US5] `tests/Feature/Loyalty/AdminLoyaltyTest.php`: الملخص يعرض إجمالي النقاط القائمة وقيمتها (`Σ balance × point_value`)؛ إضافة 10 بسبب → حركة `admin_add` بـ`admin_id` و`reason`؛ `reason` فارغ → خطأ؛ خصم فوق الرصيد → خطأ؛ تعديل الإعدادات يغيّر الحساب (مثلاً `spend_per_point`)؛ غير المدير → 403

### Implementation for User Story 5

- [X] T047 [US5] في `app/Loyalty/LoyaltyLedger.php`: `adjust(User $customer, string $direction, int $points, string $reason, int $adminId)` (`admin_add` دفعة جديدة / `admin_deduct` لا يتجاوز الرصيد)
- [X] T048 [US5] `app/Http/Controllers/Admin/LoyaltyController.php` (`index`, `updateSettings`, `show`, `adjust`) ومساراته `admin.loyalty.*` في `routes/web.php` كما في [contracts/web.md](contracts/web.md)، مع تحقق: `points` `required|integer|min:1`، `reason` `required|string|max:255`، الإعدادات أعداد صحيحة موجبة
- [X] T049 [US5] الصفحات `resources/views/admin/loyalty/index.blade.php` (الملخص، بحث عميل، نموذج الإعدادات) و`resources/views/admin/loyalty/show.blade.php` (رصيد وسجل العميل ونموذج التعديل)، ورابط في `resources/views/admin/dashboard.blade.php`
- [X] T050 [US5] تشغيل T046 حتى النجاح

---

## Phase 8: Polish & Cross-Cutting Concerns

- [X] T051 [P] إضافة إجابة "كيف تعمل نقاط الولاء؟" إلى `database/seeders/AssistantFaqSeeder.php` ليجيب عنها المساعد الذكي
- [X] T052 فحص بصري بالمتصفح (فاتح/داكن، كمبيوتر/جوال) لصفحات: نقاطي، الدفع، المنتج، السلة، إدارة النقاط — بلقطات شاشة
- [X] T053 اختبار يمر على كل العملاء بعد السيناريوهات ويتحقق من الثابت `balance − debt = Σ points` (SC-004) في `tests/Feature/Loyalty/InvariantTest.php`
- [X] T054 تشغيل كامل: `php artisan test` و`flutter analyze` و`flutter test`، ومراجعة سيناريوهات [quickstart.md](quickstart.md) كلها
- [X] T055 فحص الملفات المجهّزة (`git diff --cached --name-only`) ثم الحفظ في git للموقع والتطبيق

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (1)** → **Foundational (2)** → القصص (3–7) → **Polish (8)**
- T001 (النسخة الاحتياطية والموافقة) شرط قبل T011 (الهجرة على القاعدة الحقيقية)

### User Story Dependencies

- **US1 (P1)**: بعد المرحلة 2 — مستقلة
- **US2 (P1)**: بعد المرحلة 2 — مستقلة (تختبر بمنح رصيد مباشر عبر مساعد الاختبارات)؛ T020 وT014 يعدّلان نفس الدالة فيُنفّذان بالتتابع
- **US3 (P2)**: بعد المرحلة 2؛ نموذج "حوّل إلى كوبون" في صفحتها يعتمد على T024/T026 من US2
- **US4 (P3)**: بعد المرحلة 2 — مستقلة
- **US5 (P3)**: بعد المرحلة 2 — مستقلة

### Within Each User Story

- الاختبارات أولاً وتفشل → الدفتر/الخدمة → المتحكمات والمسارات → الواجهات → التطبيق → تشغيل الاختبارات

### Parallel Opportunities

- T005، T006، T007 معاً (ملفات مختلفة)
- اختبارات كل قصة [P] معاً (T017+T018، T031+T032)
- مهام التطبيق [P] (T028، T038، T039، T044) بالتوازي مع مهام الخادم في نفس القصة بعد تثبيت العقد
- بعد المرحلة 2: US1 وUS4 وUS5 بالتوازي

---

## Parallel Example: User Story 2

```bash
# الاختبارات معاً:
Task: "CheckoutRedemptionTest in tests/Feature/Loyalty/CheckoutRedemptionTest.php"
Task: "CouponConversionTest in tests/Feature/Loyalty/CouponConversionTest.php"

# بعد تثبيت العقد: التطبيق بالتوازي مع الخادم
Task: "loyalty_service.dart + checkout_service.dart in flutter:lib/services/"
Task: "PointsCouponIssuer in app/Loyalty/PointsCouponIssuer.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. المرحلة 1: النسخة الاحتياطية والموافقة
2. المرحلة 2: الجداول والدفتر
3. المرحلة 3: الكسب عند التسليم
4. **توقف وتحقق**: العملاء يكسبون نقاطاً حقيقية من الآن (تتراكم حتى يُطلق الاستبدال)

### Incremental Delivery

1. الأساس + US1 → النقاط تتراكم (MVP)
2. + US2 → الاستبدال بالطريقتين (البرنامج مكتمل القيمة)
3. + US3 → صفحة "نقاطي" والانتهاء
4. + US4 + US5 → التحفيز والإدارة
5. كل خطوة تُختبر وتُحفظ دون كسر ما قبلها

---

## Notes

- [P] = ملفات مختلفة بلا اعتماد
- كل تعديل على `Order::settleForStatusChange()` (T014، T020) يجب أن يبقى تحت قفل `changeStatus()` وآمناً للتكرار
- لا يدخل `points_discount` في `OrderItem::settlementBase()` أبداً (FR-015)
- الرد على المستخدم بالعربية، ولا حفظ لملفات حساسة
