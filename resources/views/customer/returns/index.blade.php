@extends('layouts.customer-account')

@section('title', __('طلبات الإرجاع'))

@section('account_content')
    <div class="ts-account-card">
        <div class="ts-account-head"><h1>{{ __('طلبات الإرجاع') }}</h1></div>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('رقم الطلب') }}</th><th>{{ __('الطلب') }}</th><th>{{ __('المتجر') }}</th><th>{{ __('المبلغ') }}</th><th>{{ __('الحالة') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($returnRequests as $returnRequest)
                        <tr>
                            <td class="fw-semibold">{{ $returnRequest->number }}</td>
                            <td class="text-muted small">{{ $returnRequest->order->order_number }}</td>
                            <td>{{ $returnRequest->seller?->store_name }}</td>
                            <td>{{ number_format($returnRequest->refund_amount / 100) }} ل.س</td>
                            <td><span class="ts-order-chip">{{ __(\App\Returns\Models\ReturnRequest::STATUS_LABELS[$returnRequest->status] ?? $returnRequest->status) }}</span></td>
                            <td class="text-end"><a href="{{ route('customer.returns.show', $returnRequest) }}" class="btn btn-sm btn-outline-secondary">{{ __('التفاصيل') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ __('لا توجد طلبات إرجاع بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $returnRequests->links() }}</div>
    </div>
@endsection
