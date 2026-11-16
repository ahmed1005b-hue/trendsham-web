# Research: الإشعارات المنبثقة على الهاتف

مبني على قراءة الكود: كل الإشعارات عبر `AppNotification::send(userId, type, title, body, link)`، والجدول `notifications` فيه `link` (مسار ويب). التطبيق اليوم: الضغط على إشعار في الجرس يعلّمه مقروءاً فقط ولا يفتح شيئاً.

## R1 — كيف يرسل الخادم إلى Firebase

- **Decision**: واجهة FCM HTTP v1 مباشرة عبر `Http` في Laravel. رمز الدخول: JWT بتوقيع RS256 بمفتاح حساب الخدمة (`openssl_sign`، موجود في PHP)، يُبدَّل بـ access token من `oauth2.googleapis.com/token`، ويُخزَّن في الكاش 50 دقيقة.
- **Rationale**: لا حزم جديدة؛ الواجهة القديمة (legacy server key) أُوقفت من Google.
- **Alternatives**: حزمة `kreait/laravel-firebase` — تضيف اعتماديات كثيرة لاستعمال واحد؛ OneSignal — رفضه صاحب المشروع لصالح Firebase.
- **غياب المفتاح**: `FcmClient::isConfigured()` = false ← لا إرسال، حالة الإشعار `not_configured`، والجرس يعمل. يسمح بالتطوير والاختبار قبل إنشاء Firebase.

## R2 — متى يُرسل (عدم تأخير العمليات)

- **Decision**: داخل `AppNotification::send()` بعد الحفظ: `dispatch(fn () => PushSender::send($id))->afterResponse()`. في سطر الأوامر (المجدول) يُنفَّذ عند نهاية الأمر.
- **Rationale**: `QUEUE_CONNECTION=database` بلا عامل مضمون؛ `afterResponse` لا يحتاج عاملاً ولا يؤخر الرد (نفس قرار كوبونات المتابعين). الإشعارات التي تُنشأ داخل معاملة تُرسل بعد الرد أي بعد انتهاء المعاملة.
- **عدم التكرار (FR-009)**: كل صف إشعار يُرسل مرة واحدة: يُعلَّم `push_status` قبل الإرسال بشرط أنه ما زال `pending` (تحديث شرطي).

## R3 — المجموعات والإشعارات الحرجة

- **Decision**: `NotificationGroups::MAP` (نوع ← مجموعة):
  - عميل: `orders` (order_created, order_status_changed, order_cancelled, shipment_updated, payment_confirmed, payment)، `messages` (new_message, staff_message, product_question_answer)، `coupons_points` (coupon_received, loyalty_expiring)، `offers` (store_new_offer, store_new_product, broadcast).
  - بائع: `seller_orders` (seller_new_order)، `seller_messages` (new_message, product_question, staff_message)، `seller_store` (product_approved, product_rejected, seller_approved, seller_rejected, showcase_*)، `seller_payouts` (withdrawal_approved, withdrawal_paid)، `seller_coupons` (seller_coupon_review).
  - حرجة (تُرسل دائماً): store_suspended, store_unsuspended, withdrawal_failed, payment_duplicate.
  - النوع غير المعروف ← `general` يُرسل (لا نخسر إشعاراً جديداً لم يُصنَّف).
- نفس النوع (مثل new_message) يقع في مجموعة العميل أو البائع حسب دور المستلم.

## R4 — الشاشة المستهدفة

- **Decision**: `PushTarget::fromLink(link)` بمطابقة المسار: `/customer/orders/{id}`→order، `/seller/orders/{id}`→seller_order، `/conversations/{id}` و`/seller/conversations/{id}`→conversation، `/product/{slug}`→product، `/store/{id}`→store، `/customer/coupons`→coupons، `/customer/points`→points، `/seller/wallet`→seller_wallet، `/seller/coupons/{id}`→seller_coupon، `/seller/products…`→seller_products، غير ذلك→notifications. يُحفظ `target` (JSON) في صف الإشعار ويُرسل في `data` للتطبيق، ويُعاد في `/api/v1/notifications` ليستعمله الجرس أيضاً.
- **Rationale**: لا نعدّل 25 موضع استدعاء؛ الروابط موجودة أصلاً.

