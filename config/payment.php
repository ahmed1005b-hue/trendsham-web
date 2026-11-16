<?php

/*
|--------------------------------------------------------------------------
| الدفع الإلكتروني وتحويل أرباح البائعين
|--------------------------------------------------------------------------
|
| PAYMENT_DRIVER (دفع العملاء):
|   manual  = لا دفع إلكتروني (الدفع عند الاستلام / تأكيد يدوي من الإدارة).
|   sandbox = بنك تجريبي محلي لاختبار كل المسارات بدون مفاتيح (يعمل فقط خارج production).
|   bank    = بوابة البنك الحقيقية (صفحة دفع مستضافة لدى البنك).
|
| PAYOUT_DRIVER (تحويل أرباح البائعين):
|   manual  = المدير يحوّل بنفسه من البنك ثم يسجّل رقم الحوالة.
|   sandbox = تحويل تجريبي ينجح فوراً (للاختبار فقط).
|   bank    = تحويل تلقائي عبر API البنك.
|
| الأمان: الموقع والتطبيق لا يستقبلان رقم البطاقة أو CVV إطلاقاً. البطاقة تُدخل في صفحة البنك،
| ونحن نحفظ فقط "توكن" يعطيه البنك + آخر 4 أرقام + النوع + تاريخ الانتهاء.
|
| عند استلام مفاتيح البنك: املأ متغيرات PAYMENT_GATEWAY_* في .env، وطابق المسارات وأسماء الحقول
| أدناه مع وثائق البنك (كل ما عليه TODO(BANK)) — لا حاجة لتعديل الكود في الغالب.
*/

