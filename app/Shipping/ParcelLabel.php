<?php

namespace App\Shipping;

use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Services\Pdf\PdfService;
use App\Services\SellerOrderService;
use Illuminate\Support\Collection;

/**
 * ملصق الشحنة: ورقة بمقاس 10×15 سم يطبعها البائع ويلصقها على طرده.
 * فيها المستلم وعنوانه، والمتجر المرسل، والمبلغ الذي يُحصَّل عند التسليم، ورمز QR برقم الطلب.
 */
class ParcelLabel
{
    /** مقاس ملصقات الشحن الشائع (مم): يُطبع على طابعة ملصقات أو على ورقة عادية ويُقص */
    public const SIZE = [100, 150];

    public function __construct(
        private readonly PdfService $pdf,
        private readonly SellerOrderService $orders,
    ) {}

    /**
     * محتوى الملصق لطرد هذا المتجر.
     *
     * @return array{order: Order, parcel: Shipment, vendor: SellerProfile, items: Collection, qr: string, cod: int}
     *
     * @throws \DomainException طرد ملغي أو مرتجع لا يُشحن
     */
    public function data(SellerProfile $vendor, Order $order): array
    {
        $parcel = $this->orders->parcelFor($vendor, $order);
        if (in_array($parcel->status, Shipment::CLOSED, true)) {
            throw new \DomainException(__('هذا الطرد ملغي أو مرتجع، لا يحتاج ملصق شحنة.'));
        }
        $parcel->loadMissing('shippingCompany');

        return [
            'order' => $order,
            'parcel' => $parcel,
            'vendor' => $vendor,
            'items' => $order->items()->where('vendor_id', $vendor->id)
                ->get(['product_name', 'variant_label', 'quantity']),
            'qr' => $order->order_number.'-P'.$parcel->id,
            'cod' => (int) $parcel->cod_amount,
        ];
    }

    /** @throws \DomainException */
    public function render(SellerProfile $vendor, Order $order): string
    {
        return $this->pdf->render('shipping.label', $this->data($vendor, $order), [
            'format' => self::SIZE,
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
        ]);
    }

    public function filename(Order $order): string
    {
        return "label-{$order->order_number}.pdf";
    }
}
