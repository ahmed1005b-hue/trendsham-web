@extends('layouts.app')

@section('title', 'كشف الحساب - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - كشف الحساب</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('admin.reports.index') }}" method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="f-from" class="form-label small">من تاريخ</label>
                    <input id="f-from" type="date" name="from" class="form-control" value="{{ $from }}">
                </div>
                <div class="col-md-3">
                    <label for="f-to" class="form-label small">إلى تاريخ</label>
                    <input id="f-to" type="date" name="to" class="form-control" value="{{ $to }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">تحديث</button>
                </div>
                <div class="col-md-3 text-md-end">
                    <a href="{{ route('admin.reports.export', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-file-earmark-pdf"></i> تصدير PDF
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">عدد الطلبات</h3>
                    <p class="h4 mb-0">{{ $ordersCount }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">إجمالي المبيعات</h3>
                    <p class="h4 mb-0">{{ number_format($totalSales / 100) }} ل.س</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">عمولة المنصة</h3>
                    <p class="h4 mb-0">{{ number_format($totalCommission / 100) }} ل.س</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3 class="h6 text-muted">أرباح البائعين</h3>
                    <p class="h4 mb-0">{{ number_format($totalVendorEarnings / 100) }} ل.س</p>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small">
        سحوبات معتمدة بنفس الفترة: <strong>{{ number_format($totalWithdrawals / 100) }} ل.س</strong>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>رقم الطلب</th><th>العميل</th><th>التاريخ</th><th>الإجمالي</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="fw-bold">{{ $order->order_number }}</td>
                            <td>{{ $order->user->name }}</td>
                            <td>{{ $order->created_at->format('Y-m-d') }}</td>
                            <td>{{ number_format($order->total / 100) }} ل.س</td>
                            <td class="text-end">
                                <a href="{{ route('invoices.show', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary">الفاتورة</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد طلبات ضمن هذه الفترة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
