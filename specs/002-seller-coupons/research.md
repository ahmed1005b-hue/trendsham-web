# Research: كوبونات البائع

كل قرار هنا مبني على قراءة الكود الحالي (`app/Coupons/*`, `OrderItem::settlementBase()`, `Order::settleForStatusChange()`, الدفع في الموقع والتطبيق).

## R1 — إعادة استخدام المحفظة الحالية

- **Decision**: لا جداول جديدة للكوبونات؛ نستعمل `coupon_campaigns` (funder=seller, seller_profile_id) و`coupon_campaign_targets` (target_type=product) و`customer_coupons` و`coupon_redemptions`.
- **Rationale**: المرحلة الأولى صُممت لذلك: `CouponEligibility::covers()` يقصر كوبون البائع على منتجات متجره، `settlementBase()` يخصم الخصم حين `discount_funder=seller`، `isRunning()` يُسقط كوبونات المتجر غير المعتمد، `CouponRedeemer::reverse()` يعيد الكوبون عند الإلغاء.
- **Alternatives**: جدول مستقل لكوبونات البائع — رُفض لأنه يكرر منطق الاختيار والتسوية.

## R2 — الحالات والموافقة

- **Decision**: توسيع `status` إلى: `pending_review`, `active`, `paused` (البائع), `rejected`, `suspended` (المدير). أعمدة: `review_note`, `reviewed_by`, `reviewed_at`. نسبة > 50 ← `pending_review` عند الإنشاء. البائع يبدّل فقط بين `active` و`paused`.
- **Rationale**: يفصل إيقاف البائع (قابل للعكس منه) عن إيقاف المدير (غير قابل للعكس من البائع) كما في FR-020.
- **Alternatives**: عمود منفصل `admin_suspended` — رُفض: حالة واحدة أوضح للاستعلامات (`scopeRunning` يكفيه `status = active`).

## R3 — حدود القيم

- **Decision**: نسبة 1..70؛ `max_discount` إلزامي للنسبة؛ مبلغ ثابت ≥ 1 ل.س؛ `per_customer_limit` 1..5 (افتراضي 1)؛ `total_limit` إلزامي للبائع (1..100,000)؛ `ends_at` إلزامي (≤ سنة).
- **Rationale**: كوبون بائع بلا عدد أو نهاية مفتوح التكلفة على البائع؛ الحد الأعلى للنسبة يحمي من خطأ إدخال.

## R4 — ماذا يعني "العدد الإجمالي" لكوبون البائع

- **Decision**: لكوبونات البائع يحدّ `total_limit` عدد **النسخ المُصدرة** (`issued_count`)، فيظهر "نفدت" عند بلوغه. فحص الاستخدام الحالي (`redeemed_count < total_limit`) يبقى كما هو ولا يتعارض (الاستخدام ≤ الإصدار).
- **Rationale**: البائع يفكر "100 كوبون" = 100 عميل يحصلون عليه؛ يضمن تكلفة قصوى معروفة. كوبونات المنصة لا تتغير.
- **Alternatives**: سقف على الاستخدام فقط — يسمح بجمع غير محدود ثم رسائل "نفد" عند الدفع.

## R5 — الجمع ("احفظ")

- **Decision**: `CouponIssuer::collect(campaign, user)` يتحقق أن audience يشمل الجمع، ثم يستدعي `issue(..., 'collect')` الحالي (قفل صف الحملة + عدم التكرار) ويضيف فحص `issued_count < total_limit` داخل القفل.
- **Rationale**: نفس القفل الذي يحمي كوبونات المدير؛ يحقق SC-003.
- `audience` يأخذ `collectable` أو `followers`؛ "الاثنان" يُخزَّن كـ `collectable` مع عمود `auto_followers` (bool).

## R6 — المتابعون

