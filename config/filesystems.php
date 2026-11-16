<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        // ملفات المستندات الصادرة برمز تحقق QR (خاصة، خارج git). الاختبارات توجّهها لمجلد مؤقت.
        'documents' => [
            'driver' => 'local',
            'root' => env('DOCUMENTS_ROOT') ? base_path(env('DOCUMENTS_ROOT')) : storage_path('app/private/documents'),
            'throw' => false,
        ],
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],
        'identity_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private/identity-documents'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
