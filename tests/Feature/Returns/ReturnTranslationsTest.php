<?php

namespace Tests\Feature\Returns;

use App\Support\MessageTranslator;
use Tests\TestCase;

/** كل نص عربي في قسم المرتجعات (رسائل الأخطاء، الإشعارات، التسميات) له ترجمة إنجليزية وتركية (القسم 8). */
class ReturnTranslationsTest extends TestCase
{
    private const FILES = [
        'app/Http/Controllers/Customer/ReturnController.php',
        'app/Http/Controllers/Seller/ReturnController.php',
        'app/Http/Controllers/Admin/ReturnController.php',
        'app/Http/Controllers/Api/ReturnController.php',
        'app/Http/Controllers/Api/Seller/ReturnController.php',
        'app/Http/Resources/ReturnRequestResource.php',
    ];

    /** @return array<string, list<string>> نص ← الملفات */
    private function arabicLiterals(): array
    {
        $files = array_merge(
            glob(app_path('Returns/*.php')),
            glob(app_path('Returns/Models/*.php')),
            array_map(fn (string $path) => base_path($path), self::FILES),
        );

        $literals = [];
        foreach ($files as $file) {
            foreach (token_get_all(file_get_contents($file)) as $token) {
                if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }
                $value = stripslashes(substr($token[1], 1, -1));
                if (preg_match('/\p{Arabic}/u', $value)) {
                    $literals[$value][] = basename($file);
                }
            }
        }

        return $literals;
    }

    public function test_every_arabic_literal_has_english_and_turkish_translations(): void
    {
        $literals = $this->arabicLiterals();
        $this->assertNotEmpty($literals);

        foreach (['en', 'tr'] as $locale) {
            $dictionary = json_decode(file_get_contents(lang_path($locale.'.json')), true);
            $missing = array_keys(array_diff_key($literals, $dictionary));

            $this->assertSame([], $missing, "Missing {$locale} translations");
        }
    }

    public function test_stored_notification_titles_translate_with_the_return_number(): void
    {
        $this->assertSame('New return request RT-AB12CD34', MessageTranslator::translate('طلب إرجاع جديد RT-AB12CD34', 'en'));
        $this->assertSame(
            'Return request RT-AB12CD34 was escalated to administration because the seller did not respond',
            MessageTranslator::translate('طلب إرجاع RT-AB12CD34 أُحيل للإدارة لعدم رد البائع', 'en'),
        );
        $this->assertSame('RT-AB12CD34 numaralı iade talebi kapatıldı', MessageTranslator::translate('أُغلق طلب الإرجاع RT-AB12CD34', 'tr'));
        $this->assertSame('A maximum of 5 photos per request.', MessageTranslator::translate('الحد الأقصى 5 صور لكل طلب.', 'en'));
    }
}
