<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Loyalty\LoyaltyCalculator;
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
        $items = $request->user()->cartItems()->with('product.primaryImage', 'product.images', 'product.vendor', 'product.activeDailyDeal', 'variant')->get();

        $total = (int) $items->sum(fn ($item) => $item->subtotal);
        $calc = app(LoyaltyCalculator::class);
        $enabled = $calc->settings()->enabled();

        return response()->json([
            'items' => CartItemResource::collection($items),
            'total' => $total / 100,
            'will_earn_points' => $enabled ? $calc->pointsFor($total) : 0,   // نقاط الولاء المتوقعة
            'points_hint' => $items->isEmpty() ? null : $calc->hintText($total),
            'points_spend_per_point' => $enabled ? $calc->settings()->spendPerPoint() / 100 : null,   // ليحسب التطبيق التلميح محلياً عند تغيير الكميات
            'currency' => 'SYP',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
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
            return response()->json(['message' => 'يرجى اختيار اللون/المقاس قبل الإضافة إلى السلة.'], 422);
        }

        $quantity = $validated['quantity'] ?? 1;

        $deal = $product->relationLoaded('activeDailyDeal') ? $product->activeDailyDeal : null;
        $unitPrice = $deal ? (int) round($deal->discounted_price) : (int) $product->price;
        if ($variant) {
            $unitPrice += (int) $variant->price_adjustment;
        }
        $originalUnitPrice = (int) $product->price + ($variant ? (int) $variant->price_adjustment : 0);

        $stock = $variant ? $variant->stock_quantity : $product->stock_quantity;
        abort_unless($stock > 0, 422, 'هذا المتغير غير متوفر حالياً.');

        $item = CartItem::firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
        ]);
        if (! $item->exists || ! $this->visitBelongsToProduct($item, $product->vendor_id)) {
            $item->storefront_visit_id = app(StorefrontAttribution::class)
                ->resolveVisit($request->header(StorefrontAttribution::TOKEN_HEADER), $product->vendor_id)?->id;
        }
        $item->quantity = min($stock, ($item->exists ? $item->quantity : 0) + $quantity);
        $item->unit_price = $unitPrice;
        $item->original_unit_price = $originalUnitPrice;
        $item->save();

        return new CartItemResource($item->load('product.primaryImage', 'product.images', 'product.vendor', 'product.activeDailyDeal', 'variant'));
    }

    public function update(Request $request, CartItem $cartItem)
    {
        Gate::authorize('manage', $cartItem);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $cartItem->loadMissing('product', 'variant');
        $stock = $cartItem->variant ? $cartItem->variant->stock_quantity : $cartItem->product->stock_quantity;
        $cartItem->update(['quantity' => min($validated['quantity'], $stock)]);

        return new CartItemResource($cartItem->load('product.primaryImage', 'product.images', 'product.vendor', 'product.activeDailyDeal', 'variant'));
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        Gate::authorize('manage', $cartItem);

        $cartItem->delete();

        return response()->json(['message' => 'تم الحذف من السلة.']);
    }

    public function clearAll(Request $request)
    {
        $request->user()->cartItems()->delete();

        return response()->json(['message' => 'تم مسح السلة بالكامل.']);
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
