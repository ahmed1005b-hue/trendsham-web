<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],
    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
    ],
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    'whatsapp' => [
        'token'    => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'template' => env('WHATSAPP_TEMPLATE', 'otp_code'),
        'lang'     => env('WHATSAPP_LANG', 'ar'),
        'version'  => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    'firebase' => [
        // ملف مفتاح حساب الخدمة (سرّي، خارج git). غيابه = الإرسال للهاتف معطّل بهدوء.
        'credentials' => env('FIREBASE_CREDENTIALS', 'storage/app/private/firebase-credentials.json'),
        'project_id'  => env('FIREBASE_PROJECT_ID'),
    ],
];
