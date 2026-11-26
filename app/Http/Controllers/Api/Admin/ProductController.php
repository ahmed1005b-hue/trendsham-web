<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\AppNotification;
use App\Models\Product;
use App\Services\StaffActivityLog;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'pending');

        $query = Product::with('vendor', 'category', 'primaryImage')->latest();

        match ($filter) {
            'pending' => $query->pendingReview(),
            'live' => $query->publiclyVisible(),
            'draft' => $query->where('status', 'draft'),
            default => null,
        };

        return ProductResource::collection($query->paginate(20));
    }

    public function approve(Request $request, Product $product)
    {
        $product->update(['admin_approved_at' => now(), 'rejection_note' => null]);
        app(StaffActivityLog::class)->product($request->user(), 'product.approve', $product);

        AppNotification::send($product->vendor->user_id, 'product_approved', "تمت الموافقة على منتج \"{$product->name}\"", null, null);

        return new ProductResource($product);
    }

    public function reject(Request $request, Product $product)
    {
        $validated = $request->validate(['rejection_note' => ['required', 'string', 'max:255']]);

        $product->update(['status' => 'draft', 'admin_approved_at' => null, 'rejection_note' => $validated['rejection_note']]);
        app(StaffActivityLog::class)->product($request->user(), 'product.reject', $product, ['note' => $validated['rejection_note']]);

        AppNotification::send($product->vendor->user_id, 'product_rejected', "تم رفض منتج \"{$product->name}\"", $validated['rejection_note'], null);

        return new ProductResource($product);
    }
}
