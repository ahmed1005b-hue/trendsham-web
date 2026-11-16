<?php

namespace App\Http\Controllers\Seller;

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
    private function eligibleProducts()
    {
        return Product::whereHas('vendor', function ($q) {
            $q->where('user_id', auth()->id());
        })
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->orderBy('name')
            ->get(['id', 'name', 'price']);
    }

    public function index()
    {
        $deals = DailyDeal::whereHas('product.vendor', function ($q) {
            $q->where('user_id', auth()->id());
        })
            ->with('product')
            ->latest()
            ->paginate(15);

        return view('seller.daily-deals.index', compact('deals'));
    }

    public function create()
    {
        $products = $this->eligibleProducts();
        abort_if($products->isEmpty(), 404, 'لا توجد منتجات مؤهلة. يجب أن يكون منتجك مفعلاً ومعتمداً من الإدارة.');

        return view('seller.daily-deals.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:90'],
            'special_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
        ]);
        $validated['special_price'] = $request->filled('special_price') ? $validated['special_price'] : null;

        // تأكيد أن المنتج يملكه البائع فعلاً
        Product::whereHas('vendor', function ($q) {
            $q->where('user_id', auth()->id());
        })
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->findOrFail($validated['product_id']);

        // منع تداخل عرضين على نفس المنتج
        $overlap = DailyDeal::where('product_id', $validated['product_id'])
            ->where('ends_at', '>', $validated['starts_at'])
            ->exists();
        if ($overlap) {
            return back()->withInput()->withErrors(['starts_at' => 'يوجد عرض آخر نشط أو قادم على هذا المنتج في نفس الفترة.']);
        }

        $validated['is_active'] = true;
        DailyDeal::create($validated);

        return redirect()->route('seller.daily-deals.index')
            ->with('success', 'تم إضافة العرض اليومي بنجاح وسيظهر تلقائياً في وقت بدايته.');
    }

    public function edit(DailyDeal $deal)
    {
        Gate::authorize('manage', $deal);
        $products = $this->eligibleProducts();

        return view('seller.daily-deals.edit', ['deal' => $deal, 'products' => $products]);
    }

    public function update(Request $request, DailyDeal $deal)
    {
        Gate::authorize('manage', $deal);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:90'],
            'special_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['special_price'] = $request->filled('special_price') ? $validated['special_price'] : null;

        Product::whereHas('vendor', function ($q) {
            $q->where('user_id', auth()->id());
        })
            ->where('status', 'active')
            ->whereNotNull('admin_approved_at')
            ->findOrFail($validated['product_id']);

        $overlap = DailyDeal::where('product_id', $validated['product_id'])
            ->where('id', '!=', $deal->id)
            ->where('ends_at', '>', $validated['starts_at'])
            ->exists();
        if ($overlap) {
            return back()->withInput()->withErrors(['starts_at' => 'يوجد عرض آخر نشط أو قادم على هذا المنتج في نفس الفترة.']);
        }

        $deal->update($validated);

        return redirect()->route('seller.daily-deals.index')
            ->with('success', 'تم تحديث العرض اليومي بنجاح.');
    }

    public function destroy(DailyDeal $deal)
    {
        Gate::authorize('manage', $deal);
        $deal->delete();

        return redirect()->route('seller.daily-deals.index')
            ->with('success', 'تم حذف العرض اليومي.');
    }

    public function toggleStatus(DailyDeal $deal)
    {
        Gate::authorize('manage', $deal);
        $deal->update(['is_active' => ! $deal->is_active]);

        return redirect()->route('seller.daily-deals.index')
            ->with('success', 'تم تحديث حالة العرض.');
    }
}
