<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // بيئة التطوير: نسمح لأي مصدر. عند الإطلاق الفعلي، حدّد نطاق موقعك/تطبيقك
    // بدل '*' لأسباب أمنية (مثلاً: ['https://trendsham.com']).
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
