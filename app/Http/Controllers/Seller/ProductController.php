<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
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
            ->with('primaryImage')
            ->latest()
            ->paginate(20);

        return view('seller.products.index', ['products' => $products]);
    }

    public function create(Request $request)
    {
        $this->ensureApprovedSeller($request);

        return view('seller.products.create', ['categories' => Category::active()->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $this->ensureApprovedSeller($request);

        $validated = $this->validateProduct($request);

        $product = $request->user()->sellerProfile->products()->create([
            ...$validated,
            'slug' => Str::slug($validated['name']).'-'.Str::random(6),
            'price' => (int) round($validated['price'] * 100),
            'compare_at_price' => $validated['compare_at_price'] ? (int) round($validated['compare_at_price'] * 100) : null,
        ]);

        // الرصيد الافتتاحي أولاً، ثم مطابقة الألوان/المقاسات تسجّل الفرق — فمجموع الحركات = الرصيد الفعلي
        if ($product->stock_quantity > 0) {
            $product->inventoryMovements()->create([
                'quantity_change' => $product->stock_quantity,
                'reason' => 'restock',
                'quantity_after' => $product->stock_quantity,
                'note' => 'رصيد افتتاحي عند إنشاء المنتج',
            ]);
        }

        app(ProductVariantSync::class)->sync($request, $product, false);

        $this->storeImages($request, $product);

        $message = $product->status === 'active'
            ? 'تم إنشاء المنتج، بانتظار موافقة الإدارة قبل ظهوره للزوار.'
            : 'تم إنشاء المنتج كمسودة.';

        return redirect()->route('seller.products.index')->with('success', $message);
    }

    public function edit(Request $request, Product $product)
    {
        Gate::authorize('manage', $product);

        return view('seller.products.edit', [
            'product' => $product->load(['images', 'variants']),
            'categories' => Category::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        Gate::authorize('manage', $product);

        $validated = $this->validateProduct($request, $product->id);

        $stockDiff = $validated['stock_quantity'] - $product->stock_quantity;
        $isBeingReactivated = $product->status !== 'active' && $validated['status'] === 'active';

        $product->update([
            ...$validated,
            'price' => (int) round($validated['price'] * 100),
            'compare_at_price' => $validated['compare_at_price'] ? (int) round($validated['compare_at_price'] * 100) : null,
        ]);

        // أي إعادة تفعيل بعد ما كان المنتج معطّل/مسودة تحتاج مراجعة إدارة من جديد -
        // ما بنعتمد على موافقة قديمة صارت قبل ما يتغيّر المنتج.
        if ($isBeingReactivated) {
            $product->update(['admin_approved_at' => null, 'rejection_note' => null]);
        }

        if ($stockDiff !== 0) {
            $product->inventoryMovements()->create([
                'quantity_change' => $stockDiff,
                'reason' => 'adjustment',
                'quantity_after' => $product->stock_quantity,
                'note' => 'تعديل يدوي من لوحة البائع',
            ]);
        }

        $submittedVariants = $request->has('variant_size') || $request->has('variant_color') || $request->boolean('replace_variants');
        $nonEmptyVariants = false;
        foreach ((array) $request->input('variant_color', []) as $v) {
            if (trim((string) $v) !== '') {
                $nonEmptyVariants = true;
                break;
            }
        }
        if (! $nonEmptyVariants) {
            foreach ((array) $request->input('variant_size', []) as $v) {
                if (trim((string) $v) !== '') {
                    $nonEmptyVariants = true;
                    break;
                }
            }
        }
        if ($submittedVariants) {
            app(ProductVariantSync::class)->sync($request, $product, $request->boolean('replace_variants') || $nonEmptyVariants);
        }
        $this->storeImages($request, $product);

        $message = $isBeingReactivated
            ? 'تم تحديث المنتج، بانتظار موافقة الإدارة من جديد قبل ظهوره للزوار.'
            : 'تم تحديث المنتج.';

        return redirect()->route('seller.products.index')->with('success', $message);
    }

    public function destroy(Request $request, Product $product)
    {
        Gate::authorize('manage', $product);

        $product->delete();

        return back()->with('success', 'تم حذف المنتج.');
    }

    public function destroyImage(Request $request, Product $product, ProductImage $image)
    {
        Gate::authorize('manage', $product);
        abort_unless($image->product_id === $product->id, 403);

        $wasPrimary = $image->is_primary;

        Storage::disk('public')->delete($image->path);
        $image->delete();

        // لو كانت الصورة المحذوفة هي الرئيسية، نرقّي أقدم صورة متبقية لتكون الرئيسية
        // الجديدة تلقائياً - بدل ما يضل المنتج بلا صورة رئيسية أصلاً.
        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'تم حذف الصورة.');
    }

    public function setPrimaryImage(Request $request, Product $product, ProductImage $image)
    {
        Gate::authorize('manage', $product);
        abort_unless($image->product_id === $product->id, 403);

        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);

        return back()->with('success', 'تم تعيينها كصورة رئيسية.');
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

    private function ensureApprovedSeller(Request $request): void
    {
        abort_unless(
            $request->user()->sellerProfile?->isApproved(),
            403,
            'يجب اعتماد متجرك من الإدارة أولاً قبل إضافة منتجات.'
        );
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
            'variant_color.*' => ['nullable', 'string', 'max:50'],
            'variant_color_name.*' => ['nullable', 'string', 'max:50'],
            'variant_size.*' => ['nullable', 'string', 'max:50'],
            'variant_sku.*' => ['nullable', 'string', 'max:100'],
            'variant_original_color.*' => ['nullable', 'string', 'max:50'],
            'variant_price_adjustment.*' => ['nullable', 'numeric', 'min:0'],
            'variant_stock_quantity.*' => ['nullable', 'integer', 'min:0'],
        ], ImageUploadService::validationMessages('images.*'));
    }
}
