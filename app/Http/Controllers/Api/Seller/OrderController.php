<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\SellerOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * طلبات منتجات البائع في التطبيق. نفس قواعد لوحة البائع في الموقع (SellerOrderService).
 * يرى البائع منتجاته فقط من كل طلب، لا منتجات المتاجر الأخرى في نفس الطلب.
 */
class OrderController extends Controller
{
    public function __construct(private readonly SellerOrderService $orders) {}

    /**
     * صف لكل منتج من منتجات البائع في طلباته (شكل OrderItemResource الذي يعرضه التطبيق)
     * + order_id للتحديث + next_statuses للحالات المسموحة الآن.
     */
    public function index(Request $request)
    {
        $vendor = $request->user()->sellerProfile;

        $items = OrderItem::where('vendor_id', $vendor->id)
            // تحميل مسبق: لا استعلام لكل صف (الحالات المسموحة + بيانات العرض)
            ->with(['order.items:id,order_id,vendor_id', 'product.primaryImage', 'product.images', 'review'])
            ->latest('id')
            ->paginate(20);

        $next = $items->getCollection()->pluck('order')->unique('id')
            ->mapWithKeys(fn (Order $o) => [$o->id => $this->orders->nextStatuses($o)]);

        return response()->json([
            'data' => $items->getCollection()->map(fn (OrderItem $i) => (new OrderItemResource($i))->resolve($request) + [
                'order_id' => $i->order_id,
                'next_statuses' => $next[$i->order_id] ?? [],
            ])->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $vendor = $request->user()->sellerProfile;
        Gate::authorize('viewAsSeller', $order);

        return new OrderResource($order->load($this->vendorItems($vendor->id)));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $vendor = $request->user()->sellerProfile;
        $validated = $request->validate(['status' => ['required', 'string']]);

        try {
            $this->orders->updateStatus($vendor, $order, $validated['status']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح',
            'data' => new OrderResource($order->fresh()->load($this->vendorItems($vendor->id))),
        ]);
    }

    private function vendorItems(int $vendorId): array
    {
        return ['items' => fn ($q) => $q->where('vendor_id', $vendorId)->with('product')];
    }
}
