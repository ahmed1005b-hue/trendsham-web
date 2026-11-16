@extends('layouts.app')

@section('title', 'منتجاتي - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - منتجاتي</span>
        <div>
            <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
            <a href="{{ route('seller.products.create') }}" class="btn btn-primary btn-sm">+ منتج جديد</a>
        </div>
    </div>
</nav>

<div class="container">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>الاسم</th>
                        <th>السعر</th>
                        <th>المخزون</th>
                        <th>الحالة</th>
                        <th>اعتماد الإدارة</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td style="width: 60px;">
                                @if ($product->primaryImage)
                                    <img src="{{ $product->primaryImage->thumb_url }}" alt="{{ $product->name }}" loading="lazy" width="48" height="48" class="rounded" style="object-fit: cover;">
                                @else
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:48px;height:48px;">-</div>
                                @endif
                            </td>
                            <td translate="no">{{ $product->name }}</td>
                            <td>{{ number_format($product->price / 100) }} ل.س</td>
                            <td>
                                {{ $product->stock_quantity }}
                                @if ($product->isLowStock())
                                    <span class="badge bg-warning text-dark">منخفض</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $product->status === 'active' ? 'success' : ($product->status === 'draft' ? 'secondary' : 'danger') }}">
                                    @if($product->status === 'active') نشط
                                    @elseif($product->status === 'draft') مسودة
                                    @else غير نشط
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if ($product->isLive())
                                    <span class="badge bg-success">معتمد وظاهر</span>
                                @elseif ($product->status === 'active')
                                    <span class="badge bg-warning text-dark">بانتظار المراجعة</span>
                                @elseif ($product->rejection_note)
                                    <span class="badge bg-danger" title="{{ $product->rejection_note }}">مرفوض - {{ $product->rejection_note }}</span>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('seller.products.edit', $product) }}" class="btn btn-sm btn-outline-secondary">تعديل</a>
                                <form action="{{ route('seller.products.destroy', $product) }}" method="POST" class="d-inline" data-confirm="تأكيد حذف المنتج؟">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">لا يوجد منتجات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
</div>
@endsection
