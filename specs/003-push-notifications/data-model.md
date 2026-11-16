# Data Model: الإشعارات المنبثقة

هجرة واحدة: `2026_11_19_000000_create_push_notification_tables.php` (إضافية فقط).

## push_devices (جديد)

| العمود | النوع | ملاحظات |
|---|---|---|
| id | bigint | |
| user_id | FK users, cascadeOnDelete | |
| token | string(255), **unique** | معرّف الإرسال من Firebase |
| platform | enum `android, ios` | |
| app_version | string(20) nullable | |
| last_seen_at | timestamp | يُحدَّث عند كل تسجيل/فتح |
| timestamps | | |

قواعد: upsert على `token` (الجهاز ينتقل لمن سجّل الدخول آخراً)؛ حذف عند الخروج أو عند رفض Firebase للتوكن.

## users (أعمدة جديدة)

| العمود | النوع | ملاحظات |
|---|---|---|
| notification_prefs | json nullable | `{"orders":true,"offers":false,…}`؛ الغائب = مفعّل، عدا `offers` للعميل = لم يُحدَّد |
| offers_prompted_at | timestamp nullable | متى سُئل العميل عن العروض (مرة واحدة) |

## notifications (أعمدة جديدة على الجدول القائم)

| العمود | النوع | ملاحظات |
|---|---|---|
| group | string(30) nullable | من `NotificationGroups` حسب النوع والدور |
| target | json nullable | `{"screen":"order","id":14}` من `PushTarget::fromLink` |
| push_status | enum `pending, sent, skipped_disabled, skipped_quiet, skipped_limit, no_device, not_configured, failed` nullable | null للصفوف القديمة |
| pushed_at | timestamp nullable | |
| broadcast_id | FK push_broadcasts nullable, nullOnDelete | للإشعار الجماعي |

فهرس `(user_id, group, pushed_at)` لعدّ حد العروض اليومي.

**انتقالات `push_status`**: `pending` ← (تحديث شرطي) ← واحد من: `sent` | `skipped_*` | `no_device` | `not_configured` | `failed`. لا رجوع إلى `pending`.

## push_broadcasts (جديد)

| العمود | النوع | ملاحظات |
|---|---|---|
| id | bigint | |
| title | string(60) | |
| body | string(180) | |
| link | string(255) nullable | مسار داخلي في الموقع (يُحوَّل لهدف) |
| created_by | FK users nullOnDelete | المدير |
| scheduled_for | timestamp | الآن أو 09:00 القادمة |
| sent_at | timestamp nullable | يُضبط بتحديث شرطي (لا إرسال مزدوج) |
| recipients_count | unsigned int default 0 | صفوف الجرس |
| pushed_count | unsigned int default 0 | وصل للهاتف |
| timestamps | | |

قاعدة: لا يُنشأ إذا وُجد صف `created_at` خلال آخر 7 أيام.

## المجموعات (ثابتة في الكود — `NotificationGroups`)

| الدور | المجموعة | قابلة للإيقاف |
|---|---|---|
| customer | orders, messages, coupons_points, offers | نعم |
| seller | seller_orders, seller_messages, seller_store, seller_payouts, seller_coupons | نعم |
| أي دور | critical (store_suspended, store_unsuspended, withdrawal_failed, payment_duplicate) | **لا** |
| أي دور | general (أنواع غير مصنفة) | لا (تُرسل) |
