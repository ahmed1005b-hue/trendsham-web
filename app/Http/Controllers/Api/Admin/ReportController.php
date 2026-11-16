<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Admin\ReportController as WebReportController;
use Illuminate\Http\Request;

/**
 * تقارير المبيعات للتطبيق. نفس حسابات الويب، وexportPdf موروث كما هو (يعيد ملف PDF).
 */
class ReportController extends WebReportController
{
    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());
        $report = $this->buildReport($from, $to);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'orders_count' => $report['ordersCount'],
            'total_sales' => $report['totalSales'] / 100,
            'total_commission' => $report['totalCommission'] / 100,
            'total_vendor_earnings' => $report['totalVendorEarnings'] / 100,
            'total_withdrawals' => $report['totalWithdrawals'] / 100,
            'orders' => $report['orders']->take(100)->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'customer' => $o->user?->name,
                'status' => $o->status,
                'total' => $o->total / 100,
                'created_at' => $o->created_at?->toISOString(),
            ])->values(),
        ]);
    }
}
