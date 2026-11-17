<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'template' => env('WHATSAPP_TEMPLATE', 'otp_code'),
        'lang' => env('WHATSAPP_LANG', 'ar'),
        'version' => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    'firebase' => [
        // ملف مفتاح حساب الخدمة (سرّي، خارج git). غيابه = الإرسال للهاتف معطّل بهدوء.
        'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/private/firebase-credentials.json'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    // الدخول بـ Google وFacebook (للعملاء فقط). المفاتيح في .env وحدها، لا في الكود.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // معرّفات إضافية لتطبيق Android/iOS مفصولة بفواصل (رمز الهوية يصدر لأحدها)
        'extra_client_ids' => env('GOOGLE_EXTRA_CLIENT_IDS'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
    ],
];
