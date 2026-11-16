<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],
    'channels' => [
        'stack' => ['driver' => 'stack', 'channels' => ['single'], 'ignore_exceptions' => false],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            // يبدأ الملف بـ BOM حتى يقرأه Notepad/PowerShell عربياً لا "ط±ظ…ط²"
            'tap' => [App\Logging\Utf8BomLogFile::class],
        ],
        'null' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class],
    ],
];
