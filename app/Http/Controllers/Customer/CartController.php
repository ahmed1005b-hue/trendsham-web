<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StorefrontVisit;
use App\Services\StorefrontAttribution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->cartItems()->with('product.primaryImage', 'product.vendor', 'variant')->get();

        return view('customer.cart.index', [
            'items' => $items,
            'total' => $items->sum(fn ($item) => $item->subtotal),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::with('activeDailyDeal')->findOrFail($validated['product_id']);
        abort_unless($product->isLive(), 404);

        $variant = null;
        $hasActiveVariants = $product->variants()->where('is_active', true)->exists();
        if (! empty($validated['variant_id'])) {
            $variant = ProductVariant::where('id', $validated['variant_id'])
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->firstOrFail();
        } elseif ($hasActiveVariants) {
            return back()->with('error', 'يرجى اختيار اللون/المقاس قبل الإضافة إلى السلة.');
        }

        $quantity = $validated['quantity'] ?? 1;

        $deal = $product->relationLoaded('activeDailyDeal') ? $product->activeDailyDeal : null;
        $unitPrice = $deal ? (int) round($deal->discounted_price) : (int) $product->price;
        if ($variant) {
            $unitPrice += (int) $variant->price_adjustment;
        }
        $originalUnitPrice = (int) $product->price + ($variant ? (int) $variant->price_adjustment : 0);

        $stock = $variant ? $variant->stock_quantity : $product->stock_quantity;
        if ($stock < 1) {
            return back()->with('error', 'هذا المتغير غير متوفر حالياً.');
        }

        $item = CartItem::firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
        ]);
        if (! $item->exists || ! $this->visitBelongsToProduct($item, $product->vendor_id)) {
            $token = $request->header(StorefrontAttribution::TOKEN_HEADER)
                ?? $request->cookie('trendsham_store_visitor');
            $item->storefront_visit_id = app(StorefrontAttribution::class)
                ->resolveVisit($token, $product->vendor_id)?->id;
        }
        $item->quantity = min($stock, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->unit_price = $unitPrice;
        $item->original_unit_price = $originalUnitPrice;
        $item->save();

        return redirect()->route('customer.cart.index')->with('success', 'تمت الإضافة للسلة.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        Gate::authorize('manage', $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cartItem->loadMissing('product', 'variant');
        $stock = $cartItem->variant ? $cartItem->variant->stock_quantity : $cartItem->product->stock_quantity;
        $cartItem->update([
            'quantity' => min($validated['quantity'], $stock),
        ]);

        return back()->with('success', 'تم تحديث الكمية.');
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        Gate::authorize('manage', $cartItem);

        $cartItem->delete();

        return back()->with('success', 'تم حذف المنتج من السلة.');
    }

    public function clearAll(Request $request)
    {
        $request->user()->cartItems()->delete();

        return back()->with('success', 'تم مسح السلة بالكامل.');
    }

    private function visitBelongsToProduct(CartItem $item, int $sellerProfileId): bool
    {
        return $item->storefront_visit_id !== null
            && StorefrontVisit::query()
                ->whereKey($item->storefront_visit_id)
                ->where('seller_profile_id', $sellerProfileId)
                ->exists();
    }
}
