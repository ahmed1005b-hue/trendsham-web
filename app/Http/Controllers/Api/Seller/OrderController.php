<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Services\Pdf\PdfService;
use App\Services\SellerOrderService;
use App\Shipping\ParcelLabel;
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
            ->mapWithKeys(fn (Order $o) => [$o->id => $this->orders->nextStatuses($o, $vendor)]);
        // nextStatuses ينشئ طرود الطلبات القديمة إن نقصت: نعيد تحميلها
        $items->getCollection()->pluck('order')->unique('id')->each->load('shipments');

        return response()->json([
            'data' => $items->getCollection()->map(function (OrderItem $i) use ($request, $next, $vendor) {
                // البائع يرى حالة طرده هو (جزء الطلب الخاص بمتجره)
                $parcel = $i->order->shipments->firstWhere('seller_profile_id', $vendor->id);

                return (new OrderItemResource($i))->resolve($request) + [
                    'order_id' => $i->order_id,
                    'next_statuses' => $next[$i->order_id] ?? [],
                    'parcel_status' => $parcel?->status,
                    'parcel_status_label' => $parcel?->statusLabel(),
                    'tracking_number' => $parcel?->tracking_number,
                ];
            })->values(),
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $vendor = $request->user()->sellerProfile;
        Gate::authorize('viewAsSeller', $order);

        $parcel = $this->orders->parcelFor($vendor, $order);
        $shared = ! $order->isSingleVendor(); // قبل تحميل بنود المتجر وحده

        // طرد البائع في هذا الطلب: كيف يُوصَّل، وكم يحصّل من الزبون، وما الخطوة التالية (مثل صفحة الطلب في الموقع)
        return (new OrderResource($order->load($this->vendorItems($vendor->id))))->additional(['parcel' => [
            'id' => $parcel->id,
            'status' => $parcel->status,
            'status_label' => __($parcel->statusLabel()),
            'delivery_mode' => $parcel->delivery_mode ?? 'platform',
            'delivery_mode_label' => __($parcel->deliveryModeLabel()),
            'seller_delivers' => $parcel->isSellerDelivered(),
            'cod_amount' => (int) $parcel->cod_amount / 100,
            'shipping_fee' => (int) $parcel->shipping_fee / 100,
            'tracking_number' => $parcel->tracking_number,
            'shipping_governorate' => $order->shipping_governorate,
            'is_shared' => $shared,
            'next_statuses' => $this->orders->nextStatuses($order, $vendor),
            'can_print_label' => ! in_array($parcel->status, Shipment::CLOSED, true),
        ]]);
    }

    /** ملصق الشحنة PDF (نفس ملصق الموقع). */
    public function label(Request $request, Order $order, ParcelLabel $label, PdfService $pdf)
    {
        Gate::authorize('viewAsSeller', $order);

        try {
            $bytes = $label->render($request->user()->sellerProfile, $order);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return $pdf->respond($bytes, $label->filename($order));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $vendor = $request->user()->sellerProfile;
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->orders->updateStatus($vendor, $order, $validated['status'], $validated['tracking_number'] ?? null);
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
