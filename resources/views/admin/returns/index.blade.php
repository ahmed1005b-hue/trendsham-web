@extends('layouts.app')

@section('title', __('المرتجعات').' - '.__('لوحة المدير'))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('المرتجعات') }}</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('لوحة المدير') }}</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @php
        $tabs = ['escalated' => ['نزاعات بحاجة لقرار', 'escalated'], 'awaiting_refund' => ['بانتظار الاسترداد', 'received'], 'all' => ['الكل', null]];
    @endphp
    <ul class="nav nav-pills gap-1 mb-3 flex-wrap">
        @foreach ($tabs as $key => [$label, $statusKey])
            @php $n = $statusKey ? ($counts[$statusKey] ?? 0) : 0; @endphp
            <li class="nav-item">
                <a class="nav-link py-1 px-3 {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.returns.index', ['tab' => $key]) }}">
                    {{ __($label) }} @if ($n)<span class="badge bg-secondary ms-1">{{ $n }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>{{ __('رقم الطلب') }}</th><th>{{ __('الطلب') }}</th><th>{{ __('العميل') }}</th><th>{{ __('المتجر') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($returnRequests as $returnRequest)
                    <tr>
                        <td class="fw-semibold">{{ $returnRequest->number }}</td>
                        <td class="text-muted small">{{ $returnRequest->order->order_number }}</td>
                        <td>{{ $returnRequest->customer?->name }}</td>
                        <td>{{ $returnRequest->seller?->store_name }}</td>
                        <td>{{ number_format($returnRequest->refund_amount / 100) }} ل.س</td>
                        <td><span class="badge bg-secondary">{{ __(\App\Returns\Models\ReturnRequest::STATUS_LABELS[$returnRequest->status] ?? $returnRequest->status) }}</span></td>
                        <td class="text-end"><a href="{{ route('admin.returns.show', $returnRequest) }}" class="btn btn-sm btn-outline-secondary">{{ __('التفاصيل') }}</a></td>
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
