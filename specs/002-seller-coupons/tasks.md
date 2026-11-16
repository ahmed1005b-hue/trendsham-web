---

description: "قائمة مهام تنفيذ كوبونات البائع"
---

# Tasks: كوبونات البائع

**Input**: Design documents from `/specs/002-seller-coupons/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: مُضمّنة. قواعد المشروع تشترط نجاح الاختبارات قبل اعتبار العمل منتهياً، وكل سيناريو في [quickstart.md](quickstart.md) له اختبار. تُكتب الاختبارات أولاً وتُشاهَد وهي تفشل.

**Organization**: المهام مجمّعة حسب قصص المستخدم ليُنفَّذ كل منها ويُختبر وحده.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: يمكن تنفيذها بالتوازي (ملفات مختلفة، بلا اعتماد على مهام غير منتهية)
- **[Story]**: القصة (US1…US6 من spec.md)

## Path Conventions

- **الخادم والموقع**: `c:\xampp\htdocs\trendsham\`
- **التطبيق**: `C:\Users\istanbul\Desktop\trendsham_flutter\` — يُشار إليه بـ`flutter:`
- المبالغ بالوحدة الصغرى (×100) في الخادم، وبالليرة في `/api/v1` ومدخلات النماذج

---

## Phase 1: Setup

- [X] T001 أخذ نسخة احتياطية من قاعدة `trendsham` إلى مجلد scratchpad (`trendsham-before-seller-coupons.sql`) قبل أي هجرة، بعد موافقة صاحب المشروع
- [X] T002 حذف نظام رموز البائع القديم بلا مسارات: `app/Http/Controllers/Seller/CouponController.php` و`resources/views/seller/coupons/*.blade.php`، والتأكد أن `grep -r "Seller\\CouponController"` لا يجد شيئاً
- [X] T003 [P] إنشاء `tests/Feature/SellerCoupons/SellerCouponTestHelpers.php` (trait): `seller(status='approved', verified=true)`، `customer()`، `product(seller, price)`، `campaign(seller, attrs)`، `cartLine(user, product, qty)`، `deliver(order)` — على غرار `tests/Feature/Loyalty/LoyaltyTestHelpers.php`

---

## Phase 2: Foundational (يمنع كل القصص حتى ينتهي)

- [X] T004 هجرة `database/migrations/2026_11_18_000000_add_seller_coupon_columns.php`: توسيع `coupon_campaigns.status` إلى enum `pending_review, active, paused, rejected, suspended` (الافتراضي `active`)؛ أعمدة `auto_followers` boolean default false، `buy_product_id` و`gift_product_id` FK products nullable nullOnDelete، `review_note` string(500) nullable، `reviewed_by` FK users nullable nullOnDelete، `reviewed_at` timestamp nullable، `followers_issued_at` timestamp nullable؛ و`order_items.is_gift` boolean default false. `down()` يعكس كل ذلك
- [X] T005 تحديث `app/Coupons/Models/CouponCampaign.php`: الأعمدة في `$fillable` و`casts`؛ ثوابت `STATUSES` بأسماء عربية؛ `isLocked(): bool` (`issued_count > 0`)؛ `isSoldOut(): bool` (`total_limit !== null && issued_count >= total_limit`)؛ `scopeSeller($q, $sellerId)`؛ علاقات `buyProduct()`, `giftProduct()`, `reviewer()`؛ `valueLabel()` لنوع gift = "هدية: {اسم المنتج}"
- [X] T006 [P] إضافة `is_gift` إلى `$fillable` و`casts` في `app/Models/OrderItem.php`
- [X] T007 تشغيل الهجرة على `trendsham_testing` وعلى `trendsham` (بعد T001) والتأكد أن الاختبارات الحالية كلها ما زالت تنجح (`php artisan test`)

**Checkpoint**: الأساس جاهز.

---

## Phase 3: User Story 1 - البائع ينشئ كوبوناً لمتجره (P1) 🎯 MVP

**Goal**: زر "إضافة كوبون" في لوحة البائع، ونموذج ينشئ كوبون نسبة/مبلغ/شحن مجاني بقواعد البائع.

**Independent Test**: بائع ينشئ كوبوناً ويراه في قائمته؛ القيم الخاطئة تُرفض.

### Tests

- [X] T008 [P] [US1] `tests/Feature/SellerCoupons/CreateCouponTest.php`: إنشاء 10% حد 20,000 حد أدنى 50,000 لمدة 14 يوماً و100 كوبون ← حملة `funder=seller, status=active` وتظهر في `seller.coupons.index` بنص "جُمع 0 من 100"؛ رفض النسبة 0 و71، المبلغ الثابت 0، `ends_at` قبل البداية أو بعد سنة، غياب `total_limit` أو `max_discount` للنسبة؛ رفض منتج من متجر آخر في `product_ids`؛ متجر `suspended` أو غير موثّق يرى سبب المنع ولا يُنشئ (403/redirect برسالة)؛ بائع لا يرى/يعدّل حملة متجر آخر (404)؛ صفحة القائمة تحوي زر "إضافة كوبون"
- [X] T009 [P] [US1] `tests/Unit/SellerCoupons/SellerCampaignWriterTest.php`: تحويل الليرة ×100؛ النسبة 51 ← `pending_review`، 50 ← `active`؛ `scope=products` ينشئ targets `product`

### Implementation

- [X] T010 [US1] `app/Coupons/SellerCampaignWriter.php`: `rules(SellerProfile)` حسب data-model (percentage 1..70 مع `max_discount` إلزامي، fixed ≥ 1، free_shipping، gift لاحقاً في US6؛ scope all|products مع `product_ids` من `products.vendor_id = seller`؛ audience collectable|followers؛ `auto_followers` اختياري؛ `ends_at` إلزامي بعد الآن وبعد `starts_at` و≤ سنة؛ `total_limit` 1..100000 إلزامي؛ `per_customer_limit` 1..5؛ `valid_days` 1..365 اختياري؛ `min_order_amount` ≥ 0)؛ `messages()` عربية؛ `create(array, SellerProfile, User): CouponCampaign` داخل معاملة، الحالة `pending_review` إذا percentage و`value > 50` وإلا `active`؛ `update(campaign, array)` يرفض تغيير غير `ends_at` (تمديد فقط) و`total_limit` (زيادة فقط) إذا `isLocked()`؛ `previewAmounts(type, value, maxDiscount, sampleTotal, commissionRate): array` للمعاينة
- [X] T011 [US1] `app/Http/Controllers/Seller/CouponCampaignController.php`: `index` (حملات المتجر مع العدادات)، `create` (يرفض المتجر غير الموثّق `isIdentityVerified()` أو الموقوف بصفحة سبب)، `store`، `edit`، `update`، `toggle` (active⇄paused فقط)، `destroy` (فقط إن `issued_count = 0`)؛ كل الإجراءات تتحقق أن `campaign.seller_profile_id` = متجر البائع وإلا 404
- [X] T012 [US1] المسارات في `routes/web.php` ضمن مجموعة `seller.` (السطر ~279): `seller.coupons.{index,create,store,show,edit,update,toggle,destroy}` كما في [contracts/web.md](contracts/web.md)
- [X] T013 [P] [US1] `resources/views/seller/coupons/index.blade.php`: جدول/بطاقات (العنوان، القيمة، الحالة بلون، "جُمع X من Y"، استُخدم، النهاية، أزرار عرض/تعديل/إيقاف/حذف)، زر "إضافة كوبون" بارز، حالة فارغة تشرح الفكرة؛ RTL ووضع داكن
- [X] T014 [P] [US1] `resources/views/seller/coupons/create.blade.php` و`edit.blade.php` (partial مشترك `_form.blade.php`): النوع (بطاقات اختيار)، القيمة، الحد الأعلى، النطاق مع اختيار منتجات المتجر، التوزيع، الشروط، معاينة حيّة بـJS "على طلب بقيمة X: يدفع العميل Y ويصلك Z"، تنبيه "سيحتاج موافقة الإدارة" عند نسبة > 50؛ في التعديل بعد الجمع الحقول المقفلة معطّلة مع شرح
- [X] T015 [US1] رابط "الكوبونات" في قائمة لوحة البائع: `resources/views/partials/header.blade.php` و`resources/views/seller/dashboard.blade.php` بجانب "عروض اليوم"
- [X] T016 [US1] تشغيل T008–T009 حتى النجاح

**Checkpoint**: البائع ينشئ ويدير كوبوناته.

---

## Phase 4: User Story 2 - العميل يجمع كوبون المتجر ويستخدمه (P1)

**Goal**: شريط "كوبونات المتجر" وزر "احفظ" في الموقع والتطبيق؛ الاستخدام يُخصم من البائع.

**Independent Test**: عميل يحفظ من صفحة المتجر، يشتري، والتسوية على البائع.

### Tests

- [X] T017 [P] [US2] `tests/Feature/SellerCoupons/CollectCouponTest.php`: "احفظ" من الويب (`coupons.collect`) والتطبيق (`POST /api/v1/coupons/{id}/collect` → 201)؛ ثانية ← 409 بلا نسخة جديدة؛ `total_limit=1` بعد جمع عميل ← عميل آخر 410 "نفدت الكوبونات" وصفحة المتجر تعرض "نفدت"؛ حملة paused/pending_review/suspended أو متجر موقوف ← 404 ولا تظهر في الصفحة؛ زائر ← تحويل لتسجيل الدخول؛ `GET /api/v1/stores/{id}` يحوي `coupons[].state` و`GET /api/v1/products/{slug}` يحوي `data.store_coupons`؛ محاكاة التزامن: استدعاءان متتاليان لـ`collect` بنفس الحالة لا يتجاوزان `per_customer_limit` ولا `total_limit`
- [X] T018 [P] [US2] `tests/Feature/SellerCoupons/SellerCouponMoneyTest.php`: منتج 100,000 عمولة 10% وكوبون بائع fixed 20,000 ← الطلب `total` = 80,000 + الشحن؛ بعد التسليم محفظة البائع +72,000 و`commission_amount` 8,000؛ الإلغاء بعد التسليم يعكس الأرباح ويعيد الكوبون `available`؛ سلة من متجرين ← الخصم على بنود متجر البائع فقط و`discount_funder=seller`؛ نقاط الولاء مع كوبون بائع: النقاط لا تمس أرباح البائع؛ شحن مجاني من البائع مع سلة من متجرين ← غير صالح بالسبب "يعمل عندما تكون كل السلة من متجر X"؛ سلة من متجره فقط ← الشحن 0، وبعد التسليم المحفظة −15,000 (`coupon_shipping`) والإلغاء يعيدها، والتسليم المكرر لا يخصم مرتين

### Implementation

- [X] T019 [US2] `app/Coupons/CouponIssuer.php`: `collect(CouponCampaign, User): CustomerCoupon` يرمي `CouponCollectException` بكود (`already`, `sold_out`, `unavailable`)؛ يتحقق أن `funder=seller` و`audience=collectable`؛ داخل قفل `issue()` يُضاف شرط `isSoldOut()` لكوبونات البائع (research R4)؛ المصدر `collect`
- [X] T020 [US2] `app/Coupons/CouponEligibility.php`: لكوبون بائع `free_shipping` يرفض إذا وُجد في السلة بند `vendor_id` مختلف بالسبب "يعمل عندما تكون كل السلة من متجر {اسم}"؛ حالة الحملة `paused` ← "أوقفه المتجر مؤقتاً"
- [X] T021 [US2] `app/Services/LedgerService.php`: `couponShipping(sellerUserId, amount, desc, redemptionId)` و`couponShippingReversal(...)` بمصدر `coupon_redemption`؛ و`app/Models/Order.php` `settleForStatusChange`: عند أول تسليم، لكل `coupon_redemptions` مطبَّق `funder=seller` و`shipping_discount > 0` خصم المبلغ من محفظة البائع (`debit`, نوع `coupon_shipping`) مرة واحدة، وعند الإلغاء بعد التسليم إعادته (`credit`) مرة واحدة — في خدمة صغيرة `app/Coupons/SellerShippingCharge.php` (`charge(Order)`, `refund(Order)`)
- [X] T022 [US2] `app/Coupons/StoreCoupons.php`: `forStore(SellerProfile, ?User): Collection` و`forProduct(Product, ?User)` — الحملات `running()` بـ`funder=seller` و`audience=collectable` غير المنتهية للمتجر (حتى 10، eager load targets) مع `state` (collectable|collected|sold_out|login_required) و`remaining`؛ و`toArray()` بالليرة حسب [contracts/api.md](contracts/api.md)
- [X] T023 [US2] `app/Http/Controllers/CouponCollectController.php` (ويب: back مع رسالة) و`app/Http/Controllers/Api/CouponCollectController.php` (201/409/410/404)؛ المسارات: `routes/web.php` `POST /coupons/{campaign}/collect` اسم `coupons.collect` (`auth, role:customer, throttle:30,1`)، و`routes/api.php` `POST /coupons/{campaign}/collect` (sanctum, role customer, throttle 30/1)
- [X] T024 [P] [US2] `resources/views/partials/store-coupons.blade.php`: بطاقات (القيمة، الشرط، النهاية، المتبقي) وزر "احفظ"/"محفوظ ✓"/"نفدت"، تمرير أفقي على الجوال، وضع داكن؛ إدراجه في `resources/views/stores/show.blade.php` (تمرير `$storeCoupons` من `PublicStoreController@show`) وفي `resources/views/products/show.blade.php` (من `ProductController@show` عبر `StoreCoupons::forProduct`)
- [X] T025 [US2] API: `app/Http/Controllers/Api/PublicStoreController.php@show` يضيف `coupons`، و`app/Http/Controllers/Api/ProductController.php@show` يضيف `data.store_coupons` (العميل من توكن sanctum الاختياري)
- [X] T026 [P] [US2] flutter: `lib/services/store_service.dart` (أو ملف `lib/services/store_coupon_service.dart`): نموذج `StoreCoupon.fromJson` و`collect(int campaignId)` يعيد رسالة الخادم ويتعامل مع 409/410
- [X] T027 [US2] flutter: `lib/widgets/store_coupons_strip.dart` (بطاقات أفقية + زر احفظ مع حالة تحميل وSnackBar) وإدراجه في `lib/screens/store/store_screen.dart` و`lib/screens/product/product_detail_screen.dart`؛ تحديث نموذجي المتجر والمنتج لقراءة `coupons` / `store_coupons`
- [X] T028 [P] [US2] flutter: `test/store_coupons_test.dart` — تحليل `StoreCoupon.fromJson` بكل حالات `state`
- [X] T029 [US2] تشغيل T017–T018 و`flutter analyze lib && flutter test` حتى النجاح

**Checkpoint**: MVP كامل: بائع ينشئ، عميل يجمع ويستخدم، المال صحيح.

---

## Phase 5: User Story 3 - كوبون تلقائي لمتابعي المتجر (P2)

**Goal**: يصل الكوبون لكل المتابعين عند التفعيل وللمتابعين الجدد، مرة واحدة.

**Independent Test**: 3 متابعين ← 3 نسخ؛ متابع رابع ← نسخة؛ إعادة المتابعة لا تكرر.

### Tests

- [X] T030 [P] [US3] `tests/Feature/SellerCoupons/FollowerCouponTest.php`: كوبون `audience=followers` لمتجر له 3 متابعين ← 3 نسخ بمصدر `followers` و3 إشعارات `coupon_received` و`followers_issued_at` مضبوط؛ متابعة جديدة (ويب و`POST /api/v1/stores/{id}/follow` مع `coupons_received: 1`) ← نسخة؛ إلغاء ثم إعادة متابعة ← لا نسخة ثانية؛ `total_limit=2` و3 متابعين ← نسختان فقط؛ صفحة الإنشاء تعرض "سيصل إلى 3 متابعين" و"العدد الإجمالي أقل من المتابعين"؛ كوبون `pending_review` لا يُرسل حتى الموافقة؛ `collectable` مع `auto_followers` يُرسل للمتابعين أيضاً

### Implementation

- [X] T031 [US3] `app/Coupons/FollowerIssuer.php`: `issueToAll(CouponCampaign): int` (يمر على `store_followers` بـ`chunkById(200)`، يتوقف عند `isSoldOut()`، يضبط `followers_issued_at` مرة واحدة)؛ `issueForNewFollower(SellerProfile, User): int` لكل حملة `running()` للمتجر بـ`audience=followers` أو `auto_followers=1`، ويتخطى العميل إن كانت لديه أي نسخة غير `revoked` من الحملة؛ `activate(CouponCampaign)` يستدعي `dispatch(fn () => issueToAll(...))->afterResponse()` إن لم يُضبط `followers_issued_at`
- [X] T032 [US3] ربط التفعيل: `SellerCampaignWriter::create` يستدعي `FollowerIssuer::activate` إذا الحالة `active` والتوزيع يشمل المتابعين؛ و`app/Http/Controllers/StoreFollowController.php` و`app/Http/Controllers/Api/StoreFollowController.php` يستدعيان `issueForNewFollower` عند المتابعة (API يعيد `coupons_received`)
- [X] T033 [US3] نموذج الإنشاء (`_form.blade.php`) يعرض عدد المتابعين وتنبيه إن كان `total_limit` أقل منه
- [X] T034 [US3] تشغيل T030 حتى النجاح

---

## Phase 6: User Story 4 - إحصائيات الكوبون وإيقافه (P2)

**Goal**: البائع يرى أرقاماً صحيحة، يوقف ويعيد التشغيل، ولا يغيّر القيمة بعد الجمع.

**Independent Test**: كوبون في طلبين مُسلَّمين ← الإحصائيات صحيحة؛ الإيقاف يخفيه.

### Tests

- [X] T035 [P] [US4] `tests/Feature/SellerCoupons/StatsAndPauseTest.php`: طلبان مُسلَّمان وثالث ملغى ← `seller.coupons.show` تعرض استخدام 2، مجموع الخصم، المبيعات (بنود المتجر في الطلبين فقط)؛ toggle ← paused: يختفي من صفحة المتجر، `CouponEligibility` يعطي "أوقفه المتجر مؤقتاً"، محفظة العميل تعرض النص، طلب قائم قيد التجهيز لا يتغير خصمه؛ toggle ثانية ← active؛ بعد الجمع: تحديث `value`/`type`/`scope` يُرفض، تمديد `ends_at` وزيادة `total_limit` مقبولان، تقليل `total_limit` مرفوض، الحذف مرفوض برسالة تقترح الإيقاف؛ قبل الجمع الحذف مقبول

### Implementation

- [X] T036 [US4] `app/Coupons/SellerCouponStats.php`: `for(CouponCampaign): array` (`issued`, `redeemed` من redemptions `applied`، `discount_total` = مجموع `discount_amount + shipping_discount`، `sales_total` = مجموع `order_items.subtotal` لبنود المتجر في طلبات غير ملغاة) باستعلامين مجمّعين
- [X] T037 [US4] `CouponCampaignController@show` + `resources/views/seller/coupons/show.blade.php` (بطاقات الأرقام، آخر الطلبات التي استخدمته، أزرار الإيقاف/التمديد)
- [X] T038 [US4] `app/Coupons/CouponPresenter.php`: حالة الحملة للعميل (`paused` ← "أوقفه المتجر مؤقتاً"، `suspended` ← "لم يعد متاحاً") في الويب و`/api/v1/coupons` (`campaign_state`)
- [X] T039 [US4] تشغيل T035 حتى النجاح

---

## Phase 7: User Story 5 - إشراف المدير على كوبونات البائعين (P2)

**Goal**: قائمة للمدير، موافقة/رفض للنسبة فوق 50%، إيقاف بسبب.

**Independent Test**: كوبون 60% ← ينتظر؛ موافقة ← فعّال ويُرسل للمتابعين؛ إيقاف ← البائع لا يعيد تشغيله.

### Tests

- [X] T040 [P] [US5] `tests/Feature/SellerCoupons/AdminReviewTest.php`: `admin.seller-coupons.index` تعرض كوبونات كل المتاجر مع تصفية `store` و`status` وعدد "بانتظار الموافقة"؛ كوبون 60% لا يظهر في صفحة المتجر ولا يُجمع؛ approve ← `active`، `reviewed_by/at` مضبوطان، إشعار للبائع، إرسال للمتابعين؛ reject بلا سبب ← خطأ تحقق، بسبب ← `rejected` و`review_note` وإشعار؛ suspend ← `suspended`، البائع يحاول toggle ← مرفوض ويرى السبب في قائمته؛ غير المدير ← 403؛ كوبون 30% يعمل فوراً بلا موافقة

### Implementation

- [X] T041 [US5] `app/Coupons/CampaignReview.php`: `approve(campaign, admin)` (فقط من `pending_review`، ثم `FollowerIssuer::activate`)، `reject(campaign, admin, reason)`، `suspend(campaign, admin, reason)` (من active/paused/pending_review)؛ كل منها يضبط `reviewed_by/reviewed_at/review_note` ويرسل `AppNotification` لمستخدم البائع برابط `seller.coupons.show`
- [X] T042 [US5] `app/Http/Controllers/Admin/SellerCouponController.php` (`index`, `approve`, `reject`, `suspend` مع `reason` required|string|max:500) والمسارات `admin.seller-coupons.*` في `routes/web.php` بجانب `coupon-campaigns`
- [X] T043 [P] [US5] `resources/views/admin/seller-coupons/index.blade.php`: تبويبات الحالة، تصفية المتجر، أزرار موافقة/رفض/إيقاف مع نافذة السبب؛ ورابط مع عدد المنتظرة في `resources/views/admin/dashboard.blade.php`
- [X] T044 [US5] تشغيل T040 حتى النجاح

---

## Phase 8: User Story 6 - كوبون منتج هدية (P3)

**Goal**: "اشترِ X واحصل على Y هدية" من منتجات المتجر.

**Independent Test**: المنتج الشرط في السلة ← بند هدية بسعر 0 والمخزون −1؛ الإلغاء يعيده.

### Tests

- [X] T045 [P] [US6] `tests/Feature/SellerCoupons/GiftCouponTest.php`: إنشاء كوبون gift بمنتجين من المتجر (رفض منتج من متجر آخر أو هدية لها ألوان)؛ السلة بالمنتج الشرط ← الكوبون صالح و`saving()` = سعر الهدية؛ بدون المنتج الشرط ← السبب "اشترِ {المنتج} لتحصل على الهدية"؛ الهدية مخزونها 0 ← "الهدية نفدت"؛ الدفع في الويب و`POST /api/v1/checkout` ← بند `is_gift=1, unit_price=0, subtotal=0` والمخزون −1، صفحة الطلب تعرض "هدية"؛ التسليم ← البند لا يضيف أرباحاً ولا عمولة؛ الإلغاء ← المخزون يعود

### Implementation

- [X] T046 [US6] `SellerCampaignWriter`: قواعد gift (`buy_product_id`, `gift_product_id` من منتجات المتجر، الهدية بلا variants، `value=0`)؛ و`app/Coupons/EligibilityResult.php`: `giftProductId`, `giftValue`، و`saving()` يشمل `giftValue`
- [X] T047 [US6] `CouponEligibility::check` لنوع gift: يتحقق من وجود `buy_product_id` في السلة وأن الهدية `isLive()` ومخزونها ≥ 1، ويعيد النتيجة بالقيمة دون خصم
- [X] T048 [US6] `app/Coupons/GiftLine.php`: `add(Order, CouponCampaign): ?OrderItem` يقفل منتج الهدية، يرمي `DomainException('coupon:الهدية نفدت')` إن نفد، ينشئ البند ويخصم المخزون (`adjustStock(-1, 'sale', ...)`)؛ استدعاؤه بعد إنشاء البنود في `app/Http/Controllers/Customer/CheckoutController.php` و`app/Http/Controllers/Api/CheckoutController.php`
- [X] T049 [P] [US6] عرض علامة "هدية" لبند `is_gift` في `resources/views/customer/orders/show.blade.php` والفاتورة وصفحة طلب البائع، و`is_gift` في `OrderItemResource`؛ وflutter: شارة "هدية" في عرض بنود الطلب
- [X] T050 [US6] خيار "منتج هدية" في `_form.blade.php` مع اختيار المنتجين من المتجر
- [X] T051 [US6] تشغيل T045 حتى النجاح

---

## Phase 9: Polish & Cross-Cutting

- [X] T052 [P] `tests/Feature/SellerCoupons/StoreSuspensionTest.php`: إيقاف المتجر ← كل كوبوناته تختفي من الصفحات، غير صالحة في السلة، ولا تُجمع؛ منتج مشمول يُحذف ← يسقط من النطاق
- [X] T053 [P] سؤال جديد في `database/seeders/AssistantFaqSeeder.php` "كيف يضيف البائع كوبوناً؟" وتشغيله على القاعدة الحقيقية
- [X] T054 فحص بصري (browser-automation) لصفحات البائع والمتجر والمنتج والمدير، فاتح وداكن وعرض الجوال، والتأكد أن لا كود Blade يظهر في أي صفحة
- [X] T055 تشغيل كل الاختبارات `php artisan test` و`flutter analyze lib && flutter test`، ومطابقة سيناريوهات [quickstart.md](quickstart.md) الـ17
- [X] T056 حفظ في git (الموقع والتطبيق منفصلين) بعد فحص `git diff --cached --name-only`، مع استبعاد `lib/screens/home/home_tab.dart` و`.artifacts/` في التطبيق

---

## Dependencies & Execution Order

- **Setup (T001–T003)** ← **Foundational (T004–T007)** ← كل القصص.
- **US1** أولاً (الإنشاء). **US2** تعتمد على US1 لإنشاء الحملات في واجهة البائع، لكن اختباراتها تنشئ الحملات مباشرة فيمكن بدؤها بعد Foundational.
- **US3** تعتمد على `SellerCampaignWriter` (US1).
- **US4** تعتمد على US1 (الصفحات) وUS2 (الاستخدام في الإحصائيات).
- **US5** تعتمد على US1؛ تستدعي `FollowerIssuer` من US3 (إن لم تُنفَّذ US3 بعد، `approve` يكتفي بتغيير الحالة).
- **US6** تعتمد على US1 وUS2 (الدفع).
- **Polish** بعد كل القصص.

## Parallel Opportunities

- T003 مع T004.
- اختبارات كل قصة [P] تُكتب معاً قبل التنفيذ (T008/T009، T017/T018).
- في US2: T024 (Blade) وT026/T028 (Flutter) بالتوازي بعد T022.
- US4 وUS5 يمكن تنفيذهما بالتوازي بعد US2 (ملفات مختلفة).

### Parallel Example: User Story 2

```text
T017 CollectCouponTest.php        | T018 SellerCouponMoneyTest.php
بعد T022: T024 partial Blade      | T026 flutter store_service | T028 flutter test
```

## Implementation Strategy

1. **MVP** = Phase 1 + 2 + US1 + US2: البائع ينشئ، العميل يجمع ويستخدم، والمال صحيح. هذا يجيب على سؤال "أين زر إضافة كوبون".
2. ثم US3 (المتابعون) وUS5 (الموافقة فوق 50% — مطلوبة قبل الإطلاق للبائعين لأن الإنشاء يضع `pending_review` بلا واجهة مراجعة).
3. ثم US4 (الإحصائيات) ثم US6 (الهدية).
4. كل قصة: الاختبارات تفشل أولاً ← التنفيذ ← النجاح ← حفظ.
