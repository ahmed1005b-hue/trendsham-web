<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * حفظ ألوان ومقاسات المنتج من نموذج البائع (الموقع والتطبيق — نفس المنطق).
 *
 *  - كل (لون + مقاس) يُحدَّث في مكانه، فيبقى رقمه ثابتاً وتبقى سلال العملاء والطلبات مرتبطة به.
 *  - عند الاستبدال: ما لم يُرسَل يُحذف، ويُحذف معه من السلال (بدل أن يتحول لشراء "المنتج بلا لون").
 *  - مخزون المنتج = مجموع مخزون ألوانه ومقاساته المفعّلة.
 */
class ProductVariantSync
{
    public function sync(Request $request, Product $product, bool $replace): void
    {
        // كل شيء أو لا شيء، مع قفل المنتج: شراء متزامن لا يُفسد مطابقة المجموع
        DB::transaction(function () use ($request, $product, $replace) {
            Product::whereKey($product->id)->lockForUpdate()->first();
            $this->apply($request, $product, $replace);
        });
    }

    private function apply(Request $request, Product $product, bool $replace): void
    {
        $rows = $this->rows($request, $product);

        $keep = [];
        $usedSkus = [];
        $renamedColors = [];
        foreach ($rows as $row) {
            $originalColor = $row['original_color'];
            unset($row['original_color']);

            $variant = $this->findVariant($product, $row['color'], $row['size']);

            // لون قديم (كلمة مثل "اسود") استُبدل بدرجة من اللوحة: نحدّث نفس السجل فيبقى رقمه في السلال والطلبات
            if (! $variant && $originalColor !== null && $originalColor !== $row['color']) {
                $variant = $this->findVariant($product, $originalColor, $row['size']);
                if ($variant && $row['color'] !== null) {
                    $renamedColors[$originalColor] = [$row['color'], $row['color_name']];
                }
            }

            // product_variants.sku فريد على مستوى كل المتجر: رمز مكرر يُترك فارغاً بدل خطأ قاعدة بيانات
            if ($row['sku'] !== null) {
                $taken = in_array(strtolower($row['sku']), $usedSkus, true)
                    || ProductVariant::where('sku', $row['sku'])->when($variant, fn ($q) => $q->whereKeyNot($variant->id))->exists();
                $taken ? $row['sku'] = null : $usedSkus[] = strtolower($row['sku']);
            }

            $variant ? $variant->update($row) : $variant = $product->variants()->create($row);
            $keep[] = $variant->id;
        }

        // صور المنتج التي كانت مربوطة باللون القديم تتبع اللون الجديد
        foreach ($renamedColors as $originalColor => [$color, $colorName]) {
            $product->images()->where('color', $originalColor)->update(['color' => $color, 'color_name' => $colorName]);
        }

        if ($replace) {
            $removed = $product->variants()->whereNotIn('id', $keep)->pluck('id');
            if ($removed->isNotEmpty()) {
                CartItem::whereIn('variant_id', $removed)->delete();
                $product->variants()->whereIn('id', $removed)->delete();
            }
        }

        $this->syncProductStock($product);
    }

    private function findVariant(Product $product, ?string $color, ?string $size): ?ProductVariant
    {
        return $product->variants()
            ->where(fn ($q) => $color === null ? $q->whereNull('color') : $q->where('color', $color))
            ->where(fn ($q) => $size === null ? $q->whereNull('size') : $q->where('size', $size))
            ->first();
    }

    /** مخزون المنتج ذي الألوان/المقاسات = مجموعها، مع حركة مخزون تشرح الفرق. */
    public function syncProductStock(Product $product): void
    {
        $variants = $product->variants()->where('is_active', true);
        if (! $variants->exists()) {
            return;
        }

        $sum = (int) $variants->sum('stock_quantity');
        $diff = $sum - (int) $product->fresh()->stock_quantity;
        if ($diff !== 0) {
            $product->adjustStock($diff, 'adjustment', 'مطابقة مخزون المنتج مع مجموع ألوانه ومقاساته');
        }
        $product->refresh();
    }

    private function rows(Request $request, Product $product): array
    {
        $colors = (array) $request->input('variant_color', []);
        $sizes = (array) $request->input('variant_size', []);
        $skus = (array) $request->input('variant_sku', []);
        $adjustments = (array) $request->input('variant_price_adjustment', []);
        $stocks = (array) $request->input('variant_stock_quantity', []);
        $colorNames = (array) $request->input('variant_color_name', []);
        $originalColors = (array) $request->input('variant_original_color', []);

        $rows = [];
        foreach (array_unique([...array_keys($colors), ...array_keys($sizes)]) as $i) {
            $color = trim((string) ($colors[$i] ?? '')) ?: null;
            if ($color !== null && ProductOptions::isHex($color)) {
                $color = ProductOptions::canonicalHex($color);
            }
            $originalColor = trim((string) ($originalColors[$i] ?? '')) ?: null;
            $size = trim((string) ($sizes[$i] ?? '')) ?: null;
            if (! $color && ! $size) {
                continue;
            }
            $sku = trim((string) ($skus[$i] ?? '')) ?: null;
            if ($sku !== null && strcasecmp($sku, (string) $product->sku) === 0) {
                $sku = null; // لا يتعارض مع رمز المنتج نفسه
            }

            // نفس اللون والمقاس مرتين في النموذج: الأخير يفوز
            $rows[($color ?? '').'|'.($size ?? '')] = [
                'color' => $color,
                'original_color' => $originalColor,
                'color_name' => trim((string) ($colorNames[$i] ?? '')) ?: $color,
                'size' => $size,
                'sku' => $sku,
                'price_adjustment' => (int) round(((float) ($adjustments[$i] ?? 0)) * 100),
                'stock_quantity' => max(0, (int) ($stocks[$i] ?? 0)),
                'is_active' => true,
            ];
        }

        return array_values($rows);
    }
}
