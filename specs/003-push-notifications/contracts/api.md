# API Contract: الإشعارات المنبثقة (`/api/v1`، Sanctum)

## POST /api/v1/push/devices

تسجيل الهاتف بعد الدخول (ولكل تحديث للتوكن من Firebase).

- **Request**: `{ "token": "fcm-token…", "platform": "android", "app_version": "1.4.0" }`
- **200**: `{ "registered": true }`
- **422**: token مفقود، أو platform ليست android|ios.
- إن كان التوكن مسجلاً لحساب آخر ينتقل لهذا الحساب.

## DELETE /api/v1/push/devices

عند تسجيل الخروج (قبل حذف توكن الدخول).

- **Request**: `{ "token": "fcm-token…" }`
- **204** دائماً (حتى لو لم يكن مسجلاً). يحذف فقط إن كان لهذا المستخدم.

## GET /api/v1/notification-settings

```json
{
  "role": "customer",
  "groups": [
    { "key": "orders", "label": "الطلبات والدفع والشحن", "description": "تأكيد الطلب، الشحن، التسليم، الدفع", "enabled": true, "locked": false },
    { "key": "messages", "label": "الرسائل والردود", "description": "رسائل المتاجر والدعم وأجوبة أسئلتك", "enabled": true, "locked": false },
    { "key": "coupons_points", "label": "الكوبونات والنقاط", "description": "كوبون جديد في محفظتك وتنبيه انتهاء النقاط", "enabled": true, "locked": false },
    { "key": "offers", "label": "العروض والمنتجات الجديدة", "description": "من المتاجر التي تتابعها، حتى 3 يومياً ولا شيء ليلاً", "enabled": null, "locked": false }
  ],
  "always_on_note": "إشعارات الأمان والمال المهمة تصلك دائماً.",
  "offers_prompted": false
}
```

`enabled: null` = لم يُحدَّد (لا يُرسل). البائع يرى مجموعاته (`seller_orders`, `seller_messages`, `seller_store`, `seller_payouts`, `seller_coupons`).

## PUT /api/v1/notification-settings

- **Request**: `{ "groups": { "offers": true, "orders": false } }` — مفاتيح من مجموعات دور المستخدم فقط.
- **200**: نفس شكل GET بعد الحفظ. أي تحديث لـ `offers` يضبط `offers_prompted_at`.
- **422**: مفتاح غير معروف لهذا الدور.

## POST /api/v1/stores/{id}/follow (تعديل)

يُضاف `"ask_offers_opt_in": true` عند المتابعة إذا كان العميل لم يُسأل عن العروض بعد (مرة واحدة)، وإلا `false`.

## GET /api/v1/notifications (تعديل)

كل عنصر يضيف `"group"` و`"target": {"screen": "order", "id": 14}` (أو null) ليفتح الجرس الشاشة نفسها.

## رسالة Firebase (ما يستقبله التطبيق)

```json
{
  "notification": { "title": "طلبك TS-20261112-3674BA تم شحنه", "body": "…" },
  "data": { "notification_id": "981", "type": "order_status_changed", "screen": "order", "id": "14" },
  "android": { "priority": "high", "notification": { "channel_id": "trendsham_default" } }
}
```

قيم `screen`: order, seller_order, conversation, product, store, coupons, points, seller_wallet, seller_coupon, seller_products, notifications.
