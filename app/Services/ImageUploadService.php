<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * خدمة موحّدة لرفع الصور بأمان، تُستخدم بكل مكان بالمشروع يرفع صور
 * (منتجات، ستوري، شعار متجر...) بدل ما يتكرر نفس منطق التحقق بكل controller.
 *
 * الحماية المطبّقة هون بتتجاوز مجرد فحص الامتداد:
 * 1. فحص نوع المحتوى الفعلي للملف (مو بس الامتداد المُدَّعى) عبر fileinfo.
 * 2. التأكد إنه الملف صورة صالحة فعلياً عبر getimagesize().
 * 3. **إعادة رسم الصورة من الصفر عبر GD** - هاي أهم خطوة: بتتخلّص من أي بيانات
 *    مخفية أو أكواد ملحقة بنهاية الملف (أسلوب شائع لتهريب ملفات خبيثة داخل
 *    ملف يبدو صورة سليمة وينجح باجتياز فحص الامتداد وgetimagesize العادي)،
 *    وبتشيل بيانات EXIF (يلي ممكن تحتوي معلومات حساسة متل موقع GPS).
 * 4. اسم ملف عشوائي غير قابل للتخمين (40 حرف).
 * 5. حدود أبعاد وحجم تمنع صور ضخمة (decompression bomb) أو تالفة.
 */
class ImageUploadService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const MAX_DIMENSION = 6000;

    private const MIN_DIMENSION = 80;

    /**
     * قواعد التحقق الأولية بالـ Form Request - خط دفاع أول (فحص سريع)،
     * الفحص الحقيقي العميق بيصير بدالة store() تحت.
     */
    public static function validationRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:4096', // كيلوبايت = 4 ميغابايت
            'dimensions:min_width='.self::MIN_DIMENSION.',min_height='.self::MIN_DIMENSION.',max_width='.self::MAX_DIMENSION.',max_height='.self::MAX_DIMENSION,
        ];
    }

    public static function validationMessages(string $field = 'image'): array
    {
        return [
            "{$field}.image" => 'الملف المرفوع ليس صورة صالحة.',
            "{$field}.mimes" => 'صيغة الصورة غير مدعومة - المسموح فقط: JPG, PNG, WEBP.',
            "{$field}.max" => 'حجم الصورة أكبر من الحد المسموح (4 ميغابايت).',
            "{$field}.dimensions" => 'أبعاد الصورة غير مناسبة (الحد الأدنى 80×80، الحد الأقصى 6000×6000).',
        ];
    }

    /**
     * يتحقق من الملف فعلياً، يعيد ترميزه (يشيل أي حمولة خبيثة محتملة)،
     * ويخزّنه باسم عشوائي. يرجع المسار النسبي بالتخزين، أو null لو الملف مرفوض
     * فعلياً رغم اجتيازه للتحقق السطحي بالـ Form Request.
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'public'): ?string
    {
        $reEncoded = $this->verifyAndReEncode($file);

        if ($reEncoded === null) {
            return null;
        }

        [$data, $extension] = $reEncoded;

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;

        Storage::disk($disk)->put($path, $data);

        return $path;
    }

    private function verifyAndReEncode(UploadedFile $file): ?array
    {
        $realPath = $file->getRealPath();
        if (! $realPath || ! is_file($realPath)) {
            return null;
        }

        // 1) نوع المحتوى الفعلي - fileinfo بيقرأ "بصمة" الملف الحقيقية، مو بس
        // الامتداد أو الـ mime type يلي المتصفح مدّعيه بطلب الرفع.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = $finfo ? finfo_file($finfo, $realPath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (! in_array($realMime, self::ALLOWED_MIMES, true)) {
            return null;
        }

        // 2) تأكيد إضافي إنه فعلاً صورة سليمة الحجم
        $info = @getimagesize($realPath);
        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return null;
        }
        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            return null;
        }

        // 3) إعادة الرسم من الصفر عبر GD - يشيل أي بيانات ملحقة/مخفية ويشيل EXIF
        $source = match ($realMime) {
            'image/jpeg' => @imagecreatefromjpeg($realPath),
            'image/png' => @imagecreatefrompng($realPath),
            'image/webp' => @imagecreatefromwebp($realPath),
            default => null,
        };

        if (! $source) {
            return null;
        }

        imagesavealpha($source, true);
        imagealphablending($source, true);

        ob_start();
        $success = match ($realMime) {
            'image/jpeg' => imagejpeg($source, null, 90),
            'image/png' => imagepng($source, null, 6),
            'image/webp' => imagewebp($source, null, 90),
            default => false,
        };
        $data = ob_get_clean();
        imagedestroy($source);

        if (! $success || $data === false || $data === '') {
            return null;
        }

        $extension = match ($realMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };

        return [$data, $extension];
    }
}
