<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-size: 12px; color: #1F2A24; direction: {{ $direction }}; }
        .brand { font-size: 22px; font-weight: bold; color: #0B5F4A; margin-bottom: 2px; }
        .brand span { color: #C9962C; }
        .muted { color: #6B7A72; margin-bottom: 20px; }
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-table td { padding: 10px; border: 1px solid #ECE4D3; text-align: center; }
        .summary-table .label { font-size: 10px; color: #6B7A72; }
        .summary-table .value { font-size: 14px; font-weight: bold; color: #0B5F4A; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { background: #0B5F4A; color: #fff; padding: 6px; text-align: start; font-size: 10px; }
        .items-table td { padding: 6px; border-bottom: 1px solid #ECE4D3; font-size: 10px; }
        .footer { margin-top: 30px; text-align: center; color: #6B7A72; font-size: 9px; }
    </style>
</head>
<body>

<div class="brand">تريند<span>شام</span></div>
<div class="muted">{{ __('كشف حساب مالي — من') }} {{ $from }} {{ __('إلى') }} {{ $to }}</div>

<table class="summary-table">
    <tr>
        <td>
            <div class="label">{{ __('عدد الطلبات') }}</div>
            <div class="value">{{ $ordersCount }}</div>
        </td>
        <td>
            <div class="label">{{ __('إجمالي المبيعات') }}</div>
            <div class="value">{{ number_format($totalSales / 100) }} {{ __('ل.س') }}</div>
        </td>
        <td>
            <div class="label">{{ __('عمولة المنصة') }}</div>
            <div class="value">{{ number_format($totalCommission / 100) }} {{ __('ل.س') }}</div>
        </td>
        <td>
            <div class="label">{{ __('أرباح البائعين') }}</div>
            <div class="value">{{ number_format($totalVendorEarnings / 100) }} {{ __('ل.س') }}</div>
        </td>
        <td>
            <div class="label">{{ __('سحوبات معتمدة') }}</div>
            <div class="value">{{ number_format($totalWithdrawals / 100) }} {{ __('ل.س') }}</div>
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th>{{ __('رقم الطلب') }}</th>
            <th>{{ __('العميل') }}</th>
            <th>{{ __('التاريخ') }}</th>
            <th>{{ __('الإجمالي') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($orders as $order)
            <tr>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->user->name }}</td>
                <td>{{ $order->created_at->format('Y-m-d') }}</td>
                <td>{{ number_format($order->total / 100) }} {{ __('ل.س') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    {{ __('تريندشام - تم توليد هذا الكشف تلقائياً بتاريخ') }} {{ now()->format('Y-m-d H:i') }}
</div>

@include('pdf._verify-stamp')

</body>
</html>
