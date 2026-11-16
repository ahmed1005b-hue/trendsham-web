<?php

namespace App\Support;

class ColorContrast
{
    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
        $f = fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
    }

    public static function ratio(string $a, string $b): float
    {
        $l1 = self::luminance($a);
        $l2 = self::luminance($b);
        [$hi, $lo] = $l1 >= $l2 ? [$l1, $l2] : [$l2, $l1];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /** أقل نسبة تباين بين لون النص وكل ألوان التدرج */
    public static function minRatio(string $text, array $colors): float
    {
        return min(array_map(fn ($c) => self::ratio($text, $c), $colors));
    }

    /** light إذا كان لون النص فاتحاً (أقرب للأبيض)، وإلا dark */
    public static function tone(string $text): string
    {
        return self::ratio($text, '#FFFFFF') <= self::ratio($text, '#111111') ? 'light' : 'dark';
    }

    /** يختار لون النص الأنسب لكل ألوان التدرج. يعيد [light|dark, أقل نسبة تباين]. */
    public static function pick(array $colors): array
    {
        $min = fn (string $text) => min(array_map(fn ($c) => self::ratio($text, $c), $colors));
        $light = $min('#FFFFFF');
        $dark  = $min('#111111');

        return $light >= $dark ? ['light', $light] : ['dark', $dark];
    }
}