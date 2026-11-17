<?php

return [
    // مجلد النسخ الاحتياطية (خارج public، لا يصل إليه أحد من المتصفح)
    'path' => env('BACKUP_PATH', storage_path('app/private/backups')),

    // كم يوماً نحتفظ بالنسخ (الأقدم يُحذف تلقائياً)
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    // برنامج mysqldump: على XAMPP ضع المسار الكامل في .env
    // MYSQLDUMP_PATH="C:\xampp\mysql\bin\mysqldump.exe"
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
