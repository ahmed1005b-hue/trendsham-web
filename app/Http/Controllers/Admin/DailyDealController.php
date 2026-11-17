<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyDeal;
use App\Models\Product;
use Illuminate\Http\Request;

class DailyDealController extends Controller
{
    public function index()
    {
        $deals = DailyDeal::with('product')->latest()->paginate(15);

        return view('admin.daily-deals.index', compact('deals'));
    }

    public function create()
    {
        $products = Product::where('status', 'active')->where('admin_approved_at', '!=', null)->get();

        return view('admin.daily-deals.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:90'],
            'special_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
        ]);
        $validated['special_price'] = $request->filled('special_price') ? $validated['special_price'] : null;

        DailyDeal::create($validated);

        return redirect()->route('admin.daily-deals.index')
            ->with('success', 'تم إضافة منتج اليوم بنجاح.');
    }

    public function edit(DailyDeal $deal)
    {
        $products = Product::where('status', 'active')->where('admin_approved_at', '!=', null)->get();

        return view('admin.daily-deals.edit', compact('deal', 'products'));
    }

    public function update(Request $request, DailyDeal $deal)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id,deleted_at,NULL'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:90'],
            'special_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $validated['special_price'] = $request->filled('special_price') ? $validated['special_price'] : null;

        $deal->update($validated);

        return redirect()->route('admin.daily-deals.index')
            ->with('success', 'تم تحديث منتج اليوم بنجاح.');
    }

    public function destroy(DailyDeal $deal)
    {
        $deal->delete();

        return redirect()->route('admin.daily-deals.index')
            ->with('success', 'تم حذف منتج اليوم.');
    }

    public function toggleStatus(DailyDeal $deal)
    {
        $deal->update(['is_active' => ! $deal->is_active]);

        return redirect()->route('admin.daily-deals.index')
            ->with('success', 'تم تحديث حالة المنتج.');
    }
}
