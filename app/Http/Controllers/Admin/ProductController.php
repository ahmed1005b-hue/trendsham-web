<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('vendor', 'category', 'primaryImage')->latest();

        $filter = $request->get('filter', 'pending');

        match ($filter) {
            'pending' => $query->pendingReview(),
            'live' => $query->publiclyVisible(),
            'draft' => $query->where('status', 'draft'),
            default => null, // 'all'
        };

        return view('admin.products.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'filter' => $filter,
            'pendingCount' => Product::pendingReview()->count(),
            'employeeMode' => $request->routeIs('employee.*'),
        ]);
    }

    public function edit(Product $product)
    {
        return view('employee.products.edit', [
            'product' => $product,
            'categories' => \App\Models\Category::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:price'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'],
            'price' => (int) round($validated['price'] * 100),
            'compare_at_price' => isset($validated['compare_at_price'])
                ? (int) round($validated['compare_at_price'] * 100)
                : null,
            'stock_quantity' => $validated['stock_quantity'],
        ]);

        // منتج له ألوان/مقاسات: مخزونه = مجموعها (الكمية تُعدَّل لكل لون/مقاس من البائع)
        app(\App\Services\ProductVariantSync::class)->syncProductStock($product);

        return redirect()->route('employee.products.index')->with('success', 'تم تحديث المنتج.');
    }

    public function archive(Product $product)
    {
        $product->update(['status' => 'inactive']);

        return back()->with('success', 'تم إخفاء المنتج مع الاحتفاظ بسجله.');
    }

    public function approve(Product $product)
    {
        $product->update(['admin_approved_at' => now(), 'rejection_note' => null]);

        AppNotification::send(
            $product->vendor->user_id,
            'product_approved',
            "تمت الموافقة على منتج \"{$product->name}\"",
            'صار المنتج ظاهر للزوار الآن.',
            route('seller.products.index')
        );

        return back()->with('success', 'تمت الموافقة على المنتج.');
    }

    public function reject(Request $request, Product $product)
    {
        $validated = $request->validate(['rejection_note' => ['required', 'string', 'max:255']]);

        $product->update([
            'status' => 'draft',
            'admin_approved_at' => null,
            'rejection_note' => $validated['rejection_note'],
        ]);

        AppNotification::send(
            $product->vendor->user_id,
            'product_rejected',
            "تم رفض منتج \"{$product->name}\"",
            $validated['rejection_note'],
            route('seller.products.edit', $product)
        );

        return back()->with('success', 'تم رفض المنتج وإرجاعه لمسودة عند البائع.');
    }
}
