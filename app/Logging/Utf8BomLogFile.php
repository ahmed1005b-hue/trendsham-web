<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\StreamHandler;

/**
 * يبدأ ملف السجل بعلامة UTF-8 (BOM).
 *
 * السجل يُكتب UTF-8 صحيحاً، لكن برامج ويندوز (Notepad، PowerShell Get-Content)
 * تقرأ الملف بلا BOM بترميز النظام العربي cp1256، فيظهر "رمز" كـ "ط±ظ…ط²".
 * مع الـ BOM تتعرّف عليه كل البرامج كـ UTF-8.
 */
class Utf8BomLogFile
{
    private const BOM = "\xEF\xBB\xBF";

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            if ($handler instanceof StreamHandler && is_string($url = $handler->getUrl())) {
                self::ensureBom($url);
            }
        }
    }

    public static function ensureBom(string $path): void
    {
        if (str_contains($path, '://') && ! str_starts_with($path, 'file://')) {
            return;
        }
        $path = preg_replace('#^file://#', '', $path);

        if (! is_file($path) || filesize($path) === 0) {
            @file_put_contents($path, self::BOM);

            return;
        }

        $handle = @fopen($path, 'rb');
        $head = $handle ? fread($handle, 3) : self::BOM;
        if ($handle) {
            fclose($handle);
        }
        if ($head !== self::BOM) {
            // ملف قديم بلا BOM: نضيفها مرة واحدة في أوله
            @file_put_contents($path, self::BOM.file_get_contents($path), LOCK_EX);
        }
    }
}
