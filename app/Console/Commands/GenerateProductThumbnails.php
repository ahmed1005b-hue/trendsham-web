<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Models\Story;
use App\Services\ProductThumbnailService;
use Illuminate\Console\Command;

/**
 * يولّد نسخ WebP المصغّرة لصور المنتجات والقصص الموجودة (الجديدة تُولَّد تلقائياً عند الرفع).
 */
class GenerateProductThumbnails extends Command
{
    protected $signature = 'products:thumbnails {--force : أعد توليد النسخ الموجودة أيضاً}';

    protected $description = 'Generate WebP thumbnails for product and story images that do not have one yet';

    public function handle(ProductThumbnailService $thumbs): int
    {
        $made = $skipped = $failed = 0;
        $process = function (?string $path) use ($thumbs, &$made, &$skipped, &$failed) {
            if (! $path) {
                return;
            }
            if (! $this->option('force') && $thumbs->exists($path)) {
                $skipped++;

                return;
            }
            $thumbs->generate($path) ? $made++ : $failed++;
        };

        ProductImage::query()->select(['id', 'path'])->chunkById(200, fn ($rows) => $rows->each(fn ($r) => $process($r->path)));
        Story::query()->select(['id', 'image'])->chunkById(200, fn ($rows) => $rows->each(fn ($r) => $process($r->image)));

        $this->info("generated: {$made}, already present: {$skipped}, failed/missing original: {$failed}");

        return self::SUCCESS;
    }
}
