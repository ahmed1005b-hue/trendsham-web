<?php

/*
 * إصدارات تطبيق الهاتف: عند نشر نسخة جديدة على المتجر يُرفع latest،
 * وعندما تصبح نسخة قديمة لا تعمل مع الخادم يُرفع min فيُطلب من أصحابها التحديث إجبارياً.
 */
return [
    'android' => [
        'latest_version' => env('MOBILE_ANDROID_LATEST_VERSION', '1.1.0'),
        'min_version' => env('MOBILE_ANDROID_MIN_VERSION', '1.0.0'),
        'store_url' => env('MOBILE_ANDROID_STORE_URL', 'https://play.google.com/store/apps/details?id=com.example.trendsham'),
    ],
];
