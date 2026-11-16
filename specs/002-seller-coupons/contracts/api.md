# API Contract: كوبونات البائع (`/api/v1`)

المبالغ **بالليرة** (مقسومة على 100) كما في بقية واجهة التطبيق.

## كائن "كوبون متجر" (StoreCoupon)

```json
{
  "id": 41,
  "title": "خصم الافتتاح",
  "value_label": "خصم 10% حتى 20,000 ل.س",
  "type": "percentage",
  "min_order_amount": 50000,
  "ends_at": "2026-11-26",
  "scope_label": "كل منتجات المتجر",
  "remaining": 63,
  "state": "collectable"
}
```

`state`: `collectable` (زر احفظ) | `collected` (لدى العميل نسخة متاحة) | `sold_out` | `login_required` (زائر).

## GET /api/v1/stores/{sellerProfile} (تعديل)

يُضاف `"coupons": [StoreCoupon...]` — الحملات الفعّالة القابلة للجمع لهذا المتجر (حتى 10، الأحدث أولاً). `state` يُحسب للعميل المسجّل إن أُرسل التوكن.

## GET /api/v1/products/{product} (تعديل)

يُضاف `data.store_coupons: [StoreCoupon...]` — كوبونات متجر المنتج التي تشمل هذا المنتج.

## POST /api/v1/coupons/{campaign}/collect  (Sanctum، role customer، throttle 30/1)

- **201**: `{ "coupon_id": 512, "expires_at": "2026-11-26", "message": "حُفظ الكوبون في محفظتك" }`
- **409**: `{ "message": "الكوبون في محفظتك أصلاً" }`
- **410**: `{ "message": "نفدت الكوبونات" }` أو `"انتهى الكوبون"`
- **404**: حملة غير موجودة، أو ليست قابلة للجمع، أو متجرها غير نشط.

## POST /api/v1/stores/{sellerProfile}/follow (تعديل)

عند المتابعة يُضاف للاستجابة: `"coupons_received": 1` — عدد كوبونات المتابعين التي وصلت.

## GET /api/v1/coupons (بلا تغيير في الشكل)

نسخ البائع تظهر بـ `source_label: "من متجر X"`، وحالة `campaign_state`: `paused` ← نص "أوقفه المتجر مؤقتاً".

## POST /api/v1/checkout (بلا حقل جديد)

إن كان الكوبون المختار كوبون هدية، يحتوي الطلب في الاستجابة بنداً إضافياً: `{ "product_name": "...", "unit_price": 0, "quantity": 1, "is_gift": true }`.
