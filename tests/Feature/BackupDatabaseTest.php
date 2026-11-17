<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups');
        File::deleteDirectory($this->dir);
        config(['backup.path' => $this->dir, 'backup.keep_days' => 14]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** mysqldump وهمي يكتب [content] إلى --result-file كما يفعل الحقيقي. */
    private function fakeDump(?string $content, int $exitCode = 0, string $error = ''): void
    {
        Process::fake(function (PendingProcess $process) use ($content, $exitCode, $error) {
            $target = collect((array) $process->command)
                ->first(fn ($arg) => str_starts_with((string) $arg, '--result-file='));
            if ($content !== null && $target) {
                File::put(substr($target, strlen('--result-file=')), $content);
            }

            return Process::result(errorOutput: $error, exitCode: $exitCode);
        });
    }

    public function test_complete_dump_is_saved_compressed_without_password_in_arguments(): void
    {
        config(['database.connections.mysql.password' => 's3cret']);
        $this->fakeDump("CREATE TABLE users (id int);\n-- Dump completed on 2026-11-17\n");

        $this->artisan('db:backup')->assertSuccessful();

        $files = File::glob($this->dir.'/*.sql.gz');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('CREATE TABLE users', gzdecode(file_get_contents($files[0])));
        $this->assertSame($files, File::glob($this->dir.'/*'), 'no temporary files are left behind');
        Process::assertRan(fn ($process) => ! str_contains(implode(' ', (array) $process->command), 's3cret')
            && ($process->environment['MYSQL_PWD'] ?? null) === 's3cret');
    }

    public function test_failed_or_truncated_dump_leaves_no_file(): void
    {
        $this->fakeDump('CREATE TABLE half');
        $this->artisan('db:backup')->assertFailed();

        $this->fakeDump(null, exitCode: 2, error: 'Access denied');
        $this->artisan('db:backup')->assertFailed();

        $this->assertSame([], File::glob($this->dir.'/*'));
    }

    public function test_old_backups_are_pruned_but_newest_three_are_kept(): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach (range(1, 5) as $i) {
            $old = $this->dir."/trendsham-old-$i.sql.gz";
            File::put($old, 'x');
            touch($old, now()->subDays(30 + $i)->getTimestamp());
        }
        $this->fakeDump("-- Dump completed\n");

        $this->artisan('db:backup')->assertSuccessful();

        // النسخة الجديدة + أحدث نسختين قديمتين (المجموع 3)، والباقي حُذف
        $this->assertCount(3, File::glob($this->dir.'/*.sql.gz'));
        $this->assertFileExists($this->dir.'/trendsham-old-1.sql.gz');
        $this->assertFileDoesNotExist($this->dir.'/trendsham-old-5.sql.gz');
    }

    public function test_backup_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'db:backup'));

        $this->assertNotNull($event);
        $this->assertSame('30 3 * * *', $event->expression);
    }
}
