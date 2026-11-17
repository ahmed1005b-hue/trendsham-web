<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::with(['sellerProfile:id,store_name', 'product:id,name'])->latest()->get();

        return response()->json([
            'data' => $coupons->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type,
                'value' => $c->type === 'fixed' ? $c->value / 100 : $c->value,
                'min_order_amount' => $c->min_order_amount / 100,
                'usage_limit' => $c->usage_limit,
                'used_count' => $c->used_count,
                'expires_at' => $c->expires_at,
                'is_active' => $c->is_active,
                'seller_profile_id' => $c->seller_profile_id,
                'seller_name' => $c->sellerProfile?->store_name,
                'product_id' => $c->product_id,
                'product_name' => $c->product?->name,
                'scope' => $c->scopeLabel(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'seller_profile_id' => ['nullable', 'integer', 'exists:seller_profiles,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id,deleted_at,NULL'],
        ]);

        $sellerId = $validated['seller_profile_id'] ?? null;
        $productId = $validated['product_id'] ?? null;
        if ($productId) {
            $product = Product::find($productId);
            if ($product && $sellerId && (int) $product->vendor_id !== (int) $sellerId) {
                return response()->json(['message' => 'المنتج المحدد لا ينتمي إلى البائع المختار.'], 422);
            }
            $sellerId = $sellerId ?: $product?->vendor_id;
        }

        $coupon = Coupon::create([
            'seller_profile_id' => $sellerId,
            'product_id' => $productId,
            'code' => strtoupper($validated['code']),
            'type' => $validated['type'],
            'value' => $validated['type'] === 'fixed' ? (int) round($validated['value'] * 100) : (int) $validated['value'],
            'min_order_amount' => (int) round(($validated['min_order_amount'] ?? 0) * 100),
            'usage_limit' => $validated['usage_limit'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['message' => 'تم إنشاء الكوبون.', 'id' => $coupon->id], 201);
    }

    public function toggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return response()->json(['message' => 'تم تحديث حالة الكوبون.', 'is_active' => $coupon->is_active]);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(['message' => 'تم حذف الكوبون.']);
    }
}
