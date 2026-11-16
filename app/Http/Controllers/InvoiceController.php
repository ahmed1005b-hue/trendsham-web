<?php

namespace App\Http\Controllers;

use App\Documents\DocumentRegistry;
use App\Documents\DocumentSnapshots;
use App\Models\Order;
use App\Services\Pdf\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function __construct(private readonly PdfService $pdf, private readonly DocumentRegistry $registry) {}

    public function show(Request $request, Order $order)
    {
        Gate::authorize('invoice', $order);

        $order->load('items.vendor', 'payment');

        // فاتورة مسجّلة برمز تحقق؛ نفس الفاتورة بلا تغيير ← نفس الرمز والملف
        [$summary, $details] = DocumentSnapshots::invoice($order);
        $issued = $this->registry->issue('invoice', $order->id, $order->user_id, $summary, $details,
            fn (array $stamp) => $this->pdf->render('invoices.order', ['order' => $order, 'verify' => $stamp]));

        return $this->pdf->respond($issued->bytes, "invoice-{$order->order_number}.pdf");
    }
}
