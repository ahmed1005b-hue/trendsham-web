<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ImageUploadService;
use App\Services\ProductVariantSync;
use App\Support\ProductOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private readonly ImageUploadService $imageUploadService) {}

    public function index(Request $request)
    {
        $products = $request->user()->sellerProfile
            ->products()
            ->with('primaryImage', 'category')
            ->latest()
            ->paginate(20);

        return ProductResource::collection($products);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        abort_unless($vendor?->isApproved(), 403, 'يجب اعتماد متجرك من الإدارة أولاً.');

        $validated = $this->validateProduct($request);

        $product = $vendor->products()->create([
            ...$validated,
            'weight_grams' => Product::gramsFromKg($validated['weight_kg'] ?? null),
            'slug' => Str::slug($validated['name']).'-'.Str::random(6),
            'price' => (int) round($validated['price'] * 100),
            'compare_at_price' => $validated['compare_at_price'] ? (int) round($validated['compare_at_price'] * 100) : null,
        ]);

        if ($product->stock_quantity > 0) {
            $product->inventoryMovements()->create([
                'quantity_change' => $product->stock_quantity,
                'reason' => 'restock',
                'quantity_after' => $product->stock_quantity,
                'note' => 'رصيد افتتاحي عند إنشاء المنتج',
            ]);
        }

        $this->storeImages($request, $product);
        app(ProductVariantSync::class)->sync($request, $product, false);

        return new ProductResource($product->load('images', 'category', 'variants'));
    }

    public function update(Request $request, Product $product)
    {
        Gate::authorize('manage', $product);

        $validated = $this->validateProduct($request, $product->id);
        $isBeingReactivated = $product->status !== 'active' && $validated['status'] === 'active';
        $stockDiff = $validated['stock_quantity'] - $product->stock_quantity;

        $product->update([
            ...$validated,
            'weight_grams' => array_key_exists('weight_kg', $validated) ? Product::gramsFromKg($validated['weight_kg']) : $product->weight_grams,
            'price' => (int) round($validated['price'] * 100),
            'compare_at_price' => $validated['compare_at_price'] ? (int) round($validated['compare_at_price'] * 100) : null,
        ]);

        if ($isBeingReactivated) {
            $product->update(['admin_approved_at' => null, 'rejection_note' => null]);
        }

        if ($stockDiff !== 0) {
            $product->inventoryMovements()->create([
                'quantity_change' => $stockDiff,
                'reason' => 'adjustment',
                'quantity_after' => $product->stock_quantity,
                'note' => 'تعديل يدوي عبر التطبيق',
            ]);
        }

        $this->storeImages($request, $product);
        app(ProductVariantSync::class)->sync($request, $product, $request->boolean('replace_variants') || $request->has('variant_size') || $request->has('variant_color'));

        return new ProductResource($product->load('images', 'category', 'variants'));
    }

    public function destroy(Request $request, Product $product)
    {
        Gate::authorize('manage', $product);
        $product->delete();

        return response()->json(['message' => 'تم حذف المنتج.']);
    }

    /**
     * إدخال كمية سريع: إضافة/تعديل رصيد المخزون دون فتح نموذج تعديل المنتج كامل.
     * {change} موجب = إضافة، سالب = خصم. {set} تعيين رصيد مطلق.
     */
    public function quickStock(Request $request, Product $product)
    {
        Gate::authorize('manage', $product);

        // مخزون منتج له ألوان/مقاسات = مجموعها؛ تعديله مباشرة يفصله عن الكميات الحقيقية
        if ($product->variants()->where('is_active', true)->exists()) {
            return response()->json(['message' => 'لهذا المنتج ألوان ومقاسات: عدّل كمية كل لون/مقاس من شاشة تعديل المنتج.'], 422);
        }

        $validated = $request->validate([
            'change' => ['required_without:set', 'integer', 'min:-100000'],
            'set' => ['required_without:change', 'integer', 'min:0'],
        ]);

        $oldQuantity = $product->stock_quantity;

        if (isset($validated['set'])) {
            $newQuantity = max(0, (int) $validated['set']);
            $change = $newQuantity - $oldQuantity;
        } else {
            $change = (int) $validated['change'];
            $newQuantity = max(0, $oldQuantity + $change);
        }

        if ($change === 0) {
            return response()->json([
                'stock_quantity' => $product->stock_quantity,
                'is_low_stock' => $product->isLowStock(),
                'message' => 'لا تغيير.',
            ]);
        }

        $product->update(['stock_quantity' => $newQuantity]);
        $product->inventoryMovements()->create([
            'quantity_change' => $change,
            'reason' => $change > 0 ? 'restock' : 'adjustment',
            'quantity_after' => $newQuantity,
            'note' => $change > 0 ? 'إدخال كمية سريع من التطبيق' : 'خصم كمية سريع من التطبيق',
        ]);

        return response()->json([
            'stock_quantity' => $newQuantity,
            'is_low_stock' => $product->isLowStock(),
            'message' => $change > 0 ? 'تمت إضافة المخزون.' : 'تم خصم الكمية.',
        ]);
    }

    public function destroyImage(Request $request, Product $product, int $imageId)
    {
        Gate::authorize('manage', $product);

        $image = $product->images()->findOrFail($imageId);
        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete($image->path);
        $image->delete();

        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'تم حذف الصورة.']);
    }

    public function setPrimaryImage(Request $request, Product $product, int $imageId)
    {
        Gate::authorize('manage', $product);

        $image = $product->images()->findOrFail($imageId);
        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return response()->json(['message' => 'تم تعيينها كصورة رئيسية.']);
    }

    /**
     * قائمة المنتجات التي وصلت حد المخزون المنخفض (لتنبيه البائع).
     */
    public function lowStock(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        abort_unless($vendor, 404);

        $products = $vendor->products()
            ->where('status', 'active')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->with('primaryImage')
            ->get(['id', 'name', 'slug', 'stock_quantity', 'low_stock_threshold'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'stock_quantity' => $p->stock_quantity,
                'low_stock_threshold' => $p->low_stock_threshold,
                'image' => $p->primaryImage ? asset('storage/'.$p->primaryImage->path) : null,
            ]);

        return response()->json(['data' => $products]);
    }

    private function validateProduct(Request $request, ?int $ignoreProductId = null): array
    {
        return $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'.($ignoreProductId ? ",{$ignoreProductId}" : '')],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:1000'], // للشحن: طرد 10 كغ فأكثر أجرته أعلى
            'status' => ['required', 'in:draft,active,inactive'],
            'images.*' => ImageUploadService::validationRules(required: false),
            'image_color' => ['nullable', 'array'],
            'image_color.*' => ['nullable', 'string', 'max:50'],
            'image_color_name' => ['nullable', 'array'],
            'image_color_name.*' => ['nullable', 'string', 'max:50'],
            'existing_image_color' => ['nullable', 'array'],
            'existing_image_color.*' => ['nullable', 'string', 'max:50'],
            'existing_image_color_name' => ['nullable', 'array'],
            'existing_image_color_name.*' => ['nullable', 'string', 'max:50'],
            'replace_variants' => ['nullable', 'boolean'],
            'variant_color' => ['nullable', 'array'],
            'variant_color.*' => ['nullable', 'string', 'max:50'],
            'variant_color_name' => ['nullable', 'array'],
            'variant_color_name.*' => ['nullable', 'string', 'max:50'],
            'variant_size' => ['nullable', 'array'],
            'variant_size.*' => ['nullable', 'string', 'max:50'],
            'variant_sku' => ['nullable', 'array'],
            'variant_sku.*' => ['nullable', 'string', 'max:100'],
            'variant_original_color' => ['nullable', 'array'],
            'variant_original_color.*' => ['nullable', 'string', 'max:50'],
            'variant_price_adjustment' => ['nullable', 'array'],
            'variant_price_adjustment.*' => ['nullable', 'numeric', 'min:0'],
            'variant_stock_quantity' => ['nullable', 'array'],
            'variant_stock_quantity.*' => ['nullable', 'integer', 'min:0'],
        ], ImageUploadService::validationMessages('images.*'));
    }

    private function canonicalImageColor(string $color): string
    {
        $color = trim($color);

        return ProductOptions::isHex($color) ? ProductOptions::canonicalHex($color) : $color;
    }

    private function storeImages(Request $request, Product $product): void
    {
        $colors = (array) $request->input('image_color', []);
        $colorNames = (array) $request->input('image_color_name', []);
        foreach ((array) $request->input('existing_image_color', []) as $imgId => $c) {
            $name = $request->input("existing_image_color_name.$imgId");
            $img = $product->images()->whereKey($imgId)->first();
            $c = $this->canonicalImageColor((string) $c);
            if ($img) {
                $img->update(['color' => $c ?: null, 'color_name' => trim((string) $name) ?: ($c ?: null)]);
            }
        }
        if (! $request->hasFile('images')) {
            return;
        }

        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $nextOrder = (int) $product->images()->max('sort_order');
        $savedCount = 0;

        foreach ($request->file('images') as $idx => $file) {
            $path = $this->imageUploadService->store($file, 'products');
            if ($path === null) {
                continue;
            }
            $color = $this->canonicalImageColor((string) ($colors[$idx] ?? '')) ?: null;
            $colorName = trim((string) ($colorNames[$idx] ?? '')) ?: $color;
            $product->images()->create([
                'path' => $path,
                'is_primary' => ! $hasPrimary && $savedCount === 0,
                'sort_order' => $nextOrder + $savedCount + 1,
                'color' => $color,
                'color_name' => $colorName,
            ]);
            $savedCount++;
        }
    }
}
