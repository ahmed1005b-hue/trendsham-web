# Data Model: كوبونات البائع

هجرة واحدة إضافية: `2026_11_18_000000_add_seller_coupon_columns.php`. لا جداول جديدة.

## coupon_campaigns (تعديل)

| العمود | النوع | ملاحظات |
|---|---|---|
| `status` | enum يُوسَّع إلى `pending_review, active, paused, rejected, suspended` | الافتراضي `active` (لا يغيّر كوبونات المنصة) |
| `auto_followers` | boolean, default false | الكوبون القابل للجمع يُرسل أيضاً للمتابعين |
| `buy_product_id` | FK products, nullable, nullOnDelete | الهدية: المنتج الشرط |
| `gift_product_id` | FK products, nullable, nullOnDelete | الهدية: المنتج المجاني |
| `review_note` | string(500), nullable | سبب الرفض/الإيقاف من المدير |
| `reviewed_by` | FK users, nullable, nullOnDelete | |
| `reviewed_at` | timestamp, nullable | |
| `followers_issued_at` | timestamp, nullable | متى أُرسل للمتابعين الحاليين (مرة واحدة) |

**قواعد التحقق لكوبون البائع** (`SellerCampaignWriter::rules()`):
- `type` ∈ percentage, fixed, free_shipping, gift
- percentage: `value` 1..70، `max_discount` إلزامي ≥ 1 ل.س
- fixed: `value` ≥ 1 ل.س
- gift: `buy_product_id` و`gift_product_id` من منتجات المتجر، الهدية بلا ألوان/مقاسات
- `scope` ∈ all, products؛ `product_ids` من منتجات المتجر فقط (إلزامي عند products)
- `audience` ∈ collectable, followers؛ `auto_followers` اختياري مع collectable
- `ends_at` إلزامي، بعد `starts_at` وبعد الآن، ≤ سنة
- `total_limit` إلزامي 1..100000؛ `per_customer_limit` 1..5؛ `valid_days` اختياري 1..365؛ `min_order_amount` ≥ 0

**انتقالات الحالة**:

```text
إنشاء ──(نسبة > 50)──► pending_review ──موافقة──► active
   │                         └──رفض (سبب)──► rejected (نهائي)
   └──(غير ذلك)──► active ◄──البائع──► paused
active/paused ──المدير (سبب)──► suspended (نهائي؛ البائع لا يعيده)
```

- إرسال المتابعين (`followers_issued_at`) يحدث عند أول دخول إلى `active`.
- **قفل التعديل** `isLocked()`: `issued_count > 0` ← يُسمح فقط بتمديد `ends_at` وزيادة `total_limit` و active⇄paused. الحذف فقط حين `issued_count = 0`.
- `isRunning()` الحالي يبقى (status=active + الفترة + متجر approved)، ويُضاف لكوبونات البائع القابلة للجمع: `issued_count < total_limit` شرطاً لإظهار "احفظ".

## coupon_campaign_targets (بلا تغيير)

`target_type = product` لمنتجات النطاق المحدد.

## customer_coupons (بلا تغيير في البنية)

`source` قيم جديدة: `collect`, `followers`. عرض الحالة للعميل: حملة `paused` ← "أوقفه المتجر مؤقتاً"، `suspended` ← "لم يعد متاحاً".

## coupon_redemptions (بلا تغيير)

`shipping_discount` يُستخدم لخصم الشحن من البائع عند التسليم.

## order_items (تعديل)

| العمود | النوع | ملاحظات |
|---|---|---|
| `is_gift` | boolean, default false | بند هدية: `unit_price=0`, `subtotal=0` |

## قيود الدفتر (LedgerService)

| القيد | متى | المصدر (منع التكرار) |
|---|---|---|
| `couponShipping(sellerUserId, amount)` — مدين على البائع | أول تسليم لطلب فيه كوبون شحن مجاني من البائع | `coupon_redemption` + id |
| `couponShippingReversal` — دائن | إلغاء بعد التسليم | `coupon_redemption` + id |

ومعها حركة محفظة البائع (`debit` / `credit` بنوع `coupon_shipping`).

## العلاقات

- `SellerProfile` 1—* `CouponCampaign` (funder=seller)
- `CouponCampaign` *—1 `Product` (buy/gift)
- `SellerProfile` *—* `User` عبر `store_followers` (قائم)
