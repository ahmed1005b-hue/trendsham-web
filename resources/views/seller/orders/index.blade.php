@extends('layouts.app')

@section('title', 'الطلبات - تريندشام')

@section('styles')
<style>
    .ts-stat-card {
        background: #fff;
        border: 1px solid #ECE4D3;
        border-radius: 14px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .ts-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        font-size: 1.35rem;
    }
    .ts-stat-icon.bg-primary { background: #0B5F4A15; color: #0B5F4A; }
    .ts-stat-icon.bg-warning { background: #C9962C15; color: #C9962C; }
    .ts-stat-icon.bg-info { background: #0B5F4A15; color: #0B5F4A; }
    .ts-stat-icon.bg-success { background: #C9962C15; color: #C9962C; }
    .ts-stat-info h5 { margin: 0; font-size: 1.5rem; font-weight: 800; color: #1F2A24; font-family: 'Reem Kufi', sans-serif; }
    .ts-stat-info p { margin: .1rem 0 0; font-size: .85rem; color: #6B7A72; font-family: 'Tajawal', sans-serif; }
    .ts-status-badge {
        display: inline-block;
        padding: .35rem .8rem;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
    }
    .ts-status-pending { background: #FFF3CD; color: #856404; }
    .ts-status-paid { background: #CCE5FF; color: #004085; }
    .ts-status-processing { background: #E2E3E5; color: #383d41; }
    .ts-status-shipped { background: #D1ECF1; color: #0c5460; }
    .ts-status-delivered { background: #D4EDDA; color: #155724; }
    .ts-status-cancelled { background: #F8D7DA; color: #721C24; }
    .ts-order-row {
        background: #fff;
        border: 1px solid #ECE4D3;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: .75rem;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .ts-order-row:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(11,95,74,.08);
    }
    .ts-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: #6B7A72;
    }
    .ts-empty i { font-size: 3rem; margin-bottom: 1rem; opacity: .5; display: block; }
    .section-title {
        display: flex;
        align-items: center;
        gap: .6rem;
        font-family: 'Reem Kufi', sans-serif;
        margin-bottom: 1.25rem;
    }
    .section-title h2 { margin: 0; font-size: 1.35rem; font-weight: 800; color: #1F2A24; }
    .section-title .line { flex: 1; height: 1px; background: #ECE4D3; }
</style>
@endsection

@section('content')
<div class="container-fluid px-4">

    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الطلبات</span>
            <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
        </div>
    </nav>

    <!-- بطاقات الإحصائيات -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="ts-stat-card">
                <div class="ts-stat-icon bg-primary"><i class="bi bi-cart"></i></div>
                <div class="ts-stat-info">
                    <h5>{{ $stats['total'] }}</h5>
                    <p>طلب إجمالي</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="ts-stat-card">
                <div class="ts-stat-icon bg-warning"><i class="bi bi-clock"></i></div>
                <div class="ts-stat-info">
                    <h5>{{ $stats['pending'] }}</h5>
                    <p>في الانتظار</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="ts-stat-card">
                <div class="ts-stat-icon bg-info"><i class="bi bi-box"></i></div>
                <div class="ts-stat-info">
                    <h5>{{ $stats['processing'] }}</h5>
                    <p>قيد التجهيز</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="ts-stat-card">
                <div class="ts-stat-icon bg-success"><i class="bi bi-check-circle"></i></div>
                <div class="ts-stat-info">
                    <h5>{{ $stats['delivered'] }}</h5>
                    <p>تم التسليم</p>
                </div>
            </div>
        </div>
    </div>

    <!-- قائمة الطلبات -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="section-title">
                <h2>طلباتك</h2>
                <span class="line"></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            @if ($orderItems->isEmpty())
                <div class="ts-empty">
                    <i class="bi bi-inbox"></i>
                    <h4>لا يوجد طلبات على منتجاتك بعد</h4>
                    <p class="mb-0">عندما يطلب عميل منتجاً من متجرك، سيظهر هنا.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 15%;">رقم الطلب</th>
                                <th style="width: 25%;">المنتج</th>
                                <th style="width: 10%;">الكمية</th>
                                <th style="width: 15%;">الإجمالي</th>
                                <th style="width: 15%;">التاريخ</th>
                                <th style="width: 15%;">الحالة</th>
                                <th style="width: 15%;">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orderItems as $item)
                                <tr class="ts-order-row">
                                    <td class="fw-bold">#{{ $item->order->order_number }}</td>
                                    <td translate="no">{{ $item->product_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ $item->formatted_subtotal }}</td>
                                    <td>{{ $item->created_at->format('d M Y') }}</td>
                                    <td>
                                        <span class="ts-status-badge ts-status-{{ $item->order->status }}">
                                            {{ $item->order->statusLabel() }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('seller.orders.show', $item->order) }}" class="btn btn-outline-secondary btn-sm px-3">عرض التفاصيل</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    {{ $orderItems->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