- **Decision**: `FollowerIssuer::issueToAll(campaign)` يمر على المتابعين بدفعات 200 (`chunkById`) ويستدعي `issue(..., 'followers')`؛ يُشغَّل عند أول تفعيل (الإنشاء الفعّال أو موافقة المدير) عبر `dispatch(fn)->afterResponse()`. وعند متابعة جديدة: `issueForNewFollower(store, user)` لكل حملة متابعين فعّالة.
- **عدم التكرار عند إعادة المتابعة**: لا يُصدر إذا وُجدت لدى العميل أي نسخة من الحملة (بأي حالة غير revoked).
- **Rationale**: `QUEUE_CONNECTION=database` بلا عامل مضمون محلياً؛ `afterResponse` لا يحتاج عاملاً ولا يؤخر البائع.
- **Alternatives**: Job في الطابور — يحتاج تشغيل `queue:work` دائماً؛ يمكن الترقية لاحقاً.

## R7 — الشحن المجاني من البائع

- **Decision**: صالح فقط إذا كانت **كل** بنود السلة من متجر البائع (وإلا السبب "يعمل عندما تكون كل السلة من متجر X"). أجرة الشحن تذهب للمنصة حالياً، فعند التسليم يُخصم `shipping_discount` من محفظة البائع بقيد `coupon_shipping` (مرة واحدة، مصدر `coupon_redemption`+id)، ويُعكس عند الإلغاء بعد التسليم.
- **Rationale**: المبدأ "من أنشأ الكوبون يتحمّل خصمه"؛ الشحن رسم على مستوى الطلب لا يُقسم بين متاجر.

## R8 — منتج الهدية

- **Decision**: أعمدة `buy_product_id`, `gift_product_id` على الحملة؛ كلاهما من متجر البائع، والهدية بلا ألوان/مقاسات (منتج بسيط) في هذه المرحلة. الأهلية: المنتج الشرط في السلة + الهدية حيّة ومخزونها ≥ 1. `EligibilityResult` يحمل `giftProductId` و`giftValue` (سعر الهدية) لتدخل في `saving()` واختيار الأفضل. عند الدفع `GiftLine::add(order, campaign)` يقفل منتج الهدية، يتحقق من المخزون، ينشئ بنداً `unit_price=0, subtotal=0, is_gift=1` ويخصم المخزون. الإلغاء يعيد المخزون عبر `restockItems()` الحالي.
- **Rationale**: بند سعره 0 ← `settlementBase()=0` ← لا ربح ولا عمولة: البائع يتحمل الهدية تلقائياً.
- **Alternatives**: هدية بألوان — مؤجلة (تعقيد اختيار اللون).

## R9 — التعديل بعد الجمع

- **Decision**: `isLocked()` = `issued_count > 0`. بعده يُسمح فقط بـ: تمديد `ends_at`، زيادة `total_limit`، الإيقاف/التشغيل. الحذف مسموح فقط قبل أي جمع.
- **Rationale**: FR-019 — لا يتغير ما حفظه العميل.

## R10 — نطاق التطبيق

- **Decision**: إنشاء وإدارة كوبونات البائع في لوحة البائع على **الموقع** في هذه الميزة. التطبيق: شريط كوبونات المتجر وزر "احفظ" في شاشة المتجر وتفاصيل المنتج، والمحفظة الحالية تعرضها.
- **Rationale**: المواصفات (Assumptions) تحدد ذلك؛ شاشات البائع في التطبيق تُضاف لاحقاً إن طُلبت.

## R11 — الكود القديم

- **Decision**: حذف `app/Http/Controllers/Seller/CouponController.php` و`resources/views/seller/coupons/*` (نظام الرموز، بلا مسارات منذ e5c9b78) واستبدالها.

## R12 — الإحصائيات

- **Decision**: `SellerCouponStats::for(campaign)`: `issued_count`، `redeemed_count` (applied فقط)، مجموع `discount_amount + shipping_discount` من `coupon_redemptions` المطبَّقة، ومبيعات = مجموع `subtotal` لبنود متجر البائع في تلك الطلبات غير الملغاة.
