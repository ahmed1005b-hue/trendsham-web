<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\SellerOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        $orderItems = OrderItem::with('order.user')
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->paginate(20);

        // إحصائيات سريعة
        $stats = [
            'total' => OrderItem::where('vendor_id', $vendor->id)->count(),
            'pending' => OrderItem::where('vendor_id', $vendor->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'pending');
                })->count(),
            'processing' => OrderItem::where('vendor_id', $vendor->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'processing');
                })->count(),
            'shipped' => OrderItem::where('vendor_id', $vendor->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'shipped');
                })->count(),
            'delivered' => OrderItem::where('vendor_id', $vendor->id)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'delivered');
                })->count(),
        ];

        return view('seller.orders.index', compact('orderItems', 'stats', 'vendor'));
    }

    public function show(Request $request, Order $order)
    {
        $vendor = $request->user()->sellerProfile;

        Gate::authorize('viewAsSeller', $order);

        $order->load('items.product.primaryImage', 'user');
        $service = app(SellerOrderService::class);

        return view('seller.orders.show', [
            'order' => $order,
            'vendor' => $vendor,
            'parcel' => $service->parcelFor($vendor, $order),
            'nextStatuses' => $service->nextStatuses($order, $vendor),
            'isShared' => ! $order->isSingleVendor(),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(SellerOrderService::class)->updateStatus($request->user()->sellerProfile, $order, $validated['status'], $validated['tracking_number'] ?? null);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة الطلب بنجاح.');
    }
}
