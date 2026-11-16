@extends('layouts.app')

@section('title', 'عروض اليوم - تريندشام')

@section('content')
<div class="container-fluid px-4">
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - عروض اليوم</span>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة الإدارة</a>
            <a href="{{ route('admin.daily-deals.create') }}" class="btn btn-primary btn-sm">إضافة عرض جديد</a>
        </div>
    </nav>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">عروض اليوم النشطة</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المنتج</th>
                                    <th>النسبة</th>
                                    <th>السعر الخاص</th>
                                    <th>البداية</th>
                                    <th>النهاية</th>
                                    <th>الكمية المباعة</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($deals as $deal)
                                <tr>
                                    <td>{{ $deal->id }}</td>
                                    <td translate="no">{{ $deal->product->name }}</td>
                                    <td>
                                        <span class="badge bg-danger">{{ $deal->discount_percentage }}%</span>
                                    </td>
                                    <td>
                                        @if ($deal->special_price)
                                            {{ number_format($deal->special_price) }} ل.س
                                        @else
                                            <span class="text-muted">حسب النسبة</span>
                                        @endif
                                    </td>
                                    <td>{{ $deal->starts_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $deal->ends_at->format('Y-m-d H:i') }}</td>
                                    <td>{{ $deal->sold_quantity }} / {{ $deal->max_quantity ?: 'غير محدود' }}</td>
                                    <td>
                                        <span class="badge @if($deal->is_active) bg-success @else bg-danger @endif">
                                            @if($deal->is_active) نشط @else متوقف @endif
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.daily-deals.edit', $deal) }}" class="btn btn-outline-primary btn-sm">تعديل</a>
                                        <form action="{{ route('admin.daily-deals.toggle', $deal) }}" method="POST" class="d-inline" data-confirm="هل أنت متأكد؟">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-{{ $deal->is_active ? 'danger' : 'success' }} btn-sm">
                                                @if($deal->is_active) إيقاف @else تفعيل @endif
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.daily-deals.destroy', $deal) }}" method="POST" class="d-inline" data-confirm="هل أنت متأكد من الحذف؟">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">حذف</button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $deals->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection