<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WithdrawalRequest;
use App\Services\Pdf\PdfService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly PdfService $pdf)
    {
    }

    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            ...$this->buildReport($from, $to),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $data = [
            'from' => $from,
            'to' => $to,
            ...$this->buildReport($from, $to),
        ];

        [$summary, $details] = \App\Documents\DocumentSnapshots::report($data);
        $issued = app(\App\Documents\DocumentRegistry::class)->issue('admin_report', 0, $request->user()->id, $summary, $details,
            fn (array $stamp) => $this->pdf->render('reports.statement-pdf', $data + ['verify' => $stamp]));

        return $this->pdf->respond($issued->bytes, "statement-{$from}-to-{$to}.pdf", inline: false);
    }

    protected function buildReport(string $from, string $to): array
    {
        $range = ["{$from} 00:00:00", "{$to} 23:59:59"];

        $orders = Order::whereBetween('created_at', $range)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->with('user')
            ->latest()
            ->get();

        $settledItems = OrderItem::whereHas('order', fn ($q) => $q->whereBetween('created_at', $range))
            ->whereNotNull('commission_settled_at');

        return [
            'orders' => $orders,
            'ordersCount' => $orders->count(),
            'totalSales' => $orders->sum('total'),
            'totalCommission' => (clone $settledItems)->sum('commission_amount'),
            'totalVendorEarnings' => (clone $settledItems)->sum('vendor_earning'),
            'totalWithdrawals' => WithdrawalRequest::where('status', 'approved')
                ->whereBetween('processed_at', $range)
                ->sum('amount'),
        ];
    }
}
