@extends('layouts.app')

@section('title', __('المرتجعات').' - '.__('لوحة البائع'))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('المرتجعات') }}</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('لوحة البائع') }}</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <ul class="nav nav-pills gap-1 mb-3 flex-wrap">
        <li class="nav-item"><a class="nav-link py-1 px-3 {{ $status === null ? 'active' : '' }}" href="{{ route('seller.returns.index') }}">{{ __('الكل') }}</a></li>
        @foreach (\App\Returns\Models\ReturnRequest::STATUS_LABELS as $key => $label)
            <li class="nav-item"><a class="nav-link py-1 px-3 {{ $status === $key ? 'active' : '' }}" href="{{ route('seller.returns.index', ['status' => $key]) }}">{{ __($label) }}</a></li>
        @endforeach
    </ul>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>{{ __('رقم الطلب') }}</th><th>{{ __('الطلب') }}</th><th>{{ __('العميل') }}</th><th>{{ __('السبب') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($returnRequests as $returnRequest)
                    <tr>
                        <td class="fw-semibold">{{ $returnRequest->number }}</td>
                        <td class="text-muted small">{{ $returnRequest->order->order_number }}</td>
                        <td>{{ $returnRequest->customer?->name }}</td>
                        <td class="small">{{ __(\App\Returns\Models\ReturnRequest::REASON_LABELS[$returnRequest->reason] ?? $returnRequest->reason) }}</td>
                        <td>{{ number_format($returnRequest->refund_amount / 100) }} ل.س</td>
                        <td><span class="badge bg-secondary">{{ __(\App\Returns\Models\ReturnRequest::STATUS_LABELS[$returnRequest->status] ?? $returnRequest->status) }}</span></td>
                        <td class="text-end"><a href="{{ route('seller.returns.show', $returnRequest) }}" class="btn btn-sm btn-outline-secondary">{{ __('التفاصيل') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('لا توجد طلبات إرجاع.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $returnRequests->links() }}</div>
</div>
@endsection