## R5 — حدود العروض والهدوء الليلي

- **Decision**: لمجموعة `offers` (غير الجماعي): لا إرسال بين 22:00 و09:00 (توقيت التطبيق Asia/Damascus)؛ ولا أكثر من 3 مُرسلة اليوم للمستخدم (عدّ صفوف `push_status=sent` لمجموعة offers منذ بداية اليوم). الممنوع يبقى في الجرس بحالة `skipped_quiet` / `skipped_limit`. الجماعي له حد أسبوعي خاص ولا يُحسب ضمن الثلاثة.

## R6 — الإعدادات

- **Decision**: عمود JSON `users.notification_prefs` = `{group: bool}`؛ الغائب = مفعّل، عدا `offers` للعميل: الغائب = "لم يُحدَّد" (لا إرسال). `users.offers_prompted_at` لتسجيل أن السؤال طُرح.
- **سؤال العروض**: استجابة المتابعة في الـ API تضيف `ask_offers_opt_in: true` إذا كان العميل لم يُسأل؛ التطبيق يعرض السؤال ويرسل الجواب لـ `PUT /notification-settings`.

## R7 — الأجهزة

- **Decision**: جدول `push_devices` (token فريد). التسجيل بـ upsert على token: إن كان لمستخدم آخر ينتقل للحالي. الخروج يحذفه. ردّ Firebase بـ `UNREGISTERED` أو 404 أو `INVALID_ARGUMENT` للتوكن ← حذف الجهاز.

## R8 — إشعار "طلب جديد" للبائع

- **Decision**: `SellerOrderNotifier::notify(Order)` بعد إنشاء الطلب في الدفع (موقع وتطبيق): لكل `vendor_id` مجموع `subtotal` لبنوده غير الهدايا ← `AppNotification::send(sellerUserId, 'seller_new_order', 'طلب جديد #…', 'بقيمة … ل.س', route('seller.orders.show', order))`.

## R9 — الإرسال الجماعي

- **Decision**: جدول `push_broadcasts`. الإنشاء يتحقق: لا إرسال (مُرسل أو مجدول) خلال 7 أيام. `scheduled_for` = الآن، أو 09:00 القادمة إن كان الوقت ليلاً. الأمر `notifications:send-broadcasts` كل دقيقة (يحتاج المجدول الذي نحتاجه أصلاً لنقاط الولاء) يرسل المستحق: يُنشئ صف إشعار لكل عميل على دفعات (insert جماعي، دون إرسال فردي)، ثم يرسل للأجهزة التي مجموعة offers لأصحابها مفعّلة. يُعلَّم `sent_at` بتحديث شرطي فلا يُرسل مرتين. يُرسل أيضاً فوراً بعد الرد إن كان مستحقاً الآن.

## R10 — التطبيق (Flutter)

- **Decision**: `firebase_core` + `firebase_messaging` + `app_settings` (لفتح إعدادات الهاتف). `firebase_options.dart` يُولَّد من مشروع صاحب المشروع؛ حتى يتوفر، `PushService.isConfigured=false` ولا يُستدعى Firebase (التطبيق يُبنى ويعمل). `navigatorKey` عام في `main.dart` لفتح الشاشة من `onMessageOpenedApp` و`getInitialMessage`. في المقدمة: `SnackBar` بزر "عرض" وتحديث عدّاد الجرس (بلا حزمة إشعارات محلية).
- **معرّف التطبيق**: هو `com.example.trendsham` (الافتراضي). يُسجَّل في Firebase كما هو الآن؛ **قبل النشر على Google Play يجب تغييره** (Play يرفض com.example) وعندها يُضاف التطبيق الجديد في Firebase. يُذكر في quickstart.

## R11 — الأمان

- ملف المفتاح في `storage/app/private/firebase-credentials.json` (المجلد مستبعد من git)، ومساره في `.env` `FIREBASE_CREDENTIALS`.
- نص الإشعار من العنوان والنص الموجودين (لا بيانات بطاقات ولا رموز تحقق في الإشعارات أصلاً)؛ الإرسال الجماعي محدود الطول.
- تسجيل الجهاز يتطلب دخولاً (Sanctum)؛ لا يمكن ربط جهاز بحساب آخر إلا بالدخول إليه.
