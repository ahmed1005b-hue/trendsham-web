<?php

namespace App\Support;

/**
 * كتالوج ألوان ومقاسات المنتجات: المصدر الوحيد للويب وتطبيق الجوال.
 */
final class ProductOptions
{
    /** @var list<array{name: string, hex: string}> */
    private const COLORS = [
        ['name' => 'أسود', 'hex' => '#1A1A1A'],
        ['name' => 'أبيض', 'hex' => '#FFFFFF'],
        ['name' => 'رمادي', 'hex' => '#9E9E9E'],
        ['name' => 'فضي', 'hex' => '#C0C0C0'],
        ['name' => 'كحلي', 'hex' => '#1F2A44'],
        ['name' => 'أزرق', 'hex' => '#1E63C6'],
        ['name' => 'سماوي', 'hex' => '#7EC8E3'],
        ['name' => 'تركواز', 'hex' => '#1ABC9C'],
        ['name' => 'أخضر', 'hex' => '#2E7D32'],
        ['name' => 'زيتي', 'hex' => '#6B6E2E'],
        ['name' => 'كاكي', 'hex' => '#B5A27A'],
        ['name' => 'بيج', 'hex' => '#D8C3A5'],
        ['name' => 'كريمي', 'hex' => '#F3E9D2'],
        ['name' => 'بني', 'hex' => '#6D4C41'],
        ['name' => 'أصفر', 'hex' => '#FBC02D'],
        ['name' => 'ذهبي', 'hex' => '#C9A227'],
        ['name' => 'برتقالي', 'hex' => '#F57C00'],
        ['name' => 'أحمر', 'hex' => '#D32F2F'],
        ['name' => 'خمري', 'hex' => '#7B1E2B'],
        ['name' => 'وردي', 'hex' => '#F48FB1'],
        ['name' => 'فوشيا', 'hex' => '#D81B60'],
        ['name' => 'بنفسجي', 'hex' => '#7B1FA2'],
    ];

    /** @var list<array{key: string, name: string, sizes: list<string>}> */
    private const SIZE_SYSTEMS = [
        ['key' => 'letters', 'name' => 'أحرف (ملابس)', 'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL']],
        ['key' => 'clothing', 'name' => 'أرقام (ملابس)', 'sizes' => ['34', '36', '38', '40', '42', '44', '46', '48', '50', '52', '54', '56']],
        ['key' => 'shoes', 'name' => 'أحذية', 'sizes' => ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46']],
        ['key' => 'jeans', 'name' => 'جينز (الخصر)', 'sizes' => ['26', '27', '28', '29', '30', '31', '32', '33', '34', '36', '38', '40']],
        ['key' => 'kids', 'name' => 'أطفال (بالعمر)', 'sizes' => ['0-3M', '3-6M', '6-12M', '1-2Y', '2-3Y', '3-4Y', '4-5Y', '5-6Y', '7-8Y', '9-10Y', '11-12Y', '13-14Y']],
    ];

    /**
     * @return list<array{name: string, hex: string}>
     */
    public static function colors(): array
    {
        return self::COLORS;
    }

    /**
     * @return list<array{key: string, name: string, sizes: list<string>}>
     */
    public static function sizeSystems(): array
    {
        return self::SIZE_SYSTEMS;
    }

    /**
     * يوحّد كتابة اسم اللون: مسافات، تطويل، تشكيل، همزات، ياء، تاء مربوطة، حروف لاتينية صغيرة.
     */
    public static function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';
        $name = preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $name) ?? '';
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        return mb_strtolower($name);
    }

    public static function isHex(?string $value): bool
    {
        return $value !== null && preg_match('/^#?[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    public static function canonicalHex(string $value): string
    {
        return '#'.strtoupper(ltrim($value, '#'));
    }

    /**
     * لون العرض (#RRGGBB) لقيمة لون محفوظة، أو null إن لم يُعرف.
     */
    public static function hexFor(?string $color, ?string $colorName = null): ?string
    {
        if (self::isHex($color)) {
            return self::canonicalHex($color);
        }

        foreach ([$colorName, $color] as $candidate) {
            if ($candidate === null || trim($candidate) === '') {
                continue;
            }

            $normalized = self::normalizeName($candidate);

            foreach (self::COLORS as $entry) {
                if (self::normalizeName($entry['name']) === $normalized) {
                    return $entry['hex'];
                }
            }
        }

        return null;
    }
}
