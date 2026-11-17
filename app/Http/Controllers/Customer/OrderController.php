<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Returns\ReturnService;
use App\Services\CustomerOrderCanceller;
use App\Shipping\ParcelService;
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

        app(ParcelService::class)->ensureFor($order);
        $order->load('items.review', 'items.product', 'payment', 'shipments.seller', 'shipments.shippingCompany');

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

    /** إلغاء طرد متجر واحد من الطلب ما دام «قيد المراجعة». */
    public function cancelParcel(Order $order, Shipment $shipment, CustomerOrderCanceller $canceller)
    {
        Gate::authorize('cancel', $order);
        abort_unless($shipment->order_id === $order->id, 404);
        abort_unless($canceller->cancelParcel($order, $shipment), 409, $canceller::NOT_CANCELLABLE);

        return back()->with('success', __('أُلغي طرد هذا المتجر وأُرجعت منتجاته، وبقية الطلب مستمرة.'));
    }
}
