<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\DailyDeal;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DailyDealController extends Controller
{
    /**
     * منتجات البائع المؤهلة لدخول عروض اليوم (مفعلة ومعتمدة من الإدارة).
     */
    private function vendor(Request $request)
    {
        $vendor = $request->user()->sellerProfile;
        abort_unless($vendor?->isApproved(), 403, 'يجب اعتماد متجرك من الإدارة أولاً.');

        return $vendor;
    }

    private function eligibleProducts($vendor)
    {
        return Product::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    private function formatDeal(DailyDeal $deal): array
    {
        return [
            'id' => $deal->id,
            'product_id' => $deal->product_id,
            'product_name' => $deal->product?->name,
            'discount_percentage' => (float) $deal->discount_percentage,
            'special_price' => $deal->special_price !== null ? (float) $deal->special_price : null,
            'discounted_price' => (float) $deal->discounted_price,
            'starts_at' => $deal->starts_at->toIso8601String(),
            'ends_at' => $deal->ends_at->toIso8601String(),
            'max_quantity' => $deal->max_quantity,
            'sold_quantity' => $deal->sold_quantity,
            'is_active' => $deal->is_active,
            'is_expired' => $deal->is_expired,
        ];
    }

    public function index(Request $request)
    {
        $vendor = $this->vendor($request);

        $deals = DailyDeal::whereHas('product', function ($q) use ($vendor) {
            $q->where('vendor_id', $vendor->id);
        })
            ->with('product')
            ->latest()
            ->get();

        return response()->json([
            'eligible_products' => $this->eligibleProducts($vendor)->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
            ]),
            'deals' => $deals->map(fn ($d) => $this->formatDeal($d)),
        ]);
    }

    public function store(Request $request)
    {
        $vendor = $this->vendor($request);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:90'],
            'special_price' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        // تأكيد أن المنتج يملكه البائع فعلاً ومؤهل
        $exists = Product::where('id', $validated['product_id'])
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->exists();
        abort_unless($exists, 403, 'المنتج غير موجود أو غير مؤهل للعروض.');

        $overlap = DailyDeal::where('product_id', $validated['product_id'])
            ->where('ends_at', '>', $validated['starts_at'])
            ->exists();
        if ($overlap) {
            return response()->json(['message' => 'يوجد عرض آخر نشط أو قادم على هذا المنتج في نفس الفترة.'], 422);
        }

        $deal = DailyDeal::create($validated + ['is_active' => true]);

        return response()->json([
            'message' => 'تم إضافة العرض اليومي بنجاح.',
            'deal' => $this->formatDeal($deal->load('product')),
        ], 201);
    }

    public function update(Request $request, DailyDeal $deal)
    {
        $vendor = $this->vendor($request);
        Gate::authorize('manage', $deal);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:90'],
            'special_price' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $exists = Product::where('id', $validated['product_id'])
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->exists();
        abort_unless($exists, 403, 'المنتج غير موجود أو غير مؤهل للعروض.');

        $overlap = DailyDeal::where('product_id', $validated['product_id'])
            ->where('id', '!=', $deal->id)
            ->where('ends_at', '>', $validated['starts_at'])
            ->exists();
        if ($overlap) {
            return response()->json(['message' => 'يوجد عرض آخر نشط أو قادم على هذا المنتج في نفس الفترة.'], 422);
        }

        $deal->update($validated);

        return response()->json([
            'message' => 'تم تحديث العرض اليومي بنجاح.',
            'deal' => $this->formatDeal($deal->load('product')),
        ]);
    }

    public function destroy(Request $request, DailyDeal $deal)
    {
        $this->vendor($request);
        Gate::authorize('delete', $deal);

        $deal->delete();

        return response()->json(['message' => 'تم حذف العرض اليومي.']);
    }

    public function toggle(Request $request, DailyDeal $deal)
    {
        $this->vendor($request);
        Gate::authorize('toggle', $deal);

        $deal->update(['is_active' => ! $deal->is_active]);

        return response()->json([
            'message' => 'تم تحديث حالة العرض.',
            'deal' => $this->formatDeal($deal->load('product')),
        ]);
    }
}
