<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user()->sellerProfile;
        $coupons = $seller->coupons()->latest()->paginate(20);
        return response()->json(['data' => $coupons]);
    }

    public function store(Request $request)
    {
        $seller = $request->user()->sellerProfile;
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $productId = $validated['product_id'] ?? null;
        if ($productId) {
            $product = Product::find($productId);
            if ($product && (int) $product->vendor_id !== (int) $seller->id) {
                return response()->json(['message' => 'المنتج المحدد لا ينتمي إلى البائع.'], 422);
            }
        }

        $coupon = Coupon::create([
            'seller_profile_id' => $seller->id,
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

    public function show(Coupon $coupon)
    {
        $this->authorize('view', $coupon);
        return response()->json(['data' => $coupon]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorize('update', $coupon);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code,'. $coupon->id],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $coupon->update([
            'code' => strtoupper($validated['code']),
            'type' => $validated['type'],
            'value' => $validated['type'] === 'fixed' ? (int) round($validated['value'] * 100) : (int) $validated['value'],
            'min_order_amount' => (int) round(($validated['min_order_amount'] ?? 0) * 100),
            'usage_limit' => $validated['usage_limit'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['is_active'] ?? $coupon->is_active,
        ]);

        return response()->json(['message' => 'تم تحديث الكوبون.']);
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorize('delete', $coupon);
        $coupon->delete();
        return response()->json(['message' => 'تم حذف الكوبون.']);
    }
}