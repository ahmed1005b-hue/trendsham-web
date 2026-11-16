# Implementation Plan: كوبونات البائع

**Branch**: `002-seller-coupons` | **Date**: 2026-11-12 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-seller-coupons/spec.md`

## Summary

البائع ينشئ كوبونات لمتجره (نسبة، مبلغ، شحن مجاني، هدية) يتحمّل خصمها من أرباحه. العميل يجمعها من صفحة المتجر أو المنتج، أو تصله تلقائياً إن كان متابعاً. المدير يوافق فقط على النسبة فوق 50%، ويوقف أي كوبون بسبب.

**النهج التقني** (التفاصيل في [research.md](research.md)):
- **لا نظام جديد**: محفظة الكوبونات من المرحلة الأولى (`app/Coupons/*`) مصممة أصلاً لممول "seller" — الجداول والتسوية (`settlementBase()` يخصم كوبون البائع) واختيار الأفضل والعكس عند الإلغاء موجودة. نضيف فوقها.
- **`SellerCampaignWriter`** جديد بجانب `CampaignWriter` (المدير): قواعد البائع (نسبة ≤ 70%، نطاق متجره فقط، توزيع جمع/متابعين)، وحالة "بانتظار الموافقة" تلقائياً فوق 50%.
- **حالات جديدة للحملة**: `pending_review`, `rejected`, `suspended` (أوقفها المدير) بجانب `active`, `paused`.
- **الجمع**: `CouponIssuer::collect()` يستعمل `issue()` الحالي (قفل الحملة، لا تكرار) مع سقف `total_limit` على عدد النسخ المُصدرة لكوبونات البائع.
- **المتابعون**: عند التفعيل يُصدر للمتابعين على دفعات بعد إرسال الرد (`dispatch()->afterResponse()`، لا يحتاج عامل طوابير)؛ وعند متابعة جديدة يُصدر في `StoreFollowController`.
- **الشحن المجاني من البائع**: يُقبل فقط إذا كانت السلة كلها من متجره؛ عند التسليم يُخصم مبلغ الشحن من محفظة البائع بقيد محاسبي، ويُعكس عند الإلغاء.
- **الهدية**: بند طلب بسعر 0 وعلامة `is_gift`؛ الإضافة في خدمة مشتركة يستدعيها الدفع في الموقع والتطبيق؛ المخزون يُخصم ويعود بالإلغاء عبر `restockItems()` الحالي.
- **الواجهات**: لوحة البائع (موقع)، شريط "كوبونات المتجر" في صفحة المتجر والمنتج (موقع + تطبيق)، لوحة المدير (موقع).

## Technical Context

**Language/Version**: PHP 8.2+ (Laravel 12) للموقع والخادم؛ Dart 3 (Flutter) للتطبيق

**Primary Dependencies**: Laravel (Eloquent, Sanctum)، Blade + Bootstrap 5 RTL، وحدة الكوبونات `app/Coupons/*`، `LedgerService`، `AppNotification`

**Storage**: MySQL (`trendsham`؛ الاختبارات على `trendsham_testing`). المبالغ بالوحدة الصغرى ×100

**Testing**: PHPUnit (`php artisan test`)؛ `flutter analyze lib` و`flutter test`

**Target Platform**: خادم ويب (XAMPP محلياً) + تطبيق Android/iOS

**Project Type**: Laravel monolith (ويب + `/api/v1`) + تطبيق Flutter

**Performance Goals**: شريط الكوبونات في صفحة المتجر/المنتج باستعلام واحد بلا N+1؛ إصدار المتابعين بدفعات 200 بعد الرد، يكتمل خلال دقيقة لآلاف المتابعين (SC-004)

**Constraints**: لا تجاوز `total_limit` ولا `per_customer_limit` مع التزامن (قفل صف الحملة الحالي)؛ أرباح البائع لا تصبح سالبة؛ المنصة لا تتحمّل شيئاً من كوبون البائع؛ قيم الكوبون لا تتغير بعد أول جمع

**Scale/Scope**: مئات المتاجر؛ هجرة واحدة إضافية؛ ~5 صفحات ويب، شاشتا تطبيق معدّلتان، ~4 نقاط API

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

ملف `.specify/memory/constitution.md` ما زال قالباً غير مُعتمد. البوابات أدناه هي قواعد المشروع المتفق عليها:

| البوابة | الحالة | كيف تلتزم الخطة |
|---|---|---|
| تعديل قاعدة البيانات بموافقة وبعد نسخة احتياطية | ✅ | هجرة إضافية فقط (أعمدة جديدة + توسيع قيم `status`)؛ تُشغَّل بعد موافقة ونسخة احتياطية |
| الموقع والتطبيق معاً | ✅ | جمع واستخدام الكوبون في الاثنين؛ إدارة البائع في لوحة الموقع (انظر research R10) |
| المبالغ ×100 | ✅ | كل القيم بالوحدة الصغرى، والمدخلات بالليرة تُضرب ×100 كما في `CampaignWriter` |
| واجهة عربية RTL + الوضع الداكن | ✅ | صفحات على مكوّنات لوحة البائع الحالية + `html.dark` |
| الاختبارات تنجح قبل الانتهاء | ✅ | اختبارات Feature لكل قصة + الحالات المالية (انظر [quickstart.md](quickstart.md)) |
| من أنشأ الكوبون يتحمّل خصمه | ✅ | `discount_funder = seller` على البنود، وخصم الشحن من محفظة البائع؛ اختبار المثال 100,000 → 72,000/8,000 |
| لا ملفات حساسة | ✅ | لا رفع ملفات |

**النتيجة**: لا مخالفات.

## Project Structure

### Documentation (this feature)

```text
specs/002-seller-coupons/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api.md
│   └── web.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
database/migrations/
└── 2026_11_18_000000_add_seller_coupon_columns.php     # حالات المراجعة، الهدية، is_gift، شحن البائع

app/Coupons/
├── SellerCampaignWriter.php      # جديد: قواعد إنشاء/تعديل كوبون البائع + الموافقة فوق 50%
├── CampaignReview.php            # جديد: موافقة/رفض/إيقاف المدير + الإشعارات
├── FollowerIssuer.php            # جديد: إصدار للمتابعين على دفعات
├── GiftLine.php                  # جديد: إضافة بند الهدية في الدفع (موقع + API)
├── SellerCouponStats.php         # جديد: أرقام الإحصائيات
├── CouponEligibility.php         # تعديل: الهدية، شحن البائع (السلة كلها من متجره)
├── CouponIssuer.php              # تعديل: collect() + سقف النسخ لكوبونات البائع
├── EligibilityResult.php         # تعديل: giftProductId + قيمة الهدية في saving()
├── CouponPresenter.php           # تعديل: حالة "أوقفه المتجر"، شريط المتجر
└── Models/CouponCampaign.php     # تعديل: الحالات الجديدة، isRunning، isLocked()

app/Models/OrderItem.php          # تعديل: is_gift
app/Models/Order.php              # تعديل: خصم/عكس شحن البائع في settleForStatusChange
app/Services/LedgerService.php    # تعديل: قيد couponShipping / عكسه

app/Http/Controllers/
├── Seller/CouponCampaignController.php      # جديد (يحل محل Seller/CouponController القديم المحذوف)
├── Admin/SellerCouponController.php         # جديد: القائمة، الموافقة، الرفض، الإيقاف
├── CouponCollectController.php              # جديد: "احفظ" (ويب)
├── Api/CouponCollectController.php          # جديد: جمع + كوبونات المتجر/المنتج
├── StoreFollowController.php (+Api)         # تعديل: إصدار كوبون المتابعين
├── Customer/CheckoutController.php (+Api)   # تعديل: بند الهدية
└── PublicStoreController / ProductController (+Api)  # تعديل: كوبونات المتجر في الاستجابة

resources/views/
├── seller/coupons/{index,create,edit,show}.blade.php   # إعادة كتابة (القديمة لنظام الرموز)
├── admin/seller-coupons/index.blade.php
├── partials/store-coupons.blade.php                    # شريط "كوبونات المتجر"
└── stores/show + products/show + layouts/seller nav    # تعديل

tests/Feature/SellerCoupons/   # اختبار لكل قصة + المالية + التزامن

trendsham_flutter/lib/
├── services/coupon_service.dart (أو store_service)  # collect + كوبونات المتجر
├── widgets/store_coupons_strip.dart                 # جديد
├── screens/store/store_screen.dart                  # تعديل
└── screens/product/… product detail                 # تعديل
```

**Structure Decision**: نتبع وحدة `app/Coupons` القائمة؛ كل قاعدة في خدمة واحدة يستعملها الموقع والتطبيق. القديم `Seller/CouponController` وصفحاته (نظام الرموز المُلغى، بلا مسارات) يُحذف ويُستبدل.

## Constitution Check (بعد التصميم)

أُعيد الفحص بعد [data-model.md](data-model.md) و[contracts/](contracts/): الهجرة إضافية؛ لا مسار يغيّر قيمة كوبون بعد الجمع؛ كل الخصومات تُسجَّل على الممول. **لا مخالفات.**

## Complexity Tracking

لا توجد مخالفات تستوجب تبريراً.
