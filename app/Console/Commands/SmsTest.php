<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsService;
use Illuminate\Console\Command;

/**
 * تجربة بوابة الرسائل: php artisan sms:test 0933123456
 */
class SmsTest extends Command
{
    protected $signature = 'sms:test {phone : رقم المستلم} {--message= : نص الرسالة}';

    protected $description = 'إرسال رسالة تجريبية عبر بوابة الرسائل المضبوطة في SMS_DRIVER';

    public function handle(SmsService $sms): int
    {
        $driver = config('sms.driver');
        $message = $this->option('message') ?: 'رسالة تجريبية من تريندشام ✅';

        $this->info("البوابة الحالية: {$driver}");

        if ($sms->send($this->argument('phone'), $message)) {
            $this->info($driver === 'log'
                ? 'تمت كتابة الرسالة في storage/logs/laravel.log (لم تُرسل فعلياً لأن SMS_DRIVER=log).'
                : 'تم الإرسال بنجاح.');

            return self::SUCCESS;
        }

        $this->error('فشل الإرسال. راجع storage/logs/laravel.log لمعرفة السبب.');

        return self::FAILURE;
    }
}
