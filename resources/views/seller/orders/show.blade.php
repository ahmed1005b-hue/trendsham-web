@extends('layouts.app')

@section('title', 'طلب #{{ $order->order_number }} - تريندشام')

@section('styles')
<style>
    .ts-order-header-card {
        background: #fff;
        border: 1px solid #ECE4D3;
        border-radius: 14px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .ts-order-item-card {
        background: #fff;
        border: 1px solid #ECE4D3;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .ts-order-thumb {
        width: 70px;
        height: 70px;
        border-radius: 10px;
        background: linear-gradient(160deg, #F3EEE1, #E9E1CC);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        flex-shrink: 0;
    }
    .ts-order-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .ts-order-item-card img { max-width: 100%; height: auto; display: block; }
    .ts-order-item-card .flex-grow-1 { min-width: 0; flex: 1 1 auto; }
    .ts-order-item-card > div { min-width: 0; }
    .ts-shipping-card {
        background: #FBF7F0;
        border: 1px solid #ECE4D3;
        border-radius: 14px;
        padding: 1.5rem;
    }
    .ts-status-select {
        background: #fff;
        border: 1px solid #ECE4D3;
        border-radius: 10px;
        padding: .5rem 1rem;
        font-family: 'Tajawal', sans-serif;
    }
    .ts-status-label {
        font-weight: 700;
        font-family: 'Reem Kufi', sans-serif;
        text-transform: uppercase;
        letter-spacing: .03em;
        font-size: .8rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid px-4">
    @if (session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif

    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الطلب</span>
            <a href="{{ route('seller.orders.index') }}" class="btn btn-outline-secondary btn-sm">كل الطلبات</a>
        </div>
    </nav>

    <!-- معلومات الطلب -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="ts-order-header-card">
                <h6 class="text-muted small mb-2">رقم الطلب</h6>
                <h3 class="mb-2 fw-bold">{{ $order->order_number }}</h3>
                <p class="mb-2"><span class="ts-status-label ts-status-{{ $parcel->status }}">{{ __($parcel->statusLabel()) }}</span></p>
                @if ($isShared)
                    <p class="small text-muted mb-2">{{ __('في الطلب منتجات من متاجر أخرى؛ أنت تدير طردك فقط.') }}</p>
                @endif
                <p class="mb-0 small text-muted">
                    <i class="bi bi-calendar me-1"></i>
                    {{ $order->created_at->format('d M Y, h:i A') }}
                </p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ts-order-header-card">
                <h6 class="text-muted small mb-2">المبلغ الإجمالي</h6>
                <h3 class="mb-0 fw-bold">{{ number_format($order->total / 100) }} ل.س</h3>
                @if ($order->shipping_fee > 0)
                    <p class="mb-0 small text-muted">رسوم الشحن: {{ number_format($order->shipping_fee / 100) }} ل.س</p>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ts-order-header-card">
                <h6 class="text-muted small mb-2">{{ __('حالة طردك') }}</h6>
                @if ($nextStatuses)
                    <form action="{{ route('seller.orders.updateStatus', $order) }}" method="POST"
                          data-confirm-when="status=cancelled" data-confirm="{{ __('إلغاء طردك نهائي ولا يمكن التراجع عنه، وسيُبلَّغ العميل. متأكد؟') }}">
                        @csrf
                        <div class="d-flex gap-2 mb-2">
                            <select name="status" class="ts-status-select">
                                @foreach ($nextStatuses as $key)
                                    <option value="{{ $key }}">{{ __(App\Models\Shipment::STATUS_LABELS[$key]) }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm px-3">تحديث</button>
                        </div>
                        <input type="text" name="tracking_number" class="form-control form-control-sm" maxlength="255" dir="ltr"
                               value="{{ old('tracking_number', $parcel->tracking_number) }}" placeholder="{{ __('رقم التتبع من شركة الشحن (عند الشحن)') }}">
                    </form>
                @else
                    <p class="small text-muted mb-0">{{ __('لا توجد خطوة تالية لطردك.') }}</p>
                @endif
                @if ($parcel->tracking_number)
                    <p class="small mt-2 mb-0">{{ __('رقم التتبع:') }} <span dir="ltr">{{ $parcel->tracking_number }}</span></p>
                @endif
            </div>
        </div>
    </div>

    <!-- معلومات العميل والشحن -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="ts-shipping-card">
                <h6 class="mb-3"><i class="bi bi-person me-1"></i> معلومات العميل</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>الاسم:</strong> {{ $order->user->name }}</p>
                        <p class="mb-1"><strong>الهاتف:</strong> {{ $order->shipping_phone }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>البريد الإلكتروني:</strong> {{ $order->user->email ?? 'غير محدد' }}</p>
                    </div>
                </div>
                <hr class="my-3">
                <h6 class="mb-3"><i class="bi bi-geo-alt me-1"></i> عنوان التوصيل</h6>
                <p class="mb-2"><span class="badge bg-light text-dark border"><i class="bi {{ $order->delivery_method === 'center' ? 'bi-shop' : 'bi-house-door' }}" aria-hidden="true"></i>
                    {{ $order->delivery_method === 'center' ? __('الزبون يستلم من مركز شحن') : __('توصيل للبيت') }}</span></p>
                <p class="mb-0">{{ $order->shipping_address }}</p>
                @php $sellerProfile = auth()->user()->sellerProfile; @endphp
                @if ($sellerProfile?->courier_pickup)
                    <p class="small text-muted mt-2 mb-0"><i class="bi bi-truck" aria-hidden="true"></i> {{ __('سيأتي مندوب كرم لاستلام الطرد من متجرك.') }}</p>
                @elseif ($sellerProfile?->dropoffCenter)
                    <p class="small text-muted mt-2 mb-0"><i class="bi bi-truck" aria-hidden="true"></i> {{ __('سلّم الطرد في مركز كرم:') }} <span translate="no">{{ $sellerProfile->dropoffCenter->name }} - {{ $sellerProfile->dropoffCenter->address }} ({{ $sellerProfile->dropoffCenter->phone }})</span></p>
                @else
                    <p class="small text-warning mt-2 mb-0"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ __('لم تختر مركز تسليم بعد.') }} <a href="{{ route('seller.store.edit') }}">{{ __('اختره من إعدادات المتجر') }}</a></p>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="ts-order-header-card">
                <h6 class="text-muted small mb-2">ملخص المنتجات</h6>
                <ul class="list-unstyled mb-0">
                    @php $totalItems = 0; @endphp
                    @foreach ($order->items->where('vendor_id', $vendor->id) as $item)
                        <li class="mb-2 d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold small translate-no">{{ $item->product_name }} @if ($item->is_gift) <span class="badge text-bg-warning">هدية</span>@endif</div>
                                <div class="text-muted small">× {{ $item->quantity }}</div>
                            </div>
                            <div class="fw-bold">{{ $item->formatted_subtotal }}</div>
                        </li>
                        @php $totalItems += $item->quantity; @endphp
                    @endforeach
                    <li class="mt-3 pt-3 border-top">
                        <div class="fw-bold d-flex justify-content-between">
                            <span>إجمالي منتجاتك:</span>
                            <span>{{ number_format($order->items->where('vendor_id', $vendor->id)->sum('subtotal') / 100) }} ل.س</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- تفاصيل المنتجات -->
    <div class="row mb-4">
        <div class="col-12">
            <h6 class="mb-3">تفاصيل طلباتك</h6>
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 10%;">الصورة</th>
                                <th style="width: 40%;">المنتج</th>
                                <th style="width: 15%;">الكمية</th>
                                <th style="width: 15%;">السعر الفردي</th>
                                <th style="width: 20%;">المجموع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items->where('vendor_id', $vendor->id) as $item)
                                <tr>
                                    <td>
                                        <div style="width: 50px; height: 50px; border-radius: 8px; background: linear-gradient(160deg, #F3EEE1, #E9E1CC); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                            @if ($item->product && $item->product->primaryImage)
                                                <img src="{{ $item->product->primaryImage->thumb_url }}" alt="{{ $item->product_name }}" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                                            @else
                                                <i class="bi bi-box-seam" style="color: var(--ts-emerald); font-size: 1.5rem;"></i>
                                            @endif
                                        </div>
                                    </td>
                                    <td translate="no">{{ $item->product_name }} @if ($item->is_gift) <span class="badge text-bg-warning">هدية</span>@endif</td>
                                    <td>{{ $item->quantity }} وحدة</td>
                                    <td>{{ number_format($item->unit_price / 100) }} ل.س</td>
                                    <td class="fw-bold">{{ $item->formatted_subtotal }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
