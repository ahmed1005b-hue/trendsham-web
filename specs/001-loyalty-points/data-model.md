# Data Model: نظام نقاط الولاء

كل التغييرات **إضافية**: جداول جديدة وأعمدة جديدة، ولا تعديل على أعمدة قائمة.

## loyalty_accounts (جديد) — رصيد العميل

| الحقل | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| user_id | FK users، فريد | حساب واحد لكل عميل، يُنشأ عند أول حركة |
| balance | int ≥ 0 | النقاط القابلة للاستخدام الآن (مجموع `remaining` للدفعات غير المنتهية) |
| debt | int ≥ 0 | نقاط مستحقة على العميل من سحب نقاط مصروفة؛ تُسدَّد أولاً من أي كسب قادم |
| lifetime_earned | int | للعرض والإحصاء |
| timestamps | | |

**القاعدة**: كل تعديل على `balance`/`debt` يتم داخل معاملة مع `lockForUpdate()` على هذا الصف.

## loyalty_transactions (جديد) — السجل الدائم (FR-009)

| الحقل | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| user_id | FK users | |
| type | enum | `earn`, `restore`, `admin_add`, `redeem_checkout`, `redeem_coupon`, `reverse_earn`, `expire`, `admin_deduct`, `debt_settle` |
| points | int (موجب للإضافة، سالب للخصم) | |
| remaining | int null | للدفعات فقط (`earn`, `restore`, `admin_add`): ما بقي منها |
| expires_at | timestamp null | للدفعات: تاريخ الكسب + 12 شهراً |
| expiry_notified_at | timestamp null | منع تكرار تنبيه الانتهاء |
| order_id | FK orders null | |
| customer_coupon_id | FK customer_coupons null | لحركات التحويل لكوبون والإرجاع منه |
| admin_id | FK users null | للتعديل اليدوي (FR-012) |
| reason | string null | إلزامي للتعديل اليدوي |
| created_at | | |

**فهارس وقيود**:
- `UNIQUE(type, order_id)` لأنواع `earn`, `reverse_earn`, `redeem_checkout` → المنح والسحب مرة واحدة لكل طلب حتى مع الضغط المزدوج.
- `INDEX(user_id, expires_at)` للدفعات وFIFO.
- `INDEX(customer_coupon_id)`.

## loyalty_allocations (جديد) — من أي دفعة أُخذت كل نقطة

| الحقل | النوع | ملاحظات |
|---|---|---|
| id | PK | |
| debit_transaction_id | FK loyalty_transactions | حركة الاستهلاك |
| lot_transaction_id | FK loyalty_transactions | الدفعة التي استُهلك منها |
| points | int > 0 | |

**الاستخدام**: عند إرجاع نقاط (إلغاء طلب استُبدلت فيه، أو انتهاء كوبون نقاط) تُعاد النقاط إلى نفس الدفعات إن لم تنتهِ، والمنتهي منها يُسجَّل `expire`.

## orders (أعمدة جديدة)

| الحقل | النوع | ملاحظات |
|---|---|---|
| points_redeemed | int default 0 | النقاط المستبدلة عند الدفع |
| points_discount | bigint default 0 | قيمتها بالوحدة الصغرى (×100) = `points_redeemed × point_value` |

`total = subtotal + shipping_fee − discount_amount − points_discount`. لا يدخل `points_discount` في `OrderItem::settlementBase()`.

## platform_settings (أعمدة جديدة)

| الحقل | الافتراضي | المعنى |
|---|---|---|
| loyalty_enabled | true | تشغيل/إيقاف البرنامج |
| loyalty_spend_per_point | 1,000,000 | 10,000 ل.س ×100 لكل نقطة |
| loyalty_point_value | 20,000 | 200 ل.س ×100 للنقطة |
| loyalty_min_redeem | 10 | أقل عدد نقاط للاستبدال |
| loyalty_expiry_months | 12 | صلاحية الدفعة |
| loyalty_coupon_valid_days | 30 | صلاحية كوبون النقاط |

## الكيانات القائمة المُعاد استخدامها

- **coupon_campaigns**: حملة نظامية لكل قيمة كوبون نقاط: `funder=platform`, `type=fixed`, `audience=specific`, `trigger='loyalty_points'`.
- **customer_coupons**: كوبون النقاط بـ`source='loyalty'`.

## انتقالات الحالة

```text
طلب جديد ── (use_points) ──► redeem_checkout  (الرصيد ↓)
    │
    ├─ cancelled قبل التسليم ──► restore إلى الدفعات الأصلية
    │
    └─ delivered ──► earn (دفعة جديدة، تسدّد debt أولاً)
                        │
                        └─ cancelled بعد التسليم (إرجاع) ──► reverse_earn
                                (إن لم يكفِ الرصيد: الباقي يصبح debt)

نقاط ── convert ──► redeem_coupon + كوبون في المحفظة
                        ├─ استُخدم في طلب ثم أُلغي ──► الكوبون يعود للمحفظة (النظام الحالي)
                        └─ انتهى دون استخدام ──► restore (loyalty:daily)

دفعة ── تجاوزت expires_at ──► expire (loyalty:daily)
```

## قواعد التحقق

- لا استبدال بأقل من `loyalty_min_redeem` أو بأكثر من `balance`.
- خصم النقاط ≤ `subtotal − discount_amount` (لا يصبح الطلب سالباً، والشحن لا يُغطّى بالنقاط).
- العميل فقط يكسب؛ الحساب الموقوف لا يستبدل.
- التعديل اليدوي: `reason` إلزامي، والخصم لا يتجاوز `balance`.
- ثابت دائم: `balance = Σ remaining (دفعات غير منتهية)` و`Σ points (كل الحركات) = balance − debt`.
