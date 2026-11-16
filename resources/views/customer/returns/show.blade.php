@extends('layouts.customer-account')

@section('title', __('طلب إرجاع :number', ['number' => $returnRequest->number]))

@section('account_content')
    @php
        $canWithdraw = in_array($returnRequest->status, ['requested', 'approved'], true);
        $canDispute = $returnRequest->status === 'rejected' && $returnRequest->dispute_by && ! $returnRequest->dispute_by->isPast();
    @endphp
    <div class="ts-account-card">
        <div class="ts-account-head">
            <h1>{{ __('طلب إرجاع :number', ['number' => $returnRequest->number]) }}</h1>
            <a href="{{ route('customer.returns.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('كل طلبات الإرجاع') }}</a>
        </div>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <p class="mb-1">{{ __('الحالة') }}: <span class="ts-order-chip">{{ __(\App\Returns\Models\ReturnRequest::STATUS_LABELS[$returnRequest->status] ?? $returnRequest->status) }}</span></p>
        <p class="small mb-1">{{ __('الطلب') }}: <a href="{{ route('customer.orders.show', $returnRequest->order) }}">{{ $returnRequest->order->order_number }}</a> — {{ $returnRequest->seller?->store_name }}</p>
        <p class="small mb-1">{{ __('السبب') }}: {{ __(\App\Returns\Models\ReturnRequest::REASON_LABELS[$returnRequest->reason] ??$returnRequest->reason) }}</p>
        @if ($returnRequest->description)
            <p class="small mb-1">{{ $returnRequest->description }}</p>
        @endif
        @if ($returnRequest->decision_note)
            <p class="small mb-1">{{ __('ملاحظة القرار') }}: {{ $returnRequest->decision_note }}</p>
        @endif

        <h2 class="h6 mt-3">{{ __('المنتجات') }}</h2>
        @foreach ($returnRequest->items as $line)
            <div class="d-flex justify-content-between small border-bottom py-1">
                <span><span translate="no">{{ $line->orderItem->product_name }}</span> × {{ $line->quantity }}</span>
                <span>{{ number_format($line->refund_amount / 100) }} ل.س</span>
            </div>
        @endforeach
        @if ($returnRequest->shipping_refund > 0)
            <div class="d-flex justify-content-between small py-1">
                <span>{{ __('استرداد الشحن') }}</span>
                <span>{{ number_format($returnRequest->shipping_refund / 100) }} ل.س</span>
            </div>
        @endif
        <div class="d-flex justify-content-between fw-bold mt-2">
            <span>{{ __('إجمالي الاسترداد') }}</span>
            <span>{{ number_format($returnRequest->refund_amount / 100) }} ل.س</span>
        </div>

        <h2 class="h6 mt-4">{{ __('سجل الطلب') }}</h2>
        <ul class="list-unstyled small">
            @foreach ($returnRequest->events as $event)
                <li class="mb-2">
                    <strong>{{ __(\App\Returns\Models\ReturnRequest::EVENT_LABELS[$event->type] ??$event->type) }}</strong>
                    <span class="text-muted">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                    @if ($event->note)
                        <div>{{ $event->note }}</div>
                    @endif
                    @foreach ($event->photos ?? [] as $photo)
                        <a href="{{ asset('storage/'.$photo) }}" target="_blank"><img src="{{ asset('storage/'.$photo) }}" alt="" style="width:64px;height:64px;object-fit:cover" class="rounded border me-1"></a>
                    @endforeach
                </li>
            @endforeach
        </ul>

        @if ($canWithdraw)
            <form action="{{ route('customer.returns.withdraw', $returnRequest) }}" method="POST" data-confirm="{{ __('تأكيد سحب طلب الإرجاع؟') }}">
                @csrf
                <button class="btn btn-sm btn-outline-danger">{{ __('سحب الطلب') }}</button>
            </form>
        @endif

        @if ($canDispute)
            <form action="{{ route('customer.returns.dispute', $returnRequest) }}" method="POST" enctype="multipart/form-data" class="mt-3 p-3 bg-light rounded">
                @csrf
                <h2 class="h6">{{ __('رفع نزاع إلى الإدارة') }}</h2>
                <p class="small text-muted">{{ __('بإمكانك الاعتراض على رفض البائع قبل :date.', ['date' => $returnRequest->dispute_by->format('Y-m-d')]) }}</p>
                <textarea name="note" rows="3" maxlength="2000" class="form-control mb-2" placeholder="{{ __('اشرح سبب اعتراضك') }}" required>{{ old('note') }}</textarea>
                <input type="file" name="photos[]" accept="image/*" multiple class="form-control mb-2">
                <button class="btn btn-sm btn-primary">{{ __('رفع النزاع') }}</button>
            </form>
        @endif
    </div>
@endsection
