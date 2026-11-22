<?php

namespace App\Shipping;

/** المحافظات السورية الأربع عشرة بأسمائها المعتمدة في الموقع. */
final class Governorates
{
    public const ALL = [
        'دمشق', 'ريف دمشق', 'حلب', 'حمص', 'حماة', 'اللاذقية', 'طرطوس',
        'إدلب', 'درعا', 'السويداء', 'القنيطرة', 'دير الزور', 'الرقة', 'الحسكة',
    ];

    /** أسماء أخرى تُكتب بها بعض المحافظات أو أشهر مدنها. */
    private const ALIASES = [
        'ريف دمشق' => ['ريف دمشق', 'الريف', 'جرمانا', 'صحنايا', 'دوما', 'قدسيا', 'التل', 'الزبداني', 'يبرود', 'النبك', 'داريا', 'المعضمية', 'الكسوة'],
        'اللاذقية' => ['اللاذقيه', 'جبلة', 'جبله'],
        'طرطوس' => ['بانياس', 'صافيتا'],
        'حماة' => ['حماه', 'سلمية', 'مصياف', 'السلمية'],
        'إدلب' => ['ادلب'],
        'دير الزور' => ['ديرالزور', 'دير الزور', 'الميادين', 'البوكمال'],
        'الحسكة' => ['الحسكه', 'القامشلي', 'المالكية'],
        'الرقة' => ['الرقه'],
        'السويداء' => ['السويدا', 'شهبا'],
        'القنيطرة' => ['القنيطره'],
        'درعا' => ['نوى', 'ازرع', 'إزرع'],
    ];

    public static function isValid(?string $name): bool
    {
        return $name !== null && in_array($name, self::ALL, true);
    }

    /** يخمّن المحافظة من نص العنوان (مدينة العنوان المحفوظ أو العنوان المكتوب). */
    public static function guess(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        // «ريف دمشق» قبل «دمشق» حتى لا يُطابق الأقصر أولاً
        foreach (self::ALIASES['ريف دمشق'] as $alias) {
            if (str_contains($text, $alias)) {
                return 'ريف دمشق';
            }
        }
        foreach (self::ALL as $name) {
            if (str_contains($text, $name)) {
                return $name;
            }
        }
        foreach (self::ALIASES as $name => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($text, $alias)) {
                    return $name;
                }
            }
        }

        return null;
    }
}
