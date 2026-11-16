<?php

namespace App\Support;

use App\Models\Product;

/**
 * الحالة الأولى لمحرر الألوان والمقاسات في نموذج منتج البائع.
 *
 * بعد خطأ تحقق تعود مدخلات البائع كما أرسلها (المفتاح والألوان والمقاسات والكميات)،
 * وإلا تُقرأ ألوان المنتج ومقاساته المحفوظة، والمنتج الجديد يبدأ بلا خيارات.
 */
final class ProductOptionsEditorState
{
    /**
     * @param  array<string, mixed>  $old
     * @return array{enabled: bool, rows: list<array{color: ?string, color_name: ?string, size: ?string, sku: ?string, price_adjustment: float|int, stock_quantity: int, original_color: ?string}>}
     */
    public static function make(?Product $product, array $old): array
    {
        if (array_key_exists('variant_options_enabled', $old) || array_key_exists('variant_color', $old) || array_key_exists('variant_size', $old)) {
            return [
                'enabled' => ($old['variant_options_enabled'] ?? '0') === '1',
                'rows' => self::rowsFromOldInput($old),
            ];
        }

        if ($product !== null) {
            $rows = self::rowsFromProduct($product);

            return ['enabled' => $rows !== [], 'rows' => $rows];
        }

        return ['enabled' => false, 'rows' => []];
    }

    /**
     * @param  array<string, mixed>  $old
     * @return list<array{color: ?string, color_name: ?string, size: ?string, sku: ?string, price_adjustment: float|int, stock_quantity: int, original_color: ?string}>
     */
    private static function rowsFromOldInput(array $old): array
    {
        $colors = (array) ($old['variant_color'] ?? []);
        $colorNames = (array) ($old['variant_color_name'] ?? []);
        $sizes = (array) ($old['variant_size'] ?? []);
        $skus = (array) ($old['variant_sku'] ?? []);
        $prices = (array) ($old['variant_price_adjustment'] ?? []);
        $stocks = (array) ($old['variant_stock_quantity'] ?? []);
        $originalColors = (array) ($old['variant_original_color'] ?? []);

        $rows = [];
        foreach (array_unique([...array_keys($colors), ...array_keys($sizes)]) as $i) {
            $color = self::text($colors[$i] ?? null);
            $size = self::text($sizes[$i] ?? null);
            if ($color === null && $size === null) {
                continue;
            }

            $rows[] = [
                'color' => $color,
                'color_name' => self::text($colorNames[$i] ?? null),
                'size' => $size,
                'sku' => self::text($skus[$i] ?? null),
                'price_adjustment' => self::number($prices[$i] ?? 0),
                'stock_quantity' => (int) self::number($stocks[$i] ?? 0),
                'original_color' => self::text($originalColors[$i] ?? null),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{color: ?string, color_name: ?string, size: ?string, sku: ?string, price_adjustment: float|int, stock_quantity: int, original_color: ?string}>
     */
    private static function rowsFromProduct(Product $product): array
    {
        $rows = [];
        foreach ($product->variants as $variant) {
            $color = self::text($variant->color);
            $size = self::text($variant->size);
            if ($color === null && $size === null) {
                continue;
            }

            $rows[] = [
                'color' => $color,
                'color_name' => self::text($variant->color_name),
                'size' => $size,
                'sku' => self::text($variant->sku),
                'price_adjustment' => self::number($variant->price_adjustment / 100),
                'stock_quantity' => (int) $variant->stock_quantity,
                'original_color' => $color,
            ];
        }

        return $rows;
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /** الرقم كما يُكتب في الحقل: 15 لا 15.0 */
    private static function number(mixed $value): float|int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }
}
