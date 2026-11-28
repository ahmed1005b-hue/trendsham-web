@extends('layouts.app')

@section('title', 'تفاصيل الطلب - تريندشام')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - طلب {{ $order->order_number }}</span>
            <div>
                <a href="{{ route('invoices.show', $order) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> الفاتورة
                </a>
                <a href="{{ route('customer.orders.index') }}" class="btn btn-outline-secondary btn-sm">كل طلباتي</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($canRequestReturn)
            <div class="alert alert-light border d-flex justify-content-between align-items-center">
                <span class="small">{{ __('وصلك الطلب وفيه مشكلة؟ بتقدر تطلب إرجاع المنتجات ضمن مدة الإرجاع.') }}</span>
                <a href="{{ route('customer.returns.create', $order) }}" class="btn btn-sm btn-outline-danger">{{ __('طلب إرجاع') }}</a>
            </div>
        @endif

        @if ($order->status === 'pending' && $order->needsRefundBeforeCancel())
            <div class="alert alert-light border small">{{ __(\App\Services\CustomerOrderCanceller::PAID_NEEDS_SUPPORT) }}</div>
        @elseif ($order->status === 'pending')
            <div class="alert alert-light border d-flex justify-content-between align-items-center">
                <span class="small">طلبك لسا قيد المراجعة، بتقدر تلغيه لو غيّرت رأيك.</span>
                <form action="{{ route('customer.orders.cancel', $order) }}" method="POST"
                    data-confirm="تأكيد إلغاء الطلب؟">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger">إلغاء الطلب</button>
                </form>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-md-7">
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-3">المنتجات</h2>
                        @foreach ($order->items as $item)
                            <div class="d-flex justify-content-between small mb-2 pb-2 border-bottom">
                                <span translate="no">{{ $item->product_name }}</span> × {{ $item->quantity }} @if ($item->is_gift) <span class="badge text-bg-warning">هدية</span>@endif
                                <span>{{ $item->formatted_subtotal }}</span>
                            </div>
                            @if ($item->canBeReviewed())
                                <form action="{{ route('customer.reviews.store', $item) }}" method="POST"
                                    class="mb-3 p-2 bg-light rounded">
                                    @csrf
                                    <label class="small mb-1 d-block">قيّم <span
                                            translate="no">{{ $item->product_name }}</span>:</label>
                                    <div class="mb-2">
                                        @for ($i = 5; $i >= 1; $i--)
                                            <label class="me-1">
                                                <input type="radio" name="rating" value="{{ $i }}" class="form-check-input"
                                                    required>{{ $i }}
                                            </label>
                                        @endfor
                                    </div>
                                    <input type="text" name="comment" class="form-control form-control-sm mb-2"
                                        placeholder="تعليق (اختياري)">
                                    <button class="btn btn-sm btn-primary">إرسال التقييم</button>
                                </form>
                            @elseif ($item->review)
                                <p class="small text-success mb-3">{{ __('تم تقييمك لهذا المنتج بـ :rating/5', ['rating' => $item->review->rating]) }} ✓</p>
                            @endif
                        @endforeach
                        @if ($order->points_discount > 0)
                            <div class="d-flex justify-content-between small text-success mt-2">
                                <span><i class="bi bi-gem" aria-hidden="true"></i> {{ __('خصم النقاط (:points)', ['points' => \App\Loyalty\LoyaltyCalculator::localPointsLabel((int) $order->points_redeemed)]) }}</span>
                                <span>- {{ number_format($order->points_discount / 100) }} ل.س</span>
                            </div>
                        @endif
                        @if ((int) $order->shipping_debt_charged > 0)
                            <div class="d-flex justify-content-between small text-danger mt-1">
                                <span>{{ __('أجرة شحن طلب رفضت استلامه') }}</span>
                                <span>+ {{ number_format($order->shipping_debt_charged / 100) }} ل.س</span>
                            </div>
                        @endif
                        @if ($order->gift_balance_used > 0)
                            <div class="d-flex justify-content-between small text-success mt-1">
                                <span><i class="bi bi-gift" aria-hidden="true"></i> دُفع من رصيد الهدايا</span>
                                <span>- {{ number_format($order->gift_balance_used / 100) }} ل.س</span>
                            </div>
                        @endif
                        @php $earnedPoints = (int) \App\Loyalty\Models\LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earn')->value('points'); @endphp
                        @if ($earnedPoints > 0)
                            <div class="d-flex justify-content-between small mt-1" style="color:#9A7425">
                                <span><i class="bi bi-gem" aria-hidden="true"></i> النقاط المكتسبة</span>
                                <a href="{{ route('customer.loyalty.index') }}" style="color:inherit">+{{ $earnedPoints }}</a>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between fw-bold mt-2">
                            <span>الإجمالي</span>
                            <span>{{ $order->formatted_total }}</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-2">{{ $order->delivery_method === 'center' ? __('الاستلام من مركز شحن') : __('عنوان التوصيل') }}</h2>
                        <p class="mb-1">{{ $order->shipping_name }} — {{ $order->shipping_phone }}</p>
                        <p class="text-muted mb-0">{{ $order->shipping_address }}</p>
                        @if ($order->refused_at)
                            <p class="small text-danger mt-2 mb-0"><i class="bi bi-x-octagon" aria-hidden="true"></i> {{ __('رفضت استلام هذا الطلب.') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                @php $statusLabels = ['pending' => 'قيد المراجعة', 'paid' => 'مدفوع', 'processing' => 'قيد التجهيز', 'shipped' => 'تم الشحن', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي']; @endphp

                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-2">حالة الطلب</h2>
                        <span class="badge bg-secondary">{{ \App\Shipping\ParcelService::isPartiallyShipped($order) ? __('شُحن جزئياً') : ($statusLabels[$order->status] ?? $order->status) }}</span>
                        @if ($order->shipments->count() > 1)
                            <p class="small text-muted mt-2 mb-0">{{ __('طلبك من :n متاجر، وكل متجر يرسل طرده وحده. حالة كل طرد بالأسفل.', ['n' => $order->shipments->count()]) }}</p>
                        @endif
                    </div>
                </div>

                @if ($order->payment)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h2 class="h6 mb-2">الدفع</h2>
                            @php $methodLabels = ['cash_on_delivery' => 'الدفع عند الاستلام', 'card' => 'بطاقة بنكية', 'wallet' => 'محفظة إلكترونية']; @endphp
                            <p class="small mb-1">الطريقة:
                                {{ $methodLabels[$order->payment->method] ?? $order->payment->method }}</p>
                            <p class="small mb-0">الحالة:
                                {{ $statusLabels[$order->payment->status] ?? $order->payment->status }}</p>
                        </div>
                    </div>
                @endif

                @php $shipments = $order->shipments; @endphp
                @if ($shipments->isNotEmpty())
                    @foreach ($shipments as $shipment)
                        <div class="card mb-3">
                            <div class="card-body">
                                <h2 class="h6 mb-2">{{ $shipments->count() > 1 ? __('طرد متجر') : 'الشحن' }} @if($shipment->seller) - <span translate="no">{{ $shipment->seller->store_name }}</span> @endif</h2>
                                <p class="small mb-1">
                                    الحالة:
                                    <span class="ts-status-label ts-status-{{ $shipment->status }}">{{ __($shipment->statusLabel()) }}</span>
                                </p>
                                @if ($shipment->isSellerDelivered())
                                    <p class="small mb-1"><i class="bi {{ $shipment->delivery_mode === 'pickup' ? 'bi-shop' : 'bi-scooter' }}" aria-hidden="true"></i> {{ $shipment->deliveryModeLabel() }}
                                        @if ($shipment->delivery_mode === 'pickup' && $shipment->seller?->shippingSetting?->pickup_address)
                                            — {{ $shipment->seller->shippingSetting->pickup_address }}
                                        @endif
                                    </p>
                                @endif
                                @if ($shipment->cod_amount > 0 && $shipment->isOpen() && $shipment->status !== 'delivered' && $order->payment_status !== 'paid')
                                    <p class="small mb-1">{{ __('تدفع عند استلام هذا الطرد: :amount', ['amount' => number_format($shipment->cod_amount / 100).' ل.س']) }}</p>
                                @endif
                                @if ($shipments->count() > 1)
                                    <ul class="small text-muted mb-1 ps-3">
                                        @foreach ($order->items->where('vendor_id', $shipment->seller_profile_id) as $parcelItem)
                                            <li translate="no">{{ $parcelItem->product_name }} × {{ $parcelItem->quantity }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($shipment->refund_amount > 0)
                                    <p class="small text-success mb-1">{{ __('أُعيد لك :amount عن هذا الطرد.', ['amount' => number_format($shipment->refund_amount / 100).' ل.س']) }}</p>
                                @endif
                                @if ($shipment->status === 'pending' && $shipments->filter->isOpen()->count() > 1)
                                    <form action="{{ route('customer.orders.parcels.cancel', [$order, $shipment]) }}" method="POST" class="mb-2"
                                        data-confirm="{{ __('إلغاء طرد هذا المتجر فقط؟ بقية الطلب تستمر.') }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger">{{ __('إلغاء هذا الطرد') }}</button>
                                    </form>
                                @endif
                                @if ($shipment->shippingCompany)
                                    <p class="small mb-1">شركة الشحن: {{ $shipment->shippingCompany->name }}</p>
                                @endif
                                @if ($shipment->tracking_number)
                                    <p class="small mb-1">رقم التتبع: {{ $shipment->tracking_number }}</p>
                                @endif
                                @if ($shipment->seller)
                                    <form action="{{ route('stores.message', $shipment->seller) }}" method="POST" class="mt-2">
                                        @csrf
                                        <input type="hidden" name="order_id" value="{{ $order->id }}">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">مراسلة البائع</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
@endsection