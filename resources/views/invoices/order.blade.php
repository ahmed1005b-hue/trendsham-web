<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-size: 12px; color: #1F2A24; direction: {{ $direction }}; }
        .header-table { width: 100%; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .brand { font-size: 22px; font-weight: bold; color: #0B5F4A; }
        .brand span { color: #C9962C; }
        .muted { color: #6B7A72; }
        .title { font-size: 16px; font-weight: bold; margin-bottom: 4px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { background: #0B5F4A; color: #fff; padding: 8px; text-align: start; font-size: 11px; }
        .items-table td { padding: 8px; border-bottom: 1px solid #ECE4D3; font-size: 11px; }
        .totals-table { width: 40%; margin-right: 0; margin-left: auto; border-collapse: collapse; }
        .totals-table td { padding: 5px 8px; font-size: 12px; }
        .totals-table .grand-total td { font-weight: bold; font-size: 14px; border-top: 2px solid #0B5F4A; padding-top: 8px; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 10px; color: #fff; }
        .badge-paid { background: #0B5F4A; }
        .badge-pending { background: #C9962C; }
        .footer { margin-top: 30px; text-align: center; color: #6B7A72; font-size: 10px; }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 50%;">
            <div class="brand">تريند<span>شام</span></div>
            <div class="muted">{{ __('سوق إلكتروني متعدد البائعين') }}</div>
        </td>
        <td style="width: 50%; text-align: left;">
            <div class="title">{{ __('فاتورة') }}</div>
            <div class="muted">{{ __('رقم الطلب') }}: {{ $order->order_number }}</div>
            <div class="muted">{{ __('التاريخ') }}: {{ $order->created_at->format('Y-m-d') }}</div>
        </td>
    </tr>
</table>

<table class="info-table">
    <tr>
        <td style="width: 50%;">
            <strong>{{ __('بيانات العميل') }}</strong><br>
            {{ $order->shipping_name }}<br>
            {{ $order->shipping_phone }}<br>
            {{ $order->shipping_address }}
        </td>
        <td style="width: 50%; text-align: left;">
            <strong>{{ __('حالة الدفع') }}</strong><br>
            @if ($order->payment)
                @php $methodLabels = ['cash_on_delivery' => 'الدفع عند الاستلام', 'card' => 'بطاقة بنكية', 'wallet' => 'محفظة إلكترونية']; @endphp
                {{ __($methodLabels[$order->payment->method] ?? $order->payment->method) }}<br>
                <span class="badge {{ $order->payment->status === 'paid' ? 'badge-paid' : 'badge-pending' }}">
                    {{ __($order->payment->status === 'paid' ? 'مدفوع' : 'بانتظار الدفع') }}
                </span>
            @endif
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th>{{ __('المنتج') }}</th>
            <th>{{ __('البائع') }}</th>
            <th>{{ __('الكمية') }}</th>
            <th>{{ __('سعر الوحدة') }}</th>
            <th>{{ __('الإجمالي') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td translate="no">{{ $item->product_name }}@if ($item->is_gift) ({{ __('هدية') }})@endif</td>
                <td translate="no">{{ $item->vendor->store_name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->unit_price / 100) }} {{ __('ل.س') }}</td>
                <td>{{ number_format($item->subtotal / 100) }} {{ __('ل.س') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals-table">
    <tr>
        <td>{{ __('المجموع الفرعي') }}</td>
        <td>{{ number_format($order->subtotal / 100) }} {{ __('ل.س') }}</td>
    </tr>
    @if ($order->discount_amount > 0)
        <tr>
            <td>{{ __('الخصم') }}</td>
            <td>- {{ number_format($order->discount_amount / 100) }} {{ __('ل.س') }}</td>
        </tr>
    @endif
    <tr>
        <td>{{ __('رسوم الشحن') }}</td>
        <td>{{ number_format($order->shipping_fee / 100) }} {{ __('ل.س') }}</td>
    </tr>
    <tr class="grand-total">
        <td>{{ __('الإجمالي') }}</td>
        <td>{{ number_format($order->total / 100) }} {{ __('ل.س') }}</td>
    </tr>
</table>

<div class="footer">
    {{ __('تريندشام - سوق إلكتروني يجمع بائعين من كل المحافظات في مكان واحد.') }}<br>
    {{ __('هذه فاتورة إلكترونية صادرة تلقائياً ولا تتطلب توقيعاً أو ختماً.') }}
</div>

@include('pdf._verify-stamp')

</body>
</html>
