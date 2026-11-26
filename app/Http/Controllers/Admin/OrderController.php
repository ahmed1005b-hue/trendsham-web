<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\StaffActivityLog;
use App\Services\StaffOrderStatusChanger;
use App\Shipping\ParcelService;
use App\Shipping\ShippingDebt;
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
        app(ParcelService::class)->ensureFor($order);
        $order->load('items.vendor', 'payment', 'shipments.seller', 'user');

        return view('admin.orders.show', [
            'order' => $order,
            'employeeMode' => $request->routeIs('employee.*'),
        ]);
    }

    public function updateStatus(Request $request, Order $order, StaffOrderStatusChanger $changer)
    {
        abort_if($request->routeIs('employee.*') && $order->archived_at, 404);
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,processing,shipped,delivered,cancelled'],
        ]);

        try {
            $changer->change($request->user(), $order, $validated['status']);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث حالة الطلب.');
    }

    /** الزبون رفض استلام طرد: يُلغى الطرد، والمنصة تدفع شحنه وتسجّله ديناً عليه. */
    public function markRefused(Request $request, Order $order, Shipment $shipment)
    {
        abort_unless($shipment->order_id === $order->id, 404);

        try {
            $debt = app(ShippingDebt::class)->recordRefusal($shipment);
        } catch (\DomainException $e) {
            return back()->with('error', __($e->getMessage()));
        }
        app(StaffActivityLog::class)->order($request->user(), 'order.refused', $order);

        return back()->with('success', $debt > 0
            ? __('سُجّل رفض الاستلام وأُلغي الطلب. أُضيف :fee ديناً على الزبون ومُنع من الدفع عند الاستلام شهراً.', ['fee' => number_format($debt / 100).' ل.س'])
            : __('سُجّل رفض الاستلام وأُلغي الطلب. الطلب كان مدفوعاً: اخصم أجرة الشحن (:fee) مما تعيده للزبون.', ['fee' => number_format(max($shipment->shipping_cost, $shipment->shipping_fee) / 100).' ل.س']));
    }

    public function archive(Request $request, Order $order)
    {
        if ($order->archived_at === null) {
            $order->update(['archived_at' => now()]);
            app(StaffActivityLog::class)->order($request->user(), 'order.archive', $order);
        }

        return redirect()->route('employee.orders.index')->with('success', 'تمت أرشفة الطلب مع الاحتفاظ بسجله.');
    }
}
