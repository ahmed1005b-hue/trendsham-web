@extends('layouts.app')

@section('title', 'مراجعة المنتجات - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ $employeeMode ? 'إدارة المنتجات' : 'مراجعة المنتجات' }}</span>
        <a href="{{ route($employeeMode ? 'employee.dashboard' : 'admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة التحكم</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="btn-group mb-3">
        <a href="{{ route($employeeMode ? 'employee.products.index' : 'admin.products.index', ['filter' => 'pending']) }}" class="btn btn-sm {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">
            بانتظار المراجعة ({{ $pendingCount }})
        </a>
        <a href="{{ route($employeeMode ? 'employee.products.index' : 'admin.products.index', ['filter' => 'live']) }}" class="btn btn-sm {{ $filter === 'live' ? 'btn-primary' : 'btn-outline-primary' }}">معتمدة وظاهرة</a>
        <a href="{{ route($employeeMode ? 'employee.products.index' : 'admin.products.index', ['filter' => 'draft']) }}" class="btn btn-sm {{ $filter === 'draft' ? 'btn-primary' : 'btn-outline-primary' }}">مسودات</a>
        <a href="{{ route($employeeMode ? 'employee.products.index' : 'admin.products.index', ['filter' => 'all']) }}" class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' }}">الكل</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>الصورة</th><th>المنتج</th><th>البائع</th><th>القسم</th><th>السعر</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td style="width:60px;">
                                @if ($product->primaryImage)
                                    <img src="{{ $product->primaryImage->thumb_url }}" alt="{{ $product->name }}" loading="lazy" width="48" height="48" class="rounded" style="object-fit:cover;">
                                @else
                                    <div class="bg-light rounded" style="width:48px;height:48px;"></div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('products.show', $product) }}" target="_blank" translate="no">{{ $product->name }}</a>
                            </td>
                            <td translate="no">{{ $product->vendor->store_name }}</td>
                            <td translate="no">{{ $product->category->name }}</td>
                            <td>{{ number_format($product->price / 100) }} ل.س</td>
                            <td class="text-end">
                                @if ($employeeMode)
                                    <a href="{{ route('employee.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">تعديل</a>
                                    @if ($product->status !== 'inactive')
                                        <form action="{{ route('employee.products.archive', $product) }}" method="POST" class="d-inline" data-confirm="إخفاء المنتج من المتجر مع الاحتفاظ بسجله؟">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">إخفاء</button>
                                        </form>
                                    @endif
                                @endif
                                @if ($product->status === 'active' && ! $product->admin_approved_at)
                                    <form action="{{ route($employeeMode ? 'employee.products.approve' : 'admin.products.approve', $product) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success">موافقة</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-{{ $product->id }}">رفض</button>
                                    <div class="collapse mt-2" id="reject-{{ $product->id }}">
                                        <form action="{{ route($employeeMode ? 'employee.products.reject' : 'admin.products.reject', $product) }}" method="POST" class="d-flex gap-2">
                                            @csrf
                                            <input type="text" name="rejection_note" class="form-control form-control-sm" placeholder="سبب الرفض" required>
                                            <button class="btn btn-sm btn-danger">تأكيد</button>
                                        </form>
                                    </div>
                                @elseif ($product->isLive())
                                    <span class="badge bg-success">معتمد</span>
                                @else
                                    <span class="text-muted small">مسودة</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لا يوجد منتجات بهذا التصنيف.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
</div>
@endsection
