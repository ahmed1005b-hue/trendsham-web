# Implementation Plan: نظام نقاط الولاء للعملاء

**Branch**: `001-loyalty-points` | **Date**: 2026-11-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-loyalty-points/spec.md`

## Summary

العميل يكسب نقطة عن كل 10,000 ل.س يدفعها فعلياً (بعد الخصومات، دون الشحن) عند تسليم طلبه، ويستبدل النقاط بخصم 200 ل.س للنقطة: إما مباشرة في صفحة الدفع، أو بتحويلها إلى كوبون في محفظة الكوبونات. المنصة تتحمّل الخصم كاملاً فلا تتأثر أرباح البائع.

**النهج التقني** (تفاصيله في [research.md](research.md)):
- **دفتر نقاط** مستقل: كل كسب "دفعة" لها صلاحية ورصيد متبقٍّ، وكل استهلاك يُوزَّع على الدفعات الأقدم أولاً ويُسجَّل توزيعه، فيمكن إرجاع النقاط إلى دفعاتها الأصلية بتواريخ انتهائها.
- **الكسب والسحب** يُربطان بالمدخل الوحيد لتغيير حالة الطلب `Order::settleForStatusChange()` (الذي تُسوّى فيه أرباح البائع ويُعاد فيه الكوبون)، فيُنفَّذان مرة واحدة تحت نفس القفل.
- **الخصم عند الدفع** يُحفظ على الطلب (`points_redeemed`, `points_discount`) ولا يدخل `settlementBase()` للبائع.
- **التحويل لكوبون** يعيد استخدام نظام الكوبونات الحالي: حملة نظامية مخفية بتمويل المنصة لكل قيمة، وكوبون في محفظة العميل.
- **مهمة مجدولة يومية**: إنهاء صلاحية النقاط، إرجاع نقاط كوبونات النقاط المنتهية دون استخدام، وتنبيهات قبل 30 يوماً.

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 12) للموقع والخادم؛ Dart 3 (Flutter) للتطبيق

**Primary Dependencies**: Laravel (Eloquent, Scheduler, Sanctum)، Blade + Bootstrap 5 RTL، نظام الكوبونات الحالي `app/Coupons/*`، `AppNotification`

**Storage**: MySQL (`trendsham`؛ الاختبارات على `trendsham_testing`). المبالغ بالوحدة الصغرى ×100

**Testing**: PHPUnit (`php artisan test`) — Feature + Unit؛ `flutter analyze` و`flutter test`

**Target Platform**: خادم ويب (XAMPP محلياً) + تطبيق Android/iOS

**Project Type**: تطبيق ويب (Laravel monolith: ويب + واجهة تطبيق `/api/v1`) + تطبيق جوال Flutter

**Performance Goals**: صفحة "نقاطي" وحساب "ستكسب X نقاط" دون استعلامات N+1؛ منح النقاط ضمن معاملة تسليم الطلب نفسها (أقل من ثانية)

**Constraints**: لا ازدواج في المنح أو الاستبدال (قفل الصفوف + فهارس فريدة)؛ لا مبلغ طلب سالب؛ تطابق الرصيد مع مجموع الحركات دائماً؛ لا تأثير على أرباح البائع

**Scale/Scope**: آلاف العملاء؛ 4 جداول جديدة/معدّلة، ~6 صفحات ويب، ~3 شاشات تطبيق، ~6 نقاط واجهة تطبيق

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

ملف `.specify/memory/constitution.md` ما زال قالباً غير مُعتمد. البوابات أدناه هي قواعد المشروع المتفق عليها مع صاحبه:

| البوابة | الحالة | كيف تلتزم الخطة |
|---|---|---|
| تعديل قاعدة البيانات بموافقة وبعد نسخة احتياطية | ✅ | الهجرات إضافية فقط (جداول وأعمدة جديدة)، تُشغَّل بعد موافقة ونسخة احتياطية في مرحلة التنفيذ |
| الموقع والتطبيق معاً | ✅ | كل قصة لها واجهة ويب + نقطة API + شاشة Flutter |
| المبالغ ×100 | ✅ | `points_discount` وإعدادات القيم بالوحدة الصغرى؛ النقاط أعداد صحيحة |
| واجهة عربية RTL + الوضع الداكن | ✅ | الصفحات الجديدة على مكوّنات الموقع الحالية مع قواعد `ts-dark.css` |
| الاختبارات تنجح قبل اعتبار العمل منتهياً | ✅ | اختبارات Feature لكل قصة وحالة حدّية (انظر [quickstart.md](quickstart.md)) |
| لا حفظ لملفات حساسة | ✅ | لا ملفات مرفوعة أو سجلات في هذه الميزة |
| أرباح البائع لا تُمس (FR-015) | ✅ | خصم النقاط خارج `settlementBase()`؛ اختبار يثبت ذلك |

**النتيجة**: لا مخالفات. (إعادة الفحص بعد التصميم في آخر الملف.)

## Project Structure

### Documentation (this feature)

```text
specs/001-loyalty-points/
├── spec.md              # المواصفات
├── plan.md              # هذا الملف
├── research.md          # القرارات التقنية (المرحلة 0)
├── data-model.md        # نموذج البيانات (المرحلة 1)
├── quickstart.md        # سيناريوهات التحقق (المرحلة 1)
├── contracts/
│   ├── api.md           # واجهة التطبيق /api/v1
│   └── web.md           # مسارات وصفحات الموقع
├── checklists/
│   └── requirements.md
└── tasks.md             # (يُنشأ بـ /speckit-tasks)
```

### Source Code (repository root)

```text
# الخادم والموقع (c:\xampp\htdocs\trendsham)
app/Loyalty/
├── LoyaltyLedger.php          # المنح، الاستهلاك FIFO، الإرجاع، الانتهاء، التعديل اليدوي (كل العمليات المقفولة)
├── LoyaltyCalculator.php      # نقاط مبلغ ما، قيمة الخصم، "ستكسب X"، النقاط المعلّقة
├── PointsCouponIssuer.php     # تحويل النقاط إلى كوبون في المحفظة
├── LoyaltySettings.php        # قراءة الإعدادات من platform_settings بقيم افتراضية
└── Models/
    ├── LoyaltyAccount.php
    ├── LoyaltyTransaction.php
    └── LoyaltyAllocation.php
app/Models/Order.php                         # settleForStatusChange: منح/سحب/إرجاع النقاط
app/Http/Controllers/Customer/CheckoutController.php   # استبدال عند الدفع
app/Http/Controllers/Api/CheckoutController.php        # نفسه للتطبيق
app/Http/Controllers/Customer/LoyaltyController.php    # صفحة "نقاطي" + التحويل لكوبون
app/Http/Controllers/Api/LoyaltyController.php
app/Http/Controllers/Admin/LoyaltyController.php       # الملخص + التعديل اليدوي + الإعدادات
app/Console/Commands/LoyaltyDailyCommand.php           # الانتهاء + إرجاع كوبونات النقاط + التنبيهات
database/migrations/2026_11_17_*                        # جداول النقاط + أعمدة الطلب والإعدادات
resources/views/customer/loyalty/index.blade.php
resources/views/admin/loyalty/{index,show}.blade.php
resources/views/partials/loyalty-earn-hint.blade.php   # "ستكسب X نقاط" للمنتج والسلة والدفع
tests/Feature/Loyalty/{Earning,CheckoutRedemption,CouponConversion,Expiry,Admin,Api}Test.php
tests/Unit/Loyalty/LoyaltyCalculatorTest.php

# التطبيق (C:\Users\istanbul\Desktop\trendsham_flutter)
lib/services/loyalty_service.dart
lib/screens/loyalty/my_points_screen.dart
lib/screens/checkout/checkout_screen.dart     # مفتاح "استخدم نقاطي"
lib/screens/profile/profile_screen.dart       # مدخل "نقاطي" والرصيد
test/loyalty_service_test.dart
```

**Structure Decision**: نتبع بنية المشروع الحالية: منطق المجال في مجلد خاص `app/Loyalty/` (كما `app/Coupons/` و`app/Assistant/`)، والمتحكمات في مساراتها المعتادة للويب والتطبيق والإدارة، وشاشات Flutter في `lib/screens/` مع خدمة في `lib/services/`.

## Complexity Tracking

لا مخالفات تستدعي التبرير. جدول التوزيع `loyalty_allocations` هو التعقيد الوحيد الإضافي، وهو مطلوب لتحقيق "إرجاع النقاط بتاريخ انتهائها الأصلي" وتطابق الرصيد مع السجل (FR-006, FR-007, SC-004).

## Constitution Check (post-design)

أُعيد الفحص بعد [data-model.md](data-model.md) و[contracts/](contracts/): كل البوابات ما زالت ✅. الهجرات إضافية فقط ولا تغيّر أعمدة قائمة، وخصم النقاط لا يدخل أي حساب لأرباح البائع أو العمولة.
