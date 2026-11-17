<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return view('admin.coupons.index', [
            'coupons' => Coupon::with(['sellerProfile:id,store_name', 'product:id,name'])->latest()->get(),
            'sellers' => SellerProfile::orderBy('store_name')->get(['id', 'store_name']),
            'products' => Product::orderBy('name')->get(['id', 'name', 'vendor_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'integer', 'min:1'],
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
                return back()->withErrors(['product_id' => 'المنتج المحدد لا ينتمي إلى البائع المختار.'])->withInput();
            }
            $sellerId = $sellerId ?: $product?->vendor_id;
        }

        Coupon::create([
            'seller_profile_id' => $sellerId,
            'product_id' => $productId,
            'code' => strtoupper($validated['code']),
            'type' => $validated['type'],
            'value' => $validated['type'] === 'fixed' ? (int) round($validated['value'] * 100) : $validated['value'],
            'min_order_amount' => (int) round(($validated['min_order_amount'] ?? 0) * 100),
            'usage_limit' => $validated['usage_limit'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'تم إنشاء الكوبون.');
    }

    public function toggle(Coupon $coupon)
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', 'تم تحديث حالة الكوبون.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return back()->with('success', 'تم حذف الكوبون.');
    }
}
