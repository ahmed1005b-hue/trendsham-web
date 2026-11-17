<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function orders()
    {
        return OrderResource::collection(
            Order::with('user')->whereNull('archived_at')->latest()->paginate(20)
        );
    }

    public function showOrder(Order $order)
    {
        abort_if($order->archived_at, 404);
        $order->load('items.vendor', 'payment', 'shipment.shippingCompany', 'user');

        return new OrderResource($order);
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        abort_if($order->archived_at, 404);
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,processing,shipped,delivered,cancelled'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        try {
            $order->changeStatus($validated['status']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if (array_key_exists('tracking_number', $validated) ||
            in_array($validated['status'], ['shipped', 'delivered'], true)) {
            $shipment = $order->shipment()->firstOrCreate([], ['status' => 'pending']);
            $shipmentUpdates = [];

            if (array_key_exists('tracking_number', $validated)) {
                $shipmentUpdates['tracking_number'] = $validated['tracking_number'];
            }
            if ($validated['status'] === 'shipped') {
                $shipmentUpdates['status'] = 'shipped';
                $shipmentUpdates['shipped_at'] = $shipment->shipped_at ?? now();
            } elseif ($validated['status'] === 'delivered') {
                $shipmentUpdates['status'] = 'delivered';
                $shipmentUpdates['shipped_at'] = $shipment->shipped_at ?? now();
                $shipmentUpdates['delivered_at'] = $shipment->delivered_at ?? now();
            }

            if ($shipmentUpdates !== []) {
                $shipment->update($shipmentUpdates);
            }
        }

        AppNotification::send(
            $order->user_id,
            'order_status_changed',
            "تحديث على طلبك #{$order->order_number}",
            'الحالة الجديدة: '.$validated['status'],
            null
        );

        return new OrderResource($order->fresh(['items', 'payment', 'shipment.shippingCompany']));
    }

    public function archiveOrder(Order $order)
    {
        $order->update(['archived_at' => now()]);

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

        return new ProductResource($product->fresh(['vendor', 'category', 'primaryImage']));
    }

    public function archiveProduct(Product $product)
    {
        $product->update(['status' => 'inactive']);

        return response()->json(['message' => 'تم إخفاء المنتج مع الاحتفاظ بسجله.']);
    }
}
