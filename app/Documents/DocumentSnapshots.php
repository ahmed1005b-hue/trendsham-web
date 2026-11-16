<?php

namespace App\Documents;

use App\Models\Order;

/**
 * ما يُحفظ من كل مستند وقت إصداره: summary يراه أي أحد يمسح الرمز (بلا بيانات شخصية)،
 * و details لا يراها إلا صاحب المستند أو المدير.
 */
final class DocumentSnapshots
{
    /** @return array{0: array, 1: array} */
    public static function invoice(Order $order): array
    {
        $order->loadMissing('items.vendor', 'payment');

        $summary = [
            'number' => $order->order_number,
            'date' => $order->created_at?->format('Y-m-d'),
            'amount' => (int) $order->total,
            'amount_text' => number_format($order->total / 100).' ل.س',
        ];

        $details = [
            'customer' => $order->shipping_name,
            'phone' => $order->shipping_phone,
            'address' => $order->shipping_address,
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->product_name.($i->is_gift ? ' (هدية)' : ''),
                'qty' => (int) $i->quantity,
                'subtotal' => (int) $i->subtotal,
            ])->values()->all(),
            'stores' => $order->items->map(fn ($i) => $i->vendor?->store_name)->filter()->unique()->values()->all(),
            'subtotal' => (int) $order->subtotal,
            'discount' => (int) $order->discount_amount,
            'shipping' => (int) $order->shipping_fee,
            'payment' => $order->payment?->method,
        ];

        return [$summary, $details];
    }

    /** @return array{0: array, 1: array} */
    public static function statement(array $data): array
    {
        $summary = [
            'number' => $data['number'],
            'date' => $data['issuedAt']->format('Y-m-d'),
            'period' => $data['from']->format('Y-m-d').' — '.$data['to']->format('Y-m-d'),
            'amount' => round((float) $data['closing'], 2),
            'amount_text' => number_format((float) $data['closing'], 2).' ل.س (الرصيد الختامي)',
        ];

        $details = [
            'store' => $data['storeName'],
            'opening' => round((float) $data['opening'], 2),
            'closing' => round((float) $data['closing'], 2),
            'debit' => $data['totalDebit'],
            'credit' => $data['totalCredit'],
            'entries' => $data['entries']->count(),
            'checksum' => $data['checksum'],
        ];

        return [$summary, $details];
    }

    /** @return array{0: array, 1: array} */
    public static function report(array $data): array
    {
        $summary = [
            'number' => 'RPT-'.str_replace('-', '', $data['from']).'-'.str_replace('-', '', $data['to']),
            'date' => now()->format('Y-m-d'),
            'period' => $data['from'].' — '.$data['to'],
            'amount' => (int) $data['totalSales'],
            'amount_text' => number_format($data['totalSales'] / 100).' ل.س (إجمالي المبيعات)',
        ];

        $details = [
            'orders' => (int) $data['ordersCount'],
            'commission' => (int) $data['totalCommission'],
            'vendor_earnings' => (int) $data['totalVendorEarnings'],
            'withdrawals' => (int) $data['totalWithdrawals'],
            'order_numbers' => $data['orders']->pluck('order_number')->values()->all(),
        ];

        return [$summary, $details];
    }
}
