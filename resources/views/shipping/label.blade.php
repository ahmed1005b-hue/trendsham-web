<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-size: 11px; color: #111; direction: {{ $direction }}; }
        .box { border: 1.5px solid #111; padding: 6px 8px; margin-bottom: 6px; }
        .head td { vertical-align: middle; }
        .brand { font-size: 18px; font-weight: bold; color: #0B5F4A; }
        .brand span { color: #C9962C; }
        .order-no { font-size: 15px; font-weight: bold; }
        .cap { font-size: 9px; color: #555; margin-bottom: 2px; }
        .name { font-size: 15px; font-weight: bold; }
        .phone { font-size: 14px; font-weight: bold; }
        .big { font-size: 13px; }
        .cod { border: 2.5px solid #111; padding: 8px; text-align: center; margin-bottom: 6px; }
        .cod .amount { font-size: 22px; font-weight: bold; }
        .paid { border: 1.5px dashed #111; padding: 8px; text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 6px; }
        .items td { font-size: 10px; padding: 1px 0; }
        .muted { color: #555; font-size: 9px; }
    </style>
</head>
<body>

<table class="head" width="100%">
    <tr>
        <td>
            <div class="brand">تريند<span>شام</span></div>
            <div class="cap">{{ __('رقم الطلب') }}</div>
            <div class="order-no" dir="ltr">{{ $order->order_number }}</div>
            <div class="muted">{{ __('طرد رقم') }} <span dir="ltr">P{{ $parcel->id }}</span> · {{ $order->created_at?->format('Y-m-d') }}</div>
        </td>
        <td width="30mm" style="text-align: center;">
            <barcode code="{{ $qr }}" type="QR" size="0.9" error="M" disableborder="1" />
        </td>
    </tr>
</table>

<div class="box">
    <div class="cap">{{ __('إلى (المستلم)') }}</div>
    <div class="name">{{ $order->shipping_name }}</div>
    <div class="phone"><span dir="ltr">{{ $order->shipping_phone }}</span></div>
    @if ($parcel->delivery_mode === 'pickup')
        <div class="big"><strong>{{ __('يستلمه الزبون من المحل') }}</strong></div>
    @else
        @if ($order->shipping_governorate)
            <div class="big"><strong>{{ __($order->shipping_governorate) }}</strong></div>
        @endif
        <div class="big">{{ $order->shipping_address }}</div>
    @endif
</div>

@if ($cod > 0)
    <div class="cod">
        <div>{{ __('المبلغ المطلوب من الزبون عند التسليم') }}</div>
        <div class="amount">{{ number_format($cod / 100) }} {{ __('ل.س') }}</div>
    </div>
@else
    <div class="paid">{{ __('مدفوع مسبقاً — لا تحصّل أي مبلغ') }}</div>
@endif

<div class="box">
    <div class="cap">{{ __('من (المتجر)') }}</div>
    <div class="big"><strong>{{ $vendor->store_name }}</strong></div>
    @if ($vendor->store_contact_phone)
        <div><span dir="ltr">{{ $vendor->store_contact_phone }}</span></div>
    @endif
    <div class="muted">
        {{ __('التوصيل:') }} {{ __($parcel->deliveryModeLabel()) }}
        @if ($parcel->shippingCompany)
            · {{ $parcel->shippingCompany->name }}
        @endif
        @if ($parcel->tracking_number)
            · {{ __('رقم التتبع:') }} <span dir="ltr">{{ $parcel->tracking_number }}</span>
        @endif
    </div>
</div>

<div class="cap">{{ __('محتويات الطرد') }}</div>
<table class="items" width="100%">
    @foreach ($items->take(6) as $item)
        <tr>
            <td>{{ $item->product_name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif</td>
            <td width="12mm" style="text-align: center;" dir="ltr">× {{ $item->quantity }}</td>
        </tr>
    @endforeach
    @if ($items->count() > 6)
        <tr><td colspan="2" class="muted">{{ __('و:count منتجات أخرى', ['count' => $items->count() - 6]) }}</td></tr>
    @endif
</table>

</body>
</html>
