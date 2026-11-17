@extends('layouts.app')

@section('title', __('طلب إرجاع :number', ['number' => $returnRequest->number]))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('طلب إرجاع :number', ['number' => $returnRequest->number]) }}</span>
        <a href="{{ route('admin.returns.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('كل المرتجعات') }}</a>
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
            <p class="small mb-1">{{ __('الطلب') }}: <a href="{{ route('admin.orders.show', $returnRequest->order) }}">{{ $returnRequest->order->order_number }}</a></p>
            <p class="small mb-1">{{ __('العميل') }}: {{ $returnRequest->customer?->name }} — {{ __('المتجر') }}: {{ $returnRequest->seller?->store_name }}</p>
            <p class="small mb-1">{{ __('السبب') }}: {{ __(\App\Returns\Models\ReturnRequest::REASON_LABELS[$returnRequest->reason] ?? $returnRequest->reason) }}</p>
            @if ($returnRequest->description)
                <p class="small mb-1">{{ $returnRequest->description }}</p>
            @endif
            @if ($returnRequest->decision_note)
                <p class="small mb-1">{{ __('ملاحظة القرار') }}: {{ $returnRequest->decision_note }}</p>
            @endif

            <h2 class="h6 mt-3">{{ __('المنتجات') }}</h2>
            @include('partials.return-lines')

            <div class="small text-muted mt-2">
                {{ __('رسوم شحن الطلب') }}: {{ number_format($returnRequest->order->shipping_fee / 100) }} ل.س
                — {{ __('مبلغ الاسترداد المستحق') }}: {{ number_format($returnRequest->refund_amount / 100) }} ل.س
            </div>

            @if ($returnRequest->refund)
                <div class="alert alert-success mt-3 small">
                    {{ __('تم تسجيل استرداد :amount ل.س', ['amount' => number_format($returnRequest->refund->amount / 100)]) }}
                    ({{ __($returnRequest->refund->methodLabel()) }}@if ($returnRequest->refund->reference) — {{ $returnRequest->refund->reference }}@endif)
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            @if ($returnRequest->status === 'escalated')
                <form action="{{ route('admin.returns.approve', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded">
                    @csrf
                    <h2 class="h6">{{ __('قرار الإدارة: موافقة') }}</h2>
                    <textarea name="note" rows="2" maxlength="2000" class="form-control mb-2" placeholder="{{ __('ملاحظة (اختياري)') }}"></textarea>
                    <button class="btn btn-sm btn-success">{{ __('موافقة') }}</button>
                </form>
                <form action="{{ route('admin.returns.reject', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded">
                    @csrf
                    <h2 class="h6">{{ __('قرار الإدارة: رفض') }}</h2>
                    <textarea name="note" rows="2" maxlength="2000" class="form-control mb-2" placeholder="{{ __('سبب الرفض (مطلوب)') }}" required>{{ old('note') }}</textarea>
                    <button class="btn btn-sm btn-outline-danger">{{ __('رفض') }}</button>
                </form>
            @endif

            @if ($returnRequest->status === 'approved')
                <form action="{{ route('admin.returns.received', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded" data-confirm="{{ __('تأكيد استلام المنتجات المرتجعة؟') }}">
                    @csrf
                    <h2 class="h6">{{ __('استلام المرتجع') }}</h2>
                    <button class="btn btn-sm btn-primary">{{ __('تأكيد الاستلام') }}</button>
                </form>
            @endif

            @if ($returnRequest->status === 'received')
                <form action="{{ route('admin.returns.refund', $returnRequest) }}" method="POST" class="mb-3 p-3 bg-light rounded">
                    @csrf
                    <h2 class="h6">{{ __('تسجيل الاسترداد') }}</h2>
                    <p class="small text-muted">{{ __('سجّل المبلغ الذي دفعته للعميل خارج النظام.') }}</p>
                    <label class="form-label small mb-1">{{ __('المبلغ (ل.س)') }}</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control mb-2" value="{{ old('amount', $returnRequest->refund_amount / 100) }}" required>
                    <label class="form-label small mb-1">{{ __('طريقة الاسترداد') }}</label>
                    <select name="method" class="form-select mb-2" required>
                        @foreach (\App\Returns\Models\ReturnRefund::METHOD_LABELS as $method => $methodLabel)
                            @continue($method === 'gift_balance' && (int) $returnRequest->order->gift_balance_used <= 0)
                            <option value="{{ $method }}" @selected(old('method', (int) $returnRequest->order->gift_balance_used > 0 ? 'gift_balance' : null) === $method)>{{ __($methodLabel) }}</option>
                        @endforeach
                    </select>
                    @if ((int) $returnRequest->order->gift_balance_used > 0)
                        <p class="small text-muted mb-2"><i class="bi bi-gift" aria-hidden="true"></i> {{ __('دُفع من هذا الطلب :amount برصيد الهدايا. الاسترداد إليه يُضاف فوراً لرصيد الزبون (المتبقي :left).', ['amount' => number_format($returnRequest->order->gift_balance_used / 100).' ل.س', 'left' => number_format(app(\App\GiftCards\GiftCardService::class)->refundableForOrder($returnRequest->order) / 100).' ل.س']) }}</p>
                    @endif
                    <label class="form-label small mb-1">{{ __('المرجع') }}</label>
                    <input type="text" name="reference" maxlength="255" class="form-control mb-2" value="{{ old('reference') }}">
                    <label class="form-label small mb-1">{{ __('شحن إضافي (ل.س) — اختياري، حتى :max', ['max' => number_format($returnRequest->order->shipping_fee / 100)]) }}</label>
                    <input type="number" step="0.01" min="0" name="extra_shipping" class="form-control mb-2" value="{{ old('extra_shipping') }}">
                    <label class="form-label small mb-1">{{ __('ملاحظة (مطلوبة عند إضافة شحن)') }}</label>
                    <textarea name="note" rows="2" maxlength="2000" class="form-control mb-2">{{ old('note') }}</textarea>
                    <button class="btn btn-sm btn-primary">{{ __('تسجيل الاسترداد') }}</button>
                </form>
            @endif

            <h2 class="h6">{{ __('سجل الطلب') }}</h2>
            @include('partials.return-timeline')
        </div>
    </div>
</div>
@endsection
