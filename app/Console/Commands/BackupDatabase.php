<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * نسخة احتياطية مضغوطة من قاعدة البيانات (mysqldump → .sql.gz)، وحذف ما تجاوز مدة الاحتفاظ.
 * mysqldump يكتب مباشرة إلى ملف (لا يُحمَّل التفريغ كله في الذاكرة)، ثم يُضغط على دفعات.
 * كلمة السر تُمرَّر عبر متغير البيئة MYSQL_PWD لا في سطر الأوامر (لا تظهر في قائمة العمليات).
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'نسخة احتياطية مضغوطة من قاعدة البيانات مع حذف النسخ القديمة';

    public function handle(): int
    {
        $db = config('database.connections.'.config('database.default'));
        $dir = config('backup.path');
        File::ensureDirectoryExists($dir);

        $file = $dir.DIRECTORY_SEPARATOR.$db['database'].'-'.now()->format('Y-m-d_His').'.sql.gz';
        $sql = $file.'.sql.part';

        $result = Process::forever()
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                config('backup.mysqldump'),
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--user='.$db['username'],
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $db['database'],
            ]);

        // النسخة الكاملة فقط تُعتمد: mysqldump يختم الملف بـ «Dump completed»
        if ($result->failed() || ! is_file($sql) || ! $this->isComplete($sql)) {
            File::delete($sql);
            Log::error('db:backup failed', ['error' => trim($result->errorOutput())]);
            $this->error('فشل النسخ الاحتياطي: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->compress($sql, $file);
        File::delete($sql);
        $removed = $this->prune($dir);

        $this->info(sprintf('تمت النسخة: %s (%s KB). حُذفت %d نسخة قديمة.', basename($file), number_format(filesize($file) / 1024), $removed));

        return self::SUCCESS;
    }

    private function isComplete(string $path): bool
    {
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, filesize($path) - 4096));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        return str_contains($tail, 'Dump completed');
    }

    private function compress(string $from, string $to): void
    {
        $partial = $to.'.part';
        $in = fopen($from, 'rb');
        $out = gzopen($partial, 'wb6');
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }
        fclose($in);
        gzclose($out);
        File::move($partial, $to);
    }

    /** يحذف النسخ الأقدم من مدة الاحتفاظ، ويُبقي دائماً أحدث 3 نسخ مهما كان عمرها. */
    private function prune(string $dir): int
    {
        $files = collect(File::glob($dir.DIRECTORY_SEPARATOR.'*.sql.gz'))
            ->sortByDesc(fn ($f) => filemtime($f))
            ->values();
        $cutoff = now()->subDays(config('backup.keep_days'))->getTimestamp();

        $old = $files->slice(3)->filter(fn ($f) => filemtime($f) < $cutoff);
        $old->each(fn ($f) => File::delete($f));

        return $old->count();
    }
}
