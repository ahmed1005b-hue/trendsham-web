@extends('layouts.app')

@section('title', 'تفاصيل الطلب - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - طلب {{ $order->order_number }}</span>
        <div>
            <a href="{{ route('invoices.show', $order) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> الفاتورة
            </a>
            <a href="{{ route($employeeMode ? 'employee.orders.index' : 'admin.orders.index') }}" class="btn btn-outline-secondary btn-sm">كل الطلبات</a>
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

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">المنتجات</h2>
                    <div class="table-responsive">
                    <table class="table small">
                        <thead><tr><th>المنتج</th><th>البائع</th><th>الكمية</th><th>الإجمالي</th><th>حالة العمولة</th></tr></thead>
                        <tbody>
                            @foreach ($order->items as $item)
                                <tr>
                                    <td translate="no">{{ $item->product_name }} @if ($item->is_gift) <span class="badge text-bg-warning">هدية</span>@endif</td>
                                    <td translate="no">{{ $item->vendor->store_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ $item->formatted_subtotal }}</td>
                                    <td>
                                        @if ($item->commission_settled_at)
                                            <span class="badge bg-success">تمت التسوية</span>
                                        @else
                                            <span class="badge bg-secondary">بانتظار التسليم</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-2">عنوان التوصيل</h2>
                    <p class="mb-1">
                        <span class="badge bg-light text-dark border"><i class="bi {{ $order->delivery_method === 'center' ? 'bi-shop' : 'bi-house-door' }}" aria-hidden="true"></i>
                            {{ $order->delivery_method === 'center' ? __('استلام من مركز شحن') : __('توصيل للبيت') }}</span>
                    </p>
                    <p class="mb-1">{{ $order->shipping_name }} — {{ $order->shipping_phone }}</p>
                    <p class="text-muted mb-0">{{ $order->shipping_address }}</p>
                    @if ((int) $order->shipping_debt_charged > 0)
                        <p class="small text-danger mt-2 mb-0">{{ __('يتضمن الإجمالي :fee أجرة شحن طلب رفضه الزبون سابقاً.', ['fee' => number_format($order->shipping_debt_charged / 100).' ل.س']) }}</p>
                    @endif
                    @if ($order->refused_at)
                        <p class="small text-danger mt-2 mb-0"><i class="bi bi-x-octagon" aria-hidden="true"></i> {{ __('رفض الزبون الاستلام في :date.', ['date' => $order->refused_at->format('Y-m-d')]) }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">تحديث حالة الطلب</h2>
                    @php
                        $statusRank = \App\Shipping\ParcelService::RANK;
                        $paidOrder = $order->needsRefundBeforeCancel();
                        $shippedOrder = \App\Services\StaffOrderStatusChanger::hasShipped($order);
                    @endphp
                    @if ($order->status === 'cancelled')
                        <p class="text-muted small mb-0">{{ __('الطلب ملغى.') }}</p>
                    @else
                        {{-- للأمام فقط؛ «مدفوع» من زر تأكيد الدفع؛ الإلغاء قبل الشحن ولغير المدفوع --}}
                        <form action="{{ route($employeeMode ? 'employee.orders.updateStatus' : 'admin.orders.updateStatus', $order) }}" method="POST" id="tsOrderStatusForm">
                            @csrf @method('PUT')
                            <div class="d-flex gap-2">
                                <select name="status" class="form-select" id="tsOrderStatus">
                                    <option value="" disabled @selected(! old('status'))>{{ __(\App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status) }} ({{ __('الحالة الحالية') }})</option>
                                    @foreach (\App\Services\StaffOrderStatusChanger::STATUSES as $value)
                                        @continue($value !== 'cancelled' && ($statusRank[$value] ?? 0) <= ($statusRank[$order->status] ?? 0))
                                        @continue($value === 'cancelled' && ($paidOrder || $shippedOrder))
                                        <option value="{{ $value }}" @selected(old('status') === $value)>{{ __(\App\Models\Order::STATUS_LABELS[$value]) }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-primary">حفظ</button>
                            </div>
                            <div class="mt-2" id="tsCancelReason" @if (old('status') !== 'cancelled') hidden @endif>
                                <label for="f-reason" class="form-label small mb-1">{{ __('سبب الإلغاء (يصل للزبون والبائع)') }}</label>
                                <textarea name="reason" id="f-reason" rows="2" maxlength="500" class="form-control form-control-sm @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                                @error('reason') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>
                        </form>
                        <script>
                        (() => {
                            const select = document.getElementById('tsOrderStatus');
                            const box = document.getElementById('tsCancelReason');
                            const sync = () => { const c = select.value === 'cancelled'; box.hidden = !c; box.querySelector('textarea').required = c; };
                            select.addEventListener('change', sync); sync();
                        })();
                        </script>
                        <p class="text-muted small mt-2 mb-0">{{ __('الحالة تتقدم للأمام فقط. تحديد «تم التسليم» يحتسب أرباح البائعين تلقائياً ويودعها بمحافظهم.') }}</p>
                        @if ($shippedOrder)
                            <p class="alert alert-light border small mt-2 mb-0">{{ __(\App\Services\StaffOrderStatusChanger::SHIPPED_CANCEL_MESSAGE) }}</p>
                        @elseif ($paidOrder && $employeeMode)
                            <p class="alert alert-light border small mt-2 mb-0">{{ __(\App\Services\StaffOrderStatusChanger::PAID_CANCEL_MESSAGE) }}</p>
                        @elseif ($paidOrder)
                            <details class="mt-3 border rounded p-2" @if ($errors->hasAny(['refund_method']) || old('refund_method')) open @endif>
                                <summary class="small fw-bold text-danger">{{ __('إلغاء الطلب المدفوع وتسجيل رد المال') }}</summary>
                                <form action="{{ route('admin.orders.cancelRefund', $order) }}" method="POST" class="mt-2"
                                    data-confirm="{{ __('ألغِ الطلب وسجّل أنك رددت :amount للزبون؟', ['amount' => number_format($order->total / 100).' ل.س']) }}">
                                    @csrf
                                    <p class="small mb-2">{{ __('المبلغ المدفوع الذي يجب رده: :amount. رصيد الهدايا (إن وُجد) يعود للزبون تلقائياً.', ['amount' => number_format($order->total / 100).' ل.س']) }}</p>
                                    <label for="f-refund_method" class="form-label small mb-1">{{ __('كيف رُدّ المال؟') }}</label>
                                    <select name="refund_method" id="f-refund_method" required class="form-select form-select-sm mb-2 @error('refund_method') is-invalid @enderror">
                                        <option value="" disabled @selected(! old('refund_method'))>{{ __('اختر') }}</option>
                                        @foreach (\App\Services\OrderRefundCanceller::METHODS as $value => $label)
                                            <option value="{{ $value }}" @selected(old('refund_method') === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="refund_note" maxlength="255" value="{{ old('refund_note') }}" class="form-control form-control-sm mb-2" placeholder="{{ __('رقم العملية أو ملاحظة (اختياري)') }}">
                                    <textarea name="reason" rows="2" maxlength="500" required class="form-control form-control-sm mb-2" placeholder="{{ __('سبب الإلغاء (يصل للزبون والبائع)') }}"></textarea>
                                    <button class="btn btn-sm btn-danger w-100">{{ __('ألغِ الطلب وسجّل رد المال') }}</button>
                                </form>
                            </details>
                        @endif
                    @endif
                    @if ($employeeMode && $order->isAwaitingOnlinePayment())
                        <p class="alert alert-warning small mt-2 mb-0">{{ __('هذا الطلب ينتظر دفع البطاقة، فلا يُجهَّز قبل تأكيد البنك. يمكنك إلغاؤه فقط.') }}</p>
                    @endif
                    @foreach ($order->shipments->filter(fn ($p) => in_array($p->status, ['shipped', 'in_transit'], true) && ! $p->refused_at) as $parcel)
                        <form action="{{ route($employeeMode ? 'employee.orders.refused' : 'admin.orders.refused', [$order, $parcel]) }}" method="POST" class="mt-3"
                            data-confirm="{{ __('الزبون رفض استلام الطرد؟ يُلغى الطرد وتعود كمياته، وتُسجَّل أجرة شحنه ديناً عليه ويُمنع من الدفع عند الاستلام شهراً.') }}">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-x-octagon" aria-hidden="true"></i>
                                {{ __('الزبون رفض الاستلام') }}@if ($order->shipments->count() > 1) — <span translate="no">{{ $parcel->seller?->store_name }}</span>@endif</button>
                        </form>
                    @endforeach
                    @if ($employeeMode && ! $order->archived_at)
                        <form action="{{ route('employee.orders.archive', $order) }}" method="POST" class="mt-3" data-confirm="أرشفة الطلب مع الاحتفاظ بسجله؟">
                            @csrf
                            <button class="btn btn-outline-secondary btn-sm w-100">أرشفة الطلب</button>
                        </form>
                    @endif
                </div>
            </div>

            @if (! $employeeMode && $order->payment)
                @php $methodLabels = ['cash_on_delivery' => 'الدفع عند الاستلام', 'card' => 'بطاقة بنكية', 'wallet' => 'محفظة إلكترونية']; @endphp
                @php $paymentStatusLabels = ['pending' => 'بانتظار الدفع', 'paid' => 'مدفوع', 'failed' => 'فشل', 'refunded' => 'مسترجع']; @endphp
                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-2">الدفع</h2>
                        <p class="small mb-1">الطريقة: {{ $methodLabels[$order->payment->method] ?? $order->payment->method }}</p>
                        <p class="small mb-2">
                            الحالة:
                            <span class="badge bg-{{ $order->payment->status === 'paid' ? 'success' : ($order->payment->status === 'failed' ? 'danger' : 'secondary') }}">
                                {{ $paymentStatusLabels[$order->payment->status] ?? $order->payment->status }}
                            </span>
                        </p>

                        @if ($order->payment->status === 'pending')
                            <form action="{{ route('admin.payments.markPaid', $order) }}" method="POST" class="d-flex gap-2 mb-2">
                                @csrf
                                <input type="text" name="transaction_ref" class="form-control form-control-sm" placeholder="رقم مرجع العملية (اختياري)">
                                <button class="btn btn-sm btn-success">تأكيد الدفع</button>
                            </form>
                            <form action="{{ route('admin.payments.markFailed', $order) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger w-100">تعليم كفاشل</button>
                            </form>
                        @elseif ($order->payment->transaction_ref)
                            <p class="text-muted small mb-0">رقم المرجع: {{ $order->payment->transaction_ref }}</p>
                        @endif

                        <p class="text-muted small mt-2 mb-0">
                            هذا تأكيد يدوي (تحويل بنكي أو دفع عند الاستلام) بانتظار ربط بوابة دفع إلكترونية فعلية.
                        </p>
                    </div>
                </div>
            @endif

            @if (! $employeeMode && $order->shipments->isNotEmpty())
                @foreach ($order->shipments as $shipment)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h2 class="h6 mb-1">{{ __('طرد متجر') }} <span translate="no">{{ $shipment->seller?->store_name }}</span></h2>
                            <p class="small text-muted mb-3">
                                <span class="ts-status-label ts-status-{{ $shipment->status }}">{{ __($shipment->statusLabel()) }}</span>
                                · {{ __('أجرة الشحن') }} {{ number_format($shipment->shipping_fee / 100) }} ل.س
                                @if ($shipment->refund_amount > 0)
                                    · {{ __('أُعيد للزبون :amount', ['amount' => number_format($shipment->refund_amount / 100).' ل.س']) }}
                                @endif
                                @if ($shipment->refused_at)
                                    · <span class="text-danger">{{ __('رفض الزبون الاستلام') }}</span>
                                @endif
                            </p>
                            <form action="{{ route('admin.shipments.update', [$order, $shipment]) }}" method="POST">
                                @csrf @method('PUT')
                                <div class="mb-2">
                                    <label class="form-label small">شركة الشحن</label>
                                    <select name="shipping_company_id" class="form-select form-select-sm">
                                        <option value="">— بدون —</option>
                                        @foreach (\App\Models\ShippingCompany::where('is_active', true)->get() as $company)
                                            <option value="{{ $company->id }}" @selected($shipment->shipping_company_id === $company->id)>{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">رقم التتبع</label>
                                    <input type="text" name="tracking_number" class="form-control form-control-sm" value="{{ $shipment->tracking_number }}">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">{{ __('حالة الطرد') }}</label>
                                    <select name="status" class="form-select form-select-sm" @disabled(! $shipment->isOpen())>
                                        @foreach (\Illuminate\Support\Arr::except(\App\Models\Shipment::STATUS_LABELS, $shipment->status === 'returned' ? [] : ['returned']) as $value => $label)
                                            <option value="{{ $value }}" @selected($shipment->status === $value)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    @unless ($shipment->isOpen())
                                        <input type="hidden" name="status" value="{{ $shipment->status }}">
                                    @endunless
                                </div>
                                <button class="btn btn-sm btn-primary w-100">{{ __('حفظ بيانات الطرد') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
