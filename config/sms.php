<?php

return [
    'driver' => env('SMS_DRIVER', 'log'), // log | twilio | twilio_whatsapp | vonage | clicksend

    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '963'),

    'message' => 'رمز التحقق الخاص بك في تريندشام: :code. لا تشاركه مع أحد.',

    'otp' => [
        'ttl'       => 300,
        'max_tries' => 5,
        'cooldown'  => 60,
    ],

    // الإعدادات القديمة للمحافظة على التوافق الخلفي
    'drivers' => [
        'log' => [],

        'unifonic' => [
            'app_sid' => env('UNIFONIC_APP_SID'),
            'sender_id' => env('UNIFONIC_SENDER_ID'),
        ],

        'twilio' => [
            'sid'          => env('TWILIO_SID'),
            'account_sid'  => env('TWILIO_ACCOUNT_SID'),
            'token'        => env('TWILIO_TOKEN'),
            'from'         => env('TWILIO_FROM'),
        ],

        'clicksend' => [
            'username' => env('CLICKSEND_USERNAME'),
            'api_key'  => env('CLICKSEND_API_KEY'),
            'from'     => env('CLICKSEND_FROM'),
        ],

        'vonage' => [
            'key'    => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from'   => env('VONAGE_FROM'),
        ],
    ],
];
