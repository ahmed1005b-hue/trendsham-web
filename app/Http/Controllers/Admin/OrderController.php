<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $employeeMode = $request->routeIs('employee.*');
        $orders = Order::with('user')
            ->when($employeeMode, fn ($query) => $query->whereNull('archived_at'))
            ->latest()
            ->paginate(20);

        return view('admin.orders.index', compact('orders', 'employeeMode'));
    }

    public function show(Request $request, Order $order)
    {
        $order->load('items.vendor', 'payment', 'shipment', 'user');

        return view('admin.orders.show', [
            'order' => $order,
            'employeeMode' => $request->routeIs('employee.*'),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,processing,shipped,delivered,cancelled'],
        ]);

        try {
            $order->changeStatus($validated['status']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $statusLabels = Order::STATUS_LABELS;

        AppNotification::send(
            $order->user_id,
            'order_status_changed',
            "تحديث على طلبك #{$order->order_number}",
            'الحالة الجديدة: '.($statusLabels[$validated['status']] ?? $validated['status']),
            route('customer.orders.show', $order)
        );

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    public function archive(Order $order)
    {
        $order->update(['archived_at' => now()]);

        return redirect()->route('employee.orders.index')->with('success', 'تمت أرشفة الطلب مع الاحتفاظ بسجله.');
    }
}
