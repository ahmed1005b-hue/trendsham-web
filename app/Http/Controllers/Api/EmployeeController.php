<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\EmployeeWorkload;
use App\Services\ProductVariantSync;
use App\Services\StaffActivityLog;
use App\Services\StaffOrderStatusChanger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    /** أرقام شغل اليوم في الصفحة الرئيسية للموظف (نفس بطاقات الموقع). */
    public function dashboard(Request $request, EmployeeWorkload $workload): JsonResponse
    {
        return response()->json([
            'data' => $workload->countsFor($request->user()),
            'sections' => $request->user()->employeeSections(),
        ]);
    }

    public function orders()
    {
        return OrderResource::collection(
            Order::with('user')->whereNull('archived_at')->latest()->paginate(20)
        );
    }

    public function showOrder(Order $order)
    {
        abort_if($order->archived_at, 404);
        $order->load('items.vendor', 'payment', 'shipments.shippingCompany', 'shipments.seller', 'user');

        return new OrderResource($order);
    }

    /** نفس قواعد لوحة الموظف في الموقع (StaffOrderStatusChanger). */
    public function updateOrderStatus(Request $request, Order $order, StaffOrderStatusChanger $changer)
    {
        abort_if($order->archived_at, 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in(StaffOrderStatusChanger::STATUSES)],
            'reason' => ['nullable', 'required_if:status,cancelled', 'string', 'max:500'],
        ], [
            'reason.required_if' => __('اكتب سبب الإلغاء.'),
        ]);

        try {
            $changer->change($request->user(), $order, $validated['status'], $validated['reason'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OrderResource($order->fresh(['items', 'payment', 'shipments.shippingCompany', 'shipments.seller']));
    }

    public function archiveOrder(Request $request, Order $order)
    {
        if ($order->archived_at === null) {
            $order->update(['archived_at' => now()]);
            app(StaffActivityLog::class)->order($request->user(), 'order.archive', $order);
        }

        return response()->json(['message' => 'تمت أرشفة الطلب مع الاحتفاظ بسجله.']);
    }

    public function products(Request $request)
    {
        $filter = $request->string('filter')->toString() ?: 'all';
        $query = Product::with('vendor', 'category', 'primaryImage')->latest();

        match ($filter) {
            'pending' => $query->pendingReview(),
            'live' => $query->publiclyVisible(),
            'draft' => $query->where('status', 'draft'),
            'inactive' => $query->where('status', 'inactive'),
            default => null,
        };

        return ProductResource::collection($query->paginate(20));
    }

    public function updateProduct(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:categories,id,deleted_at,NULL'],
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
        app(ProductVariantSync::class)->syncProductStock($product);
        app(StaffActivityLog::class)->product($request->user(), 'product.update', $product);

        return new ProductResource($product->fresh(['vendor', 'category', 'primaryImage']));
    }

    public function archiveProduct(Request $request, Product $product)
    {
        $product->update(['status' => 'inactive']);
        app(StaffActivityLog::class)->product($request->user(), 'product.archive', $product);

        return response()->json(['message' => 'تم إخفاء المنتج مع الاحتفاظ بسجله.']);
    }
}
