# Implementation Plan: الإشعارات المنبثقة على الهاتف

**Branch**: `003-push-notifications` | **Date**: 2026-11-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-push-notifications/spec.md`

## Summary

كل إشعار في المشروع يمر اليوم بنقطة واحدة: `AppNotification::send()` (≈25 نوعاً، تُحفظ في جدول `notifications` ويراها المستخدم في الجرس). نضيف بعد الحفظ خطوة إرسال على الهاتف عبر Firebase Cloud Messaging، تُنفَّذ **بعد إرسال الرد** فلا تؤخر الطلب ولا تُفشله، وتحترم إعدادات المستخدم وحدود العروض. ونضيف إشعار "طلب جديد" للبائع (غير موجود اليوم)، وصفحة إعدادات في التطبيق، وسؤال العروض عند أول متابعة، وإرسالاً جماعياً أسبوعياً للمدير.

**النهج التقني** (التفاصيل في [research.md](research.md)):
- **الخادم يرسل مباشرة** إلى واجهة FCM v1 بمفتاح حساب خدمة من Firebase، يُوقَّع محلياً (openssl الموجود) ويُخزَّن رمز الدخول مؤقتاً — بلا حزم جديدة. بدون ملف المفتاح: الإرسال معطّل بهدوء والجرس يعمل كالمعتاد.
- **تصنيف واحد** `NotificationGroups`: كل نوع ← مجموعة (طلبات، رسائل، كوبونات، عروض…) + هل هو حرج لا يُطفأ.
- **الشاشة المستهدفة** تُستنتج من الرابط الموجود في كل إشعار (`/customer/orders/14` ← طلب 14) فلا نعدّل الـ25 موضعاً.
- **التطبيق**: `firebase_core` + `firebase_messaging`؛ تسجيل الهاتف بعد الدخول وإلغاؤه عند الخروج؛ فتح الشاشة من الإشعار؛ صفحة إعدادات؛ وجعل الضغط على الإشعار في الجرس يفتح الشاشة أيضاً.
- **الإرسال الجماعي** يُخزَّن ويُرسَل بمهمة مجدولة كل دقيقة (يؤجَّل ليلاً إلى 09:00).

## Technical Context

**Language/Version**: PHP 8.2 (Laravel 12)؛ Dart 3 (Flutter)

**Primary Dependencies**: Laravel (Eloquent, Scheduler, Sanctum, Http client)؛ Firebase Cloud Messaging HTTP v1؛ Flutter: `firebase_core`, `firebase_messaging`, `app_settings`

**Storage**: MySQL (`trendsham`؛ الاختبارات `trendsham_testing`)؛ مفتاح Firebase في `storage/app/private/firebase-credentials.json` (خارج git)

**Testing**: PHPUnit مع `Http::fake()` لواجهة Firebase؛ `flutter analyze` و`flutter test` (منطق التوجيه والإعدادات بلا Firebase حقيقي)

**Target Platform**: خادم Laravel + تطبيق Android (آيفون لاحقاً بنفس الكود)

**Project Type**: Laravel monolith (ويب + `/api/v1`) + تطبيق Flutter

**Performance Goals**: لا زيادة ملحوظة في زمن الطلب (الإرسال بعد الرد)؛ الإشعار على الهاتف خلال 10 ثوانٍ (SC-001)؛ الإرسال الجماعي على دفعات 500 جهاز

**Constraints**: لا بيانات حساسة في نص الإشعار؛ لا تكرار لنفس الحدث؛ مفتاح Firebase لا يُحفظ في git أبداً؛ فشل Firebase لا يُفشل أي عملية

**Scale/Scope**: آلاف المستخدمين؛ 2 جدول جديد + أعمدة؛ ~3 شاشات تطبيق، صفحتا ويب للمدير، ~6 نقاط API

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

ملف الدستور ما زال قالباً؛ البوابات هي قواعد المشروع المتفق عليها:

| البوابة | الحالة | كيف تلتزم الخطة |
|---|---|---|
| تعديل قاعدة البيانات بموافقة وبعد نسخة احتياطية | ✅ | هجرة إضافية فقط؛ تُشغَّل بعد موافقة ونسخة في التنفيذ |
| لا ملفات حساسة في git | ✅ | ملف مفتاح Firebase في `storage/app/private` المستبعد، وفحص `git diff --cached` قبل كل حفظ |
| الموقع والتطبيق معاً | ✅ | الإرسال للهاتف بطبيعته للتطبيق؛ الجرس في الموقع يبقى، والإرسال الجماعي من لوحة المدير في الموقع |
| واجهة عربية RTL + الوضع الداكن | ✅ | شاشات التطبيق والمدير بالمكوّنات الحالية |
| الاختبارات تنجح قبل الانتهاء | ✅ | Feature لكل قصة مع Firebase مزيّف؛ Flutter لمنطق التوجيه |

**النتيجة**: لا مخالفات.

## Project Structure

### Documentation (this feature)

```text
specs/003-push-notifications/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md         # سيناريوهات التحقق + خطوات إنشاء Firebase بالعربية
├── contracts/{api.md, web.md}
└── tasks.md              # /speckit-tasks
```

### Source Code

```text
database/migrations/2026_11_19_000000_create_push_notification_tables.php