return [
    'driver' => env('PAYMENT_DRIVER', 'manual'),
    'currency' => env('PAYMENT_CURRENCY', 'SYP'),

    // البنك التجريبي: مسموح افتراضياً فقط في local/testing
    'sandbox_enabled' => filter_var(env('PAYMENT_SANDBOX_ENABLED', false), FILTER_VALIDATE_BOOL),

    'bank' => [
        'url' => env('PAYMENT_GATEWAY_URL'),
        'key' => env('PAYMENT_GATEWAY_KEY'),
        'secret' => env('PAYMENT_GATEWAY_SECRET'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'merchant_id' => env('PAYMENT_GATEWAY_MERCHANT_ID'),
        'timeout' => (int) env('PAYMENT_GATEWAY_TIMEOUT', 20),

        // المبالغ عندنا محفوظة بالوحدة الصغرى (قرش). true = نرسلها كما هي، false = نقسم على 100.
        'amount_in_minor_units' => filter_var(env('PAYMENT_GATEWAY_AMOUNT_MINOR', true), FILTER_VALIDATE_BOOL),

        // TODO(BANK): المسارات حسب وثائق البنك
        'paths' => [
            'checkout' => env('PAYMENT_GATEWAY_CHECKOUT_PATH', '/checkout'),           // صفحة دفع لطلب
            'card_save' => env('PAYMENT_GATEWAY_CARD_SAVE_PATH', '/cards/tokenize'),   // صفحة حفظ بطاقة
            'card_charge' => env('PAYMENT_GATEWAY_CARD_CHARGE_PATH', '/charges'),      // دفع ببطاقة محفوظة
            'card_delete' => env('PAYMENT_GATEWAY_CARD_DELETE_PATH', '/cards/{token}'),
            'refund' => env('PAYMENT_GATEWAY_REFUND_PATH', '/refunds'),
        ],

        // TODO(BANK): أسماء الحقول في ردود البنك وإشعاراته (webhook)
        'fields' => [
            'checkout_url' => env('PAYMENT_GATEWAY_CHECKOUT_URL_FIELD', 'checkout_url'),
            'reference' => env('PAYMENT_GATEWAY_REFERENCE_FIELD', 'reference'),
            'status' => env('PAYMENT_GATEWAY_STATUS_FIELD', 'status'),
            'txn' => env('PAYMENT_GATEWAY_TXN_FIELD', 'transaction_id'),
            'amount' => env('PAYMENT_GATEWAY_AMOUNT_FIELD', 'amount'),
            'event' => env('PAYMENT_GATEWAY_EVENT_FIELD', 'event'),          // payment | card
            'token' => env('PAYMENT_GATEWAY_TOKEN_FIELD', 'card.token'),
            'brand' => env('PAYMENT_GATEWAY_BRAND_FIELD', 'card.brand'),
            'last4' => env('PAYMENT_GATEWAY_LAST4_FIELD', 'card.last4'),
            'exp_month' => env('PAYMENT_GATEWAY_EXP_MONTH_FIELD', 'card.exp_month'),
            'exp_year' => env('PAYMENT_GATEWAY_EXP_YEAR_FIELD', 'card.exp_year'),
            'holder' => env('PAYMENT_GATEWAY_HOLDER_FIELD', 'card.holder_name'),
            'redirect_url' => env('PAYMENT_GATEWAY_3DS_URL_FIELD', 'redirect_url'), // عند طلب تحقق 3-D Secure
        ],

        // TODO(BANK): قيم الحالة عند البنك ← قيمنا
        'status_map' => [
            'success' => 'paid', 'paid' => 'paid', 'captured' => 'paid', 'approved' => 'paid',
            'cancelled' => 'cancelled', 'canceled' => 'cancelled',
            'pending' => 'pending', 'requires_action' => 'pending',
        ],

        // TODO(BANK): توقيع الإشعارات — HMAC-SHA256 على الجسم الخام (الأكثر شيوعاً)
        'signature_header' => env('PAYMENT_GATEWAY_SIGNATURE_HEADER', 'X-Signature'),
        'signature_algo' => env('PAYMENT_GATEWAY_SIGNATURE_ALGO', 'sha256'),
    ],

    'payout' => [
        'driver' => env('PAYOUT_DRIVER', 'manual'),

        // أقل مبلغ للسحب (ل.س)
        'min_amount' => (int) env('PAYOUT_MIN_AMOUNT', 10000),

        // هل يجب أن تتحقق الإدارة من طريقة الاستلام قبل أول سحب؟
        'require_verified_method' => filter_var(env('PAYOUT_REQUIRE_VERIFIED_METHOD', false), FILTER_VALIDATE_BOOL),

        // TODO(BANK): API التحويلات (Payouts / Transfers) إن وفّره البنك
        'bank' => [
            'url' => env('PAYOUT_GATEWAY_URL', env('PAYMENT_GATEWAY_URL')),
            'key' => env('PAYOUT_GATEWAY_KEY', env('PAYMENT_GATEWAY_KEY')),
            'webhook_secret' => env('PAYOUT_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
            'path' => env('PAYOUT_GATEWAY_PATH', '/payouts'),
            'reference_field' => env('PAYOUT_GATEWAY_REFERENCE_FIELD', 'reference'),
            'status_field' => env('PAYOUT_GATEWAY_STATUS_FIELD', 'status'),
            'txn_field' => env('PAYOUT_GATEWAY_TXN_FIELD', 'transfer_id'),
            'reason_field' => env('PAYOUT_GATEWAY_REASON_FIELD', 'failure_reason'),
            'signature_header' => env('PAYOUT_GATEWAY_SIGNATURE_HEADER', 'X-Signature'),
            'timeout' => (int) env('PAYOUT_GATEWAY_TIMEOUT', 30),
        ],

        // الجهات التي يستلم عليها البائع (تظهر كقائمة اختيار). عدّلها كما تريد.
        'providers' => [
            'bank_account' => [
                'بنك سورية الدولي الإسلامي', 'بنك البركة سورية', 'بنك الشام', 'بنك بيمو السعودي الفرنسي',
                'بنك سورية والمهجر', 'بنك عودة سورية', 'المصرف التجاري السوري', 'بنك قطر الوطني سورية',
                'Ziraat Bankası', 'İş Bankası', 'Garanti BBVA', 'Akbank', 'Yapı Kredi', 'Kuveyt Türk',
                'أخرى',
            ],
            'bank_card' => ['Visa', 'Mastercard', 'أخرى'],
            'electronic_wallet' => ['سيريتل كاش', 'MTN كاش', 'شام كاش', 'Papara', 'أخرى'],
        ],
    ],
];
