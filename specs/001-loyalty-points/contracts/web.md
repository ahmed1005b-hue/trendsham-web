# Web Contract: نقاط الولاء (صفحات الموقع)

## العميل (middleware: auth + role customer)

| المسار | الاسم | الوصف |
|---|---|---|
| GET `/customer/points` | `customer.loyalty.index` | صفحة "نقاطي": الرصيد وقيمته، المعلّقة، تنبيه الانتهاء القريب، السجل مُقسّم لصفحات، نموذج "حوّل إلى كوبون" |
| POST `/customer/points/convert` | `customer.loyalty.convert` | `points` → كوبون في المحفظة، ثم إعادة توجيه مع رسالة نجاح أو أخطاء التحقق |

**تعديلات على صفحات قائمة**:
- **الدفع** (`customer.checkout.show` / `store`): بطاقة "استخدم نقاطي" فيها الرصيد وحقل عدد النقاط (افتراضي 0، أقصى `max_usable`) ويُرسل كـ`use_points`. ملخص الطلب يعرض سطر "خصم النقاط". وسطر "ستكسب X نقاط عند الاستلام".
- **صفحة المنتج والسلة**: `partials/loyalty-earn-hint.blade.php` يعرض "ستكسب X نقاط" أو "أضف Y ل.س لتكسب أول نقطة".
- **قائمة الحساب**: رابط "نقاطي" مع الرصيد.
- **تفاصيل الطلب للعميل**: سطر "خصم النقاط" إن وُجد، و"النقاط المكتسبة" بعد التسليم.

## الإدارة (middleware: auth + role admin)

| المسار | الاسم | الوصف |
|---|---|---|
| GET `/admin/loyalty` | `admin.loyalty.index` | الملخص (FR-013): إجمالي النقاط القائمة وقيمتها المالية، الممنوح والمستبدل والمنتهي هذا الشهر، بحث عن عميل، نموذج الإعدادات |
| PUT `/admin/loyalty/settings` | `admin.loyalty.settings` | تعديل الإعدادات الست (data-model) مع تحقق من القيم |
| GET `/admin/loyalty/customers/{user}` | `admin.loyalty.show` | رصيد وسجل عميل |
| POST `/admin/loyalty/customers/{user}/adjust` | `admin.loyalty.adjust` | `direction` (add/deduct)، `points`، `reason` (إلزامي). الخصم لا يتجاوز الرصيد (FR-012) |

## الإشعارات (`AppNotification`، تظهر في الموقع والتطبيق)

| النوع | المتى | النص |
|---|---|---|
| `loyalty_earned` | عند المنح | "كسبت X نقاط من طلبك {رقم}. رصيدك الآن Y نقطة" |
| `loyalty_expiring` | قبل 30 يوماً | "X من نقاطك تنتهي في {تاريخ}. استخدمها قبل ذلك" |
| `loyalty_reversed` | عند سحب نقاط طلب مرتجع | "سُحبت X نقاط بسبب إرجاع الطلب {رقم}" |