app/Notifications/Push/
├── NotificationGroups.php    # نوع ← مجموعة، حرج؟، مجموعات كل دور
├── PushTarget.php            # الرابط ← {screen, id}
├── PushPolicy.php            # يُرسل؟ (إعدادات، حرج، حد العروض، الهدوء الليلي)
├── PushPreferences.php       # قراءة/حفظ إعدادات الحساب
├── FcmClient.php             # توكن الخدمة + إرسال رسالة لجهاز
├── PushSender.php            # إشعار ← أجهزة المستخدم، حذف الأجهزة غير الصالحة، حالة الإرسال
├── SellerOrderNotifier.php   # "طلب جديد" لكل بائع في الطلب
└── BroadcastService.php      # الإرسال الجماعي الأسبوعي

app/Models/{PushDevice.php, PushBroadcast.php}
app/Models/AppNotification.php          # send(): المجموعة + الهدف + الإرسال بعد الرد
app/Console/Commands/SendDueBroadcasts.php   # كل دقيقة
app/Http/Controllers/Api/PushDeviceController.php
app/Http/Controllers/Api/NotificationSettingsController.php
app/Http/Controllers/Admin/PushBroadcastController.php
resources/views/admin/push-broadcasts/index.blade.php
Customer/Api CheckoutController       # SellerOrderNotifier بعد إنشاء الطلب
StoreFollowController (+Api)          # إشارة "اسأل عن العروض" في الاستجابة

tests/Feature/Push/…

trendsham_flutter/lib/
├── firebase_options.dart              # من مشروع Firebase لصاحب المشروع
├── services/push_service.dart         # تهيئة، إذن، تسجيل/إلغاء الجهاز، الاستماع
├── services/notification_settings_service.dart
├── utils/notification_router.dart     # {screen,id} ← شاشة
├── screens/notifications/notification_settings_screen.dart
├── screens/notifications/notifications_screen.dart   # الضغط يفتح الشاشة
├── providers/auth_provider.dart       # تسجيل الجهاز بعد الدخول وإلغاؤه عند الخروج
├── main.dart                          # navigatorKey + تهيئة Firebase
└── screens/store/store_screen.dart    # سؤال العروض عند أول متابعة
```

## Constitution Check (بعد التصميم)

أُعيد الفحص بعد [data-model.md](data-model.md) و[contracts/](contracts/): كل الأعمدة جديدة؛ المفتاح خارج git؛ الإرسال لا يمس معاملات الطلب. **لا مخالفات.**

## Complexity Tracking

لا مخالفات.
