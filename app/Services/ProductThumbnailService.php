<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * نسخ WebP مصغّرة لصور المنتجات، تُعرض في البطاقات والقوائم بدل الأصل.
 *
 * - المسار: thumbs/<مسار الأصل بامتداد webp>، مثلاً products/abc.jpg -> thumbs/products/abc.webp
 * - لا تكبير للصور الصغيرة، ولا تغيير على الأصل.
 * - أي فشل (أصل مفقود/تالف) يُتجاهل بصمت: العرض يعود للأصل عبر url().
 */
class ProductThumbnailService
{
    /** عرض يكفي بطاقة منتج على شاشة عالية الكثافة (≈300px × 2). */
    public const MAX_WIDTH = 600;

    private const QUALITY = 80;

    private const DISK = 'public';

    public function pathFor(string $original): string
    {
        $withoutExt = preg_replace('/\.[^.\/]+$/', '', ltrim($original, '/'));

        return 'thumbs/'.$withoutExt.'.webp';
    }

    /** رابط النسخة المصغّرة إن وُجدت، وإلا رابط الأصل. */
    public function url(string $original): string
    {
        $thumb = $this->pathFor($original);

        return asset('storage/'.(Storage::disk(self::DISK)->exists($thumb) ? $thumb : $original));
    }

    public function exists(string $original): bool
    {
        return Storage::disk(self::DISK)->exists($this->pathFor($original));
    }

    /** يُنشئ النسخة المصغّرة. يرجع true عند النجاح. */
    public function generate(string $original): bool
    {
        $disk = Storage::disk(self::DISK);

        try {
            if (! $disk->exists($original)) {
                return false;
            }

            $source = @imagecreatefromstring($disk->get($original));
            if (! $source) {
                return false;
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $targetW = min(self::MAX_WIDTH, $width);
            $targetH = max(1, (int) round($height * $targetW / $width));

            $thumb = imagecreatetruecolor($targetW, $targetH);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

            ob_start();
            $ok = imagewebp($thumb, null, self::QUALITY);
            $data = ob_get_clean();

            if (! $ok || ! $data) {
                return false;
            }

            return $disk->put($this->pathFor($original), $data);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public function delete(string $original): void
    {
        Storage::disk(self::DISK)->delete($this->pathFor($original));
    }
}
