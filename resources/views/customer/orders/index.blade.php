@extends('layouts.customer-account')

@section('title', 'طلباتي')

@section('account_content')
    <div class="ts-account-card">
        <div class="ts-account-head"><h1>جميع طلباتي</h1></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>رقم الطلب</th><th>التاريخ</th><th>الإجمالي</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $label = \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status;
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $order->order_number }}</td>
                            <td class="text-muted small">{{ $order->created_at->format('Y-m-d') }}</td>
                            <td>{{ $order->formatted_total }}</td>
                            <td><span class="ts-order-chip {{ $order->status }}">{{ $label }}</span></td>
                            <td class="text-end"><a href="{{ route('customer.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">التفاصيل</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا توجد طلبات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    </div>
@endsection
