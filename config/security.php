<?php

return [
    // المدير لا يدخل لوحته قبل إعداد التحقق بخطوتين (رمز من تطبيق المصادقة على هاتفه)
    'admin_two_factor_required' => (bool) env('ADMIN_2FA_REQUIRED', true),
];
