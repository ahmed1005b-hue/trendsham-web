<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;

/**
 * يترجم رسالة كتبها السيرفر بالعربية (رسالة API، إشعار مخزَّن، SMS، إشعار هاتف)
 * إلى لغة معيّنة من قاموس lang/{en,tr}.json.
 *
 * - تطابق كامل أولاً.
 * - ثم مفاتيح القاموس التي فيها متغيرات (:order, :store...) كأنماط:
 *   "تحديث على طلبك #1042" تطابق "تحديث على طلبك #:order". قيم المتغيرات
 *   نفسها تُترجم إن كانت عبارات معروفة ("قيد التجهيز" ← "Being prepared").
 * - النص متعدد الأسطر يُترجم سطراً سطراً.
 * - ما لا يُعرف يبقى كما هو (أسماء، ملاحظات كتبها مستخدم).
 */
class MessageTranslator
{
    /** @var array<string, list<array{0: string, 1: list<string>, 2: string}>> */
    private static array $patterns = [];

    public static function translate(?string $text, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        if ($text === null || $locale === 'ar' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        if (str_contains($text, "\n")) {
            return implode("\n", array_map(fn (string $line) => self::translate($line, $locale), explode("\n", $text)));
        }

        return self::line($text, $locale, 2);
    }

    private static function line(string $text, string $locale, int $depth): string
    {
        $trimmed = trim($text);
        if ($trimmed === '' || ! preg_match('/\p{Arabic}/u', $trimmed)) {
            return $text;
        }

        $dictionary = SetLocale::dictionary($locale);
        if (isset($dictionary[$trimmed])) {
            return $dictionary[$trimmed];
        }
        if ($depth === 0) {
            return $text;
        }

        foreach (self::patterns($locale, $dictionary) as [$regex, $names, $translation]) {
            if (preg_match($regex, $trimmed, $match) !== 1) {
                continue;
            }
            $replace = [];
            foreach ($names as $name) {
                $replace[':'.$name] = self::line($match[$name], $locale, $depth - 1);
            }
            // الأطول أولاً حتى لا يستبدل :order جزءاً من :order_number
            uksort($replace, fn ($a, $b) => strlen($b) <=> strlen($a));

            return strtr($translation, $replace);
        }

        return $text;
    }

    /** أنماط المفاتيح ذات المتغيرات، الأكثر تحديداً (نص ثابت أطول) أولاً. */
    private static function patterns(string $locale, array $dictionary): array
    {
        $path = lang_path($locale.'.json');
        $key = $locale.'@'.(is_file($path) ? filemtime($path) : 0).'#'.count($dictionary);
        if (isset(self::$patterns[$key])) {
            return self::$patterns[$key];
        }

        $rows = [];
        foreach ($dictionary as $source => $translation) {
            if (! preg_match_all('/:([a-z_]+)/', $source, $found) || ! preg_match('/\p{Arabic}/u', $source)) {
                continue;
            }
            $names = $found[1];
            if (count($names) !== count(array_unique($names))) {
                continue;
            }
            $parts = preg_split('/:([a-z_]+)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
            $regex = '';
            $literal = 0;
            foreach ($parts as $i => $part) {
                if ($i % 2 === 1) {
                    $regex .= '(?<'.$part.'>.+?)';
                } else {
                    $regex .= preg_quote($part, '/');
                    $literal += mb_strlen(trim($part));
                }
            }
            // نمط بلا نص ثابت عربي كافٍ (":n نقطة" مثلاً) يبقى، لكنه يأتي بعد الأدق
            $rows[] = ['/^'.$regex.'$/u', $names, $translation, $literal];
        }
        usort($rows, fn ($a, $b) => $b[3] <=> $a[3]);

        return self::$patterns[$key] = array_map(fn ($row) => [$row[0], $row[1], $row[2]], $rows);
    }
}
