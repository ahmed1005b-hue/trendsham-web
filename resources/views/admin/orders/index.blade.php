@extends('layouts.app')

@section('title', 'الطلبات - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ $employeeMode ? 'إدارة الطلبات' : 'كل الطلبات' }}</span>
        <a href="{{ route($employeeMode ? 'employee.dashboard' : 'admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة التحكم</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>رقم الطلب</th><th>العميل</th><th>الإجمالي</th><th>التاريخ</th><th>الحالة</th><th></th></tr>
                </thead>
                <tbody>
                    @php $statusLabels = ['pending' => 'قيد المراجعة', 'paid' => 'مدفوع', 'processing' => 'قيد التجهيز', 'shipped' => 'تم الشحن', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي']; @endphp
                    @forelse ($orders as $order)
                        <tr>
                            <td class="fw-bold">{{ $order->order_number }}</td>
                            <td>{{ $order->user->name }}</td>
                            <td>{{ $order->formatted_total }}</td>
                            <td>{{ $order->created_at->format('Y-m-d') }}</td>
                            <td><span class="badge bg-secondary">{{ $statusLabels[$order->status] ?? $order->status }}</span></td>
                            <td class="text-end">
                                <a href="{{ route($employeeMode ? 'employee.orders.show' : 'admin.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">التفاصيل</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لا يوجد طلبات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $orders->links() }}</div>
</div>
@endsection
