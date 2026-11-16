---

description: "قائمة مهام تنفيذ الإشعارات المنبثقة على الهاتف"
---

# Tasks: الإشعارات المنبثقة على الهاتف

**Input**: Design documents from `/specs/003-push-notifications/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: مُضمّنة. قواعد المشروع تشترط نجاح الاختبارات قبل الانتهاء، وكل سيناريو في [quickstart.md](quickstart.md) له اختبار. Firebase مزيّف في الاختبارات (`Http::fake`)؛ لا إرسال حقيقي.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: يمكن تنفيذها بالتوازي (ملفات مختلفة، بلا اعتماد على مهام غير منتهية)
- **[Story]**: القصة (US1…US5 من spec.md)

## Path Conventions

- **الخادم والموقع**: `c:\xampp\htdocs\trendsham\`
- **التطبيق**: `C:\Users\istanbul\Desktop\trendsham_flutter\` — يُشار إليه بـ`flutter:`
- مفتاح Firebase: `storage/app/private/firebase-credentials.json` (موجود، خارج git — لا يُقرأ محتواه ولا يُطبع)

---

## Phase 1: Setup

- [X] T001 نسخة احتياطية من قاعدة `trendsham` إلى scratchpad (`trendsham-before-push.sql`) بعد موافقة صاحب المشروع على الهجرة
- [X] T002 إضافة `FIREBASE_CREDENTIALS=storage/app/private/firebase-credentials.json` و`FIREBASE_PROJECT_ID=trendsham-6900c` إلى `.env` و`.env.example` (بلا قيم سرية)، وقسم `firebase` في `config/services.php` (`credentials`, `project_id`)؛ وفي `phpunit.xml` `FIREBASE_CREDENTIALS` يشير لملف اختبار وهمي `tests/Fixtures/firebase-test-credentials.json` (مفتاح RSA مولَّد للاختبار فقط)
- [X] T003 [P] `tests/Feature/Push/PushTestHelpers.php` (trait): `customer()`, `seller()`, `device(user, token)`, `fakeFcm(array $responses = [])` يزيّف `oauth2.googleapis.com/token` و`fcm.googleapis.com/*`، `sentPushes(): Collection` من الطلبات المسجلة، `runAfterResponse()` لتنفيذ ما أُجّل بعد الرد في الاختبار
- [X] T004 [P] flutter: إضافة `firebase_core`, `firebase_messaging`, `app_settings` إلى `pubspec.yaml`؛ إضافة إضافة `com.google.gms.google-services` في `android/settings.gradle.kts` و`android/app/build.gradle.kts`؛ التأكد أن `android/app/google-services.json` موجود؛ `flutter pub get` و`flutter analyze lib` نظيف

---

## Phase 2: Foundational (يمنع كل القصص)

- [X] T005 هجرة `database/migrations/2026_11_19_000000_create_push_notification_tables.php` حسب [data-model.md](data-model.md): جدول `push_devices` (`token` string(255) **unique**، `platform` enum `android, ios`، `app_version` string(20) nullable، `last_seen_at`)؛ جدول `push_broadcasts` (`title` string(60)، `body` string(180)، `link` string(255) nullable، `created_by` FK nullOnDelete، `scheduled_for`، `sent_at` nullable، `recipients_count`, `pushed_count` unsigned int default 0)؛ أعمدة `users.notification_prefs` json nullable و`users.offers_prompted_at` timestamp nullable؛ أعمدة `notifications.group` string(30) nullable، `target` json nullable، `push_status` enum `pending, sent, skipped_disabled, skipped_quiet, skipped_limit, no_device, not_configured, failed` nullable، `pushed_at` nullable، `broadcast_id` FK nullable nullOnDelete، وفهرس `(user_id, group, pushed_at)`
- [X] T006 [P] النماذج: `app/Models/PushDevice.php`، `app/Models/PushBroadcast.php`؛ علاقات `User::pushDevices()`؛ `notification_prefs` json cast في `User`؛ الأعمدة الجديدة في `$fillable`/casts في `app/Models/AppNotification.php`
- [X] T007 [P] `app/Notifications/Push/NotificationGroups.php`: `MAP` نوع←مجموعة حسب الدور كما في research R3؛ `CRITICAL`؛ `groupsFor(string $role): array` بالمفتاح والاسم العربي والوصف (contracts/api.md)؛ `groupOf(string $type, string $role): string` (غير المعروف ← `general`)؛ `isCritical(string $type): bool`
- [X] T008 [P] `app/Notifications/Push/PushTarget.php`: `fromLink(?string $link): ?array` بمطابقة المسارات في research R4 (يتجاهل البادئة `http(s)://host/…/public`)؛ و`tests/Unit/Push/PushTargetTest.php` لكل مسار + رابط غير معروف ← `notifications`
- [X] T009 [P] `app/Notifications/Push/FcmClient.php`: `isConfigured(): bool`؛ `accessToken()` (JWT RS256 بـ`openssl_sign`، تبديل في `oauth2.googleapis.com/token`، كاش 50 دقيقة)؛ `send(string $token, string $title, ?string $body, array $data): FcmResult` إلى `https://fcm.googleapis.com/v1/projects/{project}/messages:send` بأولوية عالية وقناة `trendsham_default`؛ `FcmResult` (ok، invalidToken عند UNREGISTERED/404/INVALID_ARGUMENT، error)؛ مهلة 10 ثوانٍ؛ الاستثناءات لا تخرج من الدالة؛ و`tests/Unit/Push/FcmClientTest.php` بـ`Http::fake`
- [X] T010 تشغيل الهجرة على `trendsham` (بعد T001) والتأكد أن الاختبارات الحالية كلها تنجح

---

## Phase 3: User Story 1 - العميل يعرف بما يحدث لطلبه فوراً (P1) 🎯 MVP

**Goal**: كل إشعار يُحفظ يُرسل على هواتف صاحبه بعد الرد، ويفتح الشاشة الصحيحة.

**Independent Test**: جهاز مسجّل + تغيير حالة طلب ← طلب واحد إلى Firebase و`push_status=sent`.

### Tests

- [X] T011 [P] [US1] `tests/Feature/Push/DeviceRegistrationTest.php`: `POST /api/v1/push/devices` يسجل (200)، 422 بلا token أو بمنصة خاطئة، نفس التوكن لحساب آخر ينتقل له، `DELETE /api/v1/push/devices` يحذفه لصاحبه فقط (204)، بلا دخول 401
- [X] T012 [P] [US1] `tests/Feature/Push/OrderPushTest.php`: تغيير حالة طلب لعميل له جهازان ← إرسالان بعنوان الإشعار و`data.screen=order` و`data.id`؛ `push_status=sent`؛ بلا أجهزة ← `no_device`؛ بلا ملف مفتاح ← `not_configured` ولا طلبات HTTP؛ Firebase يرد UNREGISTERED ← حذف الجهاز و`failed` والعملية الأصلية ناجحة؛ Firebase 500/مهلة ← تغيير الحالة ناجح والإشعار في الجرس؛ تشغيل الإرسال مرتين لنفس الإشعار ← إرسال واحد؛ `GET /api/v1/notifications` يعيد `group` و`target`

### Implementation

- [X] T013 [US1] `app/Http/Controllers/Api/PushDeviceController.php` (`store` upsert على token، `destroy`) ومساراتها في `routes/api.php` داخل مجموعة `auth:sanctum` مع `throttle:30,1`
- [X] T014 [US1] `app/Notifications/Push/PushSender.php`: `send(int $notificationId): void` — تحديث شرطي `push_status` من `pending` (إلا خرج)، ثم `PushPolicy::decide()` (في US1 يُرجع "أرسل" دائماً؛ يُستكمل في US3/US4)، ثم الإرسال لكل أجهزة المستخدم، حذف الأجهزة غير الصالحة، ضبط `push_status`/`pushed_at`؛ لا يرمي استثناءات
- [X] T015 [US1] `AppNotification::send()` في `app/Models/AppNotification.php`: يضبط `group` (حسب دور المستلم) و`target` (`PushTarget::fromLink`) و`push_status=pending`، ثم `dispatch(fn () => app(PushSender::class)->send($id))->afterResponse()`؛ و`NotificationResource` يضيف `group` و`target`
- [X] T016 [P] [US1] flutter: `lib/services/push_service.dart`: `isConfigured`؛ `init()` (Firebase.initializeApp، قناة أندرويد)؛ `requestPermissionWithExplainer(BuildContext)` (شرح قصير ثم طلب الإذن، مرة واحدة)؛ `registerDevice()` و`onTokenRefresh` ← `POST /push/devices`؛ `unregisterDevice()` ← `DELETE`؛ `onMessage` (مقدمة) ← SnackBar بزر "عرض" وتحديث عدّاد الجرس؛ `onMessageOpenedApp` و`getInitialMessage` ← `NotificationRouter.open`
- [X] T017 [P] [US1] flutter: `lib/utils/notification_router.dart`: `open(String screen, String? id)` بـ`navigatorKey` لكل قيم `screen` في contracts/api.md، وإن فشل الجلب: SnackBar "لم يعد متاحاً"؛ و`test/notification_router_test.dart` لتحويل `{screen,id}` إلى المسار
- [X] T018 [US1] flutter: `navigatorKey` عام وتهيئة `PushService` في `lib/main.dart`؛ في `lib/providers/auth_provider.dart` تسجيل الجهاز بعد الدخول (وعند بدء التطبيق إن كان مسجلاً) وإلغاؤه قبل الخروج؛ `lib/models/notification.dart` يقرأ `target`؛ الضغط على إشعار في `lib/screens/notifications/notifications_screen.dart` يفتح الشاشة عبر `NotificationRouter`
- [X] T019 [US1] تشغيل T008, T009, T011, T012 و`flutter analyze lib && flutter test` حتى النجاح

**Checkpoint**: MVP — كل الإشعارات الحالية تصل للهاتف وتفتح الشاشة.

---

## Phase 4: User Story 2 - البائع يعرف بالطلب الجديد فوراً (P1)

**Goal**: إشعار `seller_new_order` لكل بائع في الطلب، وإشعارات البائع تفتح شاشات لوحة البائع.

**Independent Test**: طلب من متجرين ← إشعار لكل بائع بقيمة منتجاته.

### Tests

- [X] T020 [P] [US2] `tests/Feature/Push/SellerNewOrderTest.php`: دفع من الموقع ومن `POST /api/v1/checkout` لسلة من متجرين ← إشعاران `seller_new_order` بمجموع منتجات كل بائع (بلا الهدايا) ورابط `seller.orders.show` و`target.screen=seller_order`؛ متجر واحد ← إشعار واحد؛ رسالة من عميل وسؤال منتج يصلان البائع بمجموعة `seller_messages`

### Implementation

- [X] T021 [US2] `app/Notifications/Push/SellerOrderNotifier.php`: `notify(Order $order): void` كما في research R8؛ استدعاؤه بعد إنشاء الطلب وبنوده في `app/Http/Controllers/Customer/CheckoutController.php` و`app/Http/Controllers/Api/CheckoutController.php`
- [X] T022 [US2] flutter: `NotificationRouter` يفتح `seller_order` و`seller_wallet` و`seller_coupon` و`seller_products` في شاشات البائع الموجودة (`lib/screens/seller/…`)
- [X] T023 [US2] تشغيل T020 حتى النجاح

---

## Phase 5: User Story 3 - صفحة إعدادات الإشعارات (P1)

**Goal**: مجموعات لكل دور بمفتاح، تُحترم في الإرسال، والحرجة لا تُطفأ.

**Independent Test**: إيقاف `orders` ← `skipped_disabled` والإشعار في الجرس؛ الحرج يُرسل دائماً.

### Tests

- [X] T024 [P] [US3] `tests/Feature/Push/NotificationSettingsTest.php`: `GET /api/v1/notification-settings` للعميل والبائع بالمجموعات والأوصاف و`offers.enabled=null` لعميل جديد؛ `PUT` يحفظ ويرفض مفتاحاً من دور آخر (422)؛ الإعداد مشترك بين أجهزة الحساب؛ مجموعة موقوفة ← `skipped_disabled` والإشعار موجود في الجرس؛ `store_suspended` و`withdrawal_failed` تُرسل رغم إيقاف كل المجموعات

### Implementation

- [X] T025 [US3] `app/Notifications/Push/PushPreferences.php` (`for(User): array`، `update(User, array)`، `isEnabled(User, group): ?bool` — الغائب مفعّل عدا `offers` للعميل = null) و`app/Notifications/Push/PushPolicy.php` (`decide(AppNotification, User): string` يعيد `send` أو سبب المنع: الحرج والعام يُرسلان، المجموعة الموقوفة/غير المحددة ← `skipped_disabled`)؛ ربطه في `PushSender`
- [X] T026 [US3] `app/Http/Controllers/Api/NotificationSettingsController.php` (`show`, `update`) ومساراتها `GET/PUT /api/v1/notification-settings`
- [X] T027 [P] [US3] flutter: `lib/services/notification_settings_service.dart` (نماذج المجموعات + get/update)؛ `lib/screens/notifications/notification_settings_screen.dart`: مفتاح لكل مجموعة مع الوصف، ملاحظة الإشعارات الدائمة، تنبيه "الإشعارات مغلقة من إعدادات هاتفك" مع زر `AppSettings.openAppSettings(type: AppSettingsType.notification)` إذا كان الإذن مرفوضاً؛ مدخل إليها من صفحة الإشعارات ومن الملف الشخصي؛ و`test/notification_settings_test.dart` لتحليل الاستجابة
- [X] T028 [US3] تشغيل T024 و`flutter analyze lib && flutter test` حتى النجاح

---

## Phase 6: User Story 4 - إشعارات العروض بلا إزعاج (P2)

**Goal**: سؤال العروض عند أول متابعة، 3 يومياً كحد أقصى، لا شيء ليلاً.

**Independent Test**: 4 عروض في يوم ← 3 مُرسلة والرابع `skipped_limit`؛ عرض الساعة 23:00 ← `skipped_quiet`.

### Tests

- [X] T029 [P] [US4] `tests/Feature/Push/OfferLimitsTest.php`: عميل لم يُسأل ← عرض جديد `skipped_disabled`؛ أول متابعة (ويب و`POST /api/v1/stores/{id}/follow`) ← `ask_offers_opt_in=true` والثانية `false`؛ `PUT` offers=true يضبط `offers_prompted_at`؛ 4 عروض في يوم ← 3 `sent` و`skipped_limit`؛ عرض 23:00 و08:59 ← `skipped_quiet`، طلب 23:00 ← `sent` (`Carbon::setTestNow` بتوقيت Asia/Damascus)

### Implementation

- [X] T030 [US4] `PushPolicy`: لمجموعة `offers` (عدا `broadcast`): وقت الهدوء 22:00–09:00 ← `skipped_quiet`؛ عدّ `sent` اليوم لنفس المستخدم في مجموعة offers ≥ 3 ← `skipped_limit`
- [X] T031 [US4] `StoreFollowController` (ويب) و`Api/StoreFollowController`: عند متابعة عميل لم يُسأل (`offers_prompted_at` null ولا قيمة offers) ← `ask_offers_opt_in: true` في الـ API، وفي الويب رسالة بزرين (نعم/لا) تحفظ الاختيار عبر `POST /customer/notification-settings/offers`
- [X] T032 [US4] flutter: في `lib/screens/store/store_screen.dart` بعد المتابعة إن كان `ask_offers_opt_in` ← نافذة "هل تريد إشعارات عروض ومنتجات المتاجر التي تتابعها؟" (نعم/لا) ← `PUT /notification-settings`
- [X] T033 [US4] تشغيل T029 حتى النجاح

---

## Phase 7: User Story 5 - المدير يرسل إشعاراً جماعياً (P3)

**Goal**: إرسال لكل العملاء مرة كل 7 أيام، مؤجَّل ليلاً، مع سجل.

**Independent Test**: إرسال ← صف جرس لكل عميل وإرسال لمن فعّل العروض؛ ثانٍ خلال الأسبوع مرفوض.

### Tests

- [X] T034 [P] [US5] `tests/Feature/Push/BroadcastTest.php`: المدير يرى الصفحة بالعدد والمعاينة؛ بلا `confirm` رفض؛ إرسال ← صف `broadcast` في جرس كل العملاء (لا البائعين) و`recipients_count` صحيح، وإرسال للهاتف فقط لمن `offers=true` و`pushed_count` صحيح؛ ثانٍ خلال 7 أيام ← رفض بموعد التالي؛ إنشاء 23:30 ← `scheduled_for` 09:00 الغد ولا إرسال، والأمر `notifications:send-broadcasts` في 09:01 يرسله مرة واحدة (تشغيله مرتين لا يكرر)؛ عنوان > 60 أو نص > 180 أو رابط خارجي ← خطأ تحقق؛ غير المدير 403

### Implementation

- [X] T035 [US5] `app/Notifications/Push/BroadcastService.php`: `nextAllowedAt(): ?Carbon`، `create(array, User): PushBroadcast` (قاعدة 7 أيام، `scheduled_for` حسب الهدوء)، `sendDue(): int` (تحديث شرطي لـ`sent_at`، إدراج صفوف الجرس بدفعات 500 بـ`group=offers` و`type=broadcast` و`push_status` مضبوط مسبقاً، إرسال للأجهزة المسموحة بدفعات، تحديث العدادات)
- [X] T036 [US5] `app/Console/Commands/SendDueBroadcasts.php` (`notifications:send-broadcasts`) وجدولته كل دقيقة في `routes/console.php`؛ والإرسال الفوري بعد الرد عند الإنشاء إن كان مستحقاً
- [X] T037 [US5] `app/Http/Controllers/Admin/PushBroadcastController.php` (`index`, `store` بقواعد: title required|max:60، body required|max:180، link nullable|starts_with:/|max:255، confirm accepted) والمسارات `admin.push-broadcasts.*`؛ `resources/views/admin/push-broadcasts/index.blade.php` (نموذج، معاينة حية كإشعار هاتف، عدادات، الموعد التالي، السجل) — **بلا خلط `@php(...)` قبل كتل `@php … @endphp`**؛ رابط "إشعار جماعي" في `resources/views/admin/dashboard.blade.php`
- [X] T038 [US5] تشغيل T034 حتى النجاح

---

## Phase 8: Polish & Cross-Cutting

- [X] T039 [P] سؤال في `database/seeders/AssistantFaqSeeder.php` "كيف أتحكم بالإشعارات؟" وتشغيله على القاعدة الحقيقية
- [X] T040 فحص بصري لصفحة المدير (فاتح/داكن/جوال) والتأكد أن لا كود Blade يظهر
- [X] T041 بناء التطبيق `flutter build apk --debug` للتأكد أن Firebase مدمج ويُبنى؛ (التجربة على هاتف حقيقي يقوم بها صاحب المشروع حسب quickstart)
- [X] T042 كل الاختبارات `php artisan test` و`flutter analyze lib && flutter test`، ومطابقة سيناريوهات quickstart الـ15
- [X] T043 حفظ في git (الموقع والتطبيق منفصلين) بعد فحص `git diff --cached --name-only`: **التأكد أن `firebase-credentials.json` غير موجود**، واستبعاد `lib/screens/home/home_tab.dart` و`.artifacts/`؛ `google-services.json` يُحفظ (ملف عام)

---

## Dependencies & Execution Order

- Setup (T001–T004) ← Foundational (T005–T010) ← كل القصص.
- **US1** أساس الإرسال؛ US2–US5 تعتمد على `PushSender` منه.
- **US3** قبل **US4** (سياسة الإرسال نفسها تُوسَّع).
- **US5** يعتمد على US3 (إعداد offers) وUS4 (وقت الهدوء).
- US2 مستقلة عن US3/US4 بعد US1.

## Parallel Opportunities

- T003 مع T004؛ T006–T009 معاً بعد T005.
- في US1: اختبارات T011/T012 معاً؛ Flutter (T016، T017) بالتوازي مع الخادم (T013–T015).
- US2 بالتوازي مع US3 بعد US1.

### Parallel Example: User Story 1

```text
الخادم: T013 → T014 → T015
التطبيق: T016 | T017  ثم T018
```

## Implementation Strategy

1. **MVP** = Setup + Foundational + US1: كل الإشعارات الموجودة تصل للهاتف وتفتح الشاشة.
2. US2 (طلب جديد للبائع) — أعلى قيمة بعد MVP.
3. US3 (الإعدادات) ثم US4 (العروض) ثم US5 (الجماعي).
4. كل قصة: الاختبار يفشل أولاً ← التنفيذ ← النجاح ← حفظ.
