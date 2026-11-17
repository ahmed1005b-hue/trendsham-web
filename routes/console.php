<?php

use App\Auth\TwoFactor\TwoFactorService;
use App\Models\Showcase;
use App\Models\User;
use App\Services\ShowcaseService;
use App\Services\TwilioWhatsAppService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:wa {phone}', function (string $phone) {
    $ok = app(TwilioWhatsAppService::class)->sendOtp($phone, '123456');
    $this->info($ok ? 'تم الإرسال' : 'فشل الإرسال، راجع storage/logs/laravel.log');
})->purpose('اختبار إرسال واتساب');

Artisan::command('test:twilio', function () {
    $c = config('services.twilio');
    $this->line('SID prefix: '.substr($c['sid'] ?? '', 0, 2).' | length: '.strlen($c['sid'] ?? ''));
    $this->line('TOKEN length: '.strlen($c['token'] ?? ''));
    $this->line('FROM: '.($c['from'] ?? ''));
});

// أقفال المؤقّت (منع التداخل) في الملفات: يعمل المؤقّت حتى لو توقف Redis
Schedule::useCache('file');

// إنهاء العروض المنتهية تلقائياً
Schedule::call(function () {
    $n = Showcase::where('status', 'approved')->whereDate('end_date', '<', today())->update(['status' => 'expired']);
    if ($n) {
        Cache::forget(ShowcaseService::CACHE_KEY);
    }
})->dailyAt('00:05');
// مطابقة رصيد الدفتر مع المحفظة يومياً - تنبيه عند أي فرق
Schedule::command('ledger:reconcile')->dailyAt('03:00');

// مسح بيانات الحسابات التي طلب أصحابها الحذف وانتهت مهلة التراجع (30 يوماً)
Schedule::command('accounts:purge-closed')->dailyAt('04:00')->withoutOverlapping();
// نقاط الولاء: انتهاء الصلاحية وإرجاع كوبونات النقاط المنتهية والتنبيهات
Schedule::command('loyalty:daily')->dailyAt('02:30');
// الإشعارات الجماعية المستحقة (المؤجلة ليلاً إلى 09:00)
Schedule::command('notifications:send-broadcasts')->everyMinute()->withoutOverlapping();
// المرتجعات: تصعيد الطلبات المتأخرة وإغلاق المنتهية مهلتها
Schedule::command('returns:daily')->dailyAt('02:45');
// تذكير السلة المتروكة: كل ساعة نهاراً فقط (لا إزعاج ليلاً)
Schedule::command('cart:remind-abandoned')->hourly()->between('10:00', '21:00')->withoutOverlapping();

// نسخة احتياطية يومية من قاعدة البيانات (تُحفظ 14 يوماً)
Schedule::command('db:backup')->dailyAt('03:30')->withoutOverlapping();

// طوارئ: المدير فقد هاتفه ورموز الاسترداد. يُشغَّل على الخادم فقط (يحتاج دخولاً إلى الجهاز نفسه).
Artisan::command('two-factor:reset {email}', function (string $email) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error('لا يوجد مستخدم بهذا البريد.');

        return 1;
    }
    app(TwoFactorService::class)->disable($user);
    $user->tokens()->delete();
    $this->info('أُلغي التحقق بخطوتين. سيُطلب منه إعداده من جديد عند الدخول.');

    return 0;
})->purpose('إلغاء التحقق بخطوتين لحساب فقد صاحبه هاتفه');
