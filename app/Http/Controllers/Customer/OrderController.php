<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Returns\ReturnService;
use App\Services\CustomerOrderCanceller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()->orders()->latest()->paginate(10);

        return view('customer.orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order, ReturnService $returns)
    {
        Gate::authorize('view', $order);

        $order->load('items.review', 'items.product', 'payment', 'shipment.shippingCompany');

        $canRequestReturn = $order->items->contains(fn ($item) => $returns->returnableQuantity($item) > 0);

        return view('customer.orders.show', ['order' => $order, 'canRequestReturn' => $canRequestReturn]);
    }

    public function cancel(Request $request, Order $order, CustomerOrderCanceller $canceller)
    {
        Gate::authorize('cancel', $order);
        abort_unless($order->status === 'pending', 409, $canceller::NOT_CANCELLABLE);
        abort_unless($canceller->cancel($order), 409, $canceller::NOT_CANCELLABLE);

        return back()->with('success', 'تم إلغاء الطلب وإرجاع المنتجات للمخزون.');
    }
}
