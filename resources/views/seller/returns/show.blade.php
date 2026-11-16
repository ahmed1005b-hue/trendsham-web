@extends('layouts.app')

@section('title', __('طلب إرجاع :number', ['number' => $returnRequest->number]))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('طلب إرجاع :number', ['number' => $returnRequest->number]) }}</span>
        <a href="{{ route('seller.returns.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('كل المرتجعات') }}</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <p class="mb-1">{{ __('الحالة') }}: <span class="badge bg-secondary">{{ __(\App\Returns\Models\ReturnRequest::STATUS_LABELS[$returnRequest->status] ?? $returnRequest->status) }}</span></p>
            <p class="small mb-1">{{ __('الطلب') }}: <a href="{{ route('seller.orders.show', $returnRequest->order) }}">{{ $returnRequest->order->order_number }}</a> — {{ $returnRequest->customer?->name }}</p>
            <p class="small mb-1">{{ __('السبب') }}: {{ __(\App\Returns\Models\ReturnRequest::REASON_LABELS[$returnRequest->reason] ?? $returnRequest->reason) }}</p>
            @if ($returnRequest->description)
                <p class="small mb-1">{{ $returnRequest->description }}</p>
            @endif
            @if ($returnRequest->decision_note)
                <p class="small mb-1">{{ __('ملاحظة القرار') }}: {{ $returnRequest->decision_note }}</p>
            @endif
            @if ($returnRequest->status === 'requested' && $returnRequest->respond_by)
                <p class="small text-muted mb-1">{{ __('يرجى الرد قبل :date وإلا يُحوَّل الطلب للإدارة.', ['date' => $returnRequest->respond_by->format('Y-m-d H:i')]) }}</p>
            @endif

            <h2 class="h6 mt-3">{{ __('المنتجات') }}</h2>
            @include('partials.return-lines')
        </div>

        <div class="col-lg-5">
            @if ($returnRequest->status === 'requested')
                <form action="{{ route('seller.returns.approve', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded">
                    @csrf
                    <h2 class="h6">{{ __('الموافقة على الإرجاع') }}</h2>
                    <textarea name="note" rows="2" maxlength="2000" class="form-control mb-2" placeholder="{{ __('ملاحظة للعميل (اختياري)') }}"></textarea>
                    <button class="btn btn-sm btn-success">{{ __('موافقة') }}</button>
                </form>
                <form action="{{ route('seller.returns.reject', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded">
                    @csrf
                    <h2 class="h6">{{ __('رفض الإرجاع') }}</h2>
                    <textarea name="note" rows="2" maxlength="2000" class="form-control mb-2" placeholder="{{ __('سبب الرفض (مطلوب)') }}" required>{{ old('note') }}</textarea>
                    <button class="btn btn-sm btn-outline-danger">{{ __('رفض') }}</button>
                </form>
            @endif

            @if ($returnRequest->status === 'approved')
                <form action="{{ route('seller.returns.received', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded" data-confirm="{{ __('تأكيد استلام المنتجات المرتجعة؟') }}">
                    @csrf
                    <h2 class="h6">{{ __('استلام المرتجع') }}</h2>
                    <p class="small text-muted">{{ __('أكّد الاستلام عند وصول المنتجات إليك؛ يُسوّى المبلغ على محفظتك فور التأكيد.') }}</p>
                    <button class="btn btn-sm btn-primary">{{ __('تأكيد الاستلام') }}</button>
                </form>
            @endif

            <h2 class="h6">{{ __('سجل الطلب') }}</h2>
            @include('partials.return-timeline')
        </div>
    </div>
</div>
@endsection
