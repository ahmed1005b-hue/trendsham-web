# API Contract: نقاط الولاء (`/api/v1`، Sanctum، للعميل)

المبالغ في الاستجابات **بالليرة** (مقسومة على 100) كما في بقية واجهة التطبيق.

## GET /api/v1/loyalty

رصيد العميل وملخصه.

```json
{
  "enabled": true,
  "balance": 30,
  "balance_value": 6000,
  "pending": 4,
  "debt": 0,
  "point_value": 200,
  "spend_per_point": 10000,
  "min_redeem": 10,
  "expiring_soon": { "points": 12, "expires_at": "2026-12-05" }
}
```

## GET /api/v1/loyalty/transactions?page=1

سجل الحركات (20 لكل صفحة، الأحدث أولاً).

```json
{
  "data": [
    { "id": 91, "type": "earn", "label": "من الطلب TS-1042", "points": 4,
      "order_number": "TS-1042", "expires_at": "2027-11-12", "created_at": "2026-11-12T10:20:00Z" },
    { "id": 92, "type": "redeem_coupon", "label": "تحويل إلى كوبون 4,000 ل.س", "points": -20,
      "created_at": "2026-11-13T09:00:00Z" }
  ],
  "next_page": 2
}
```

## POST /api/v1/loyalty/convert

تحويل نقاط إلى كوبون في المحفظة.

- **Request**: `{ "points": 20 }`
- **201**: `{ "coupon_id": 77, "value": 4000, "expires_at": "2026-12-13", "balance": 10 }`
- **422**: `{ "message": "تحتاج 10 نقاط على الأقل للاستبدال", "errors": { "points": [...] } }` — أقل من الحد الأدنى، أو أكثر من الرصيد، أو البرنامج متوقف.

## GET /api/v1/checkout/summary (تعديل)

يضاف للاستجابة الحالية:

```json
"loyalty": { "balance": 30, "max_usable": 25, "point_value": 200, "min_redeem": 10, "will_earn": 2 }
```

`max_usable` = أقل من الرصيد و`floor((subtotal − coupon_discount) / point_value)`.

## POST /api/v1/checkout (تعديل)

- **حقل جديد اختياري**: `use_points` (عدد صحيح ≥ 0).
- إذا كان > الرصيد أو أقل من الحد الأدنى → **422** `errors.use_points`.
- إذا تجاوزت قيمته المبلغ بعد الكوبون → يُقصّ إلى `max_usable` (لا خطأ).
- الاستجابة الحالية للطلب تضيف `points_redeemed` و`points_discount`.

## GET /api/v1/products/{product} و GET /api/v1/cart (تعديل)

يضاف `"will_earn_points": 3` داخل `data` للمنتج (FR-011)، وفي السلة على المستوى الأعلى مع `points_spend_per_point` ليحسب التطبيق التلميح عند تغيير الكميات. وفي السلة إن كان 0: `"points_hint": "أضف 2,000 ل.س لتكسب أول نقطة"`.
