@extends('layouts.app')

@section('title', 'عروض اليوم - تريندشام')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - عروض اليوم</span>
            <div>
                <a href="{{ route('seller.daily-deals.create') }}" class="btn btn-sm"
                    style="background:var(--ts-gold); color:var(--ts-emerald-dark); font-weight:700;">+ عرض جديد</a>
                <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-white align-middle">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>الخصم</th>
                        <th>السعر بعد الخصم</th>
                        <th>يبادل من</th>
                        <th>حتى</th>
                        <th>الكمية</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deals as $deal)
                        <tr>
                            <td>
                                @if ($deal->product)
                                    <a href="{{ route('products.show', $deal->product) }}"
                                        class="fw-bold text-decoration-none">{{ $deal->product->name }}</a>
                                @else
                                    <span class="text-muted">منتج محذوف</span>
                                @endif
                            </td>
                            <td>{{ $deal->discount_percentage }}%</td>
                            <td class="fw-bold">{{ number_format($deal->discounted_price, 2) }} ل.س</td>
                            <td>{{ $deal->starts_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $deal->ends_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($deal->max_quantity > 0)
                                    {{ $deal->sold_quantity }} / {{ $deal->max_quantity }}
                                @else
                                    غير محدودة
                                @endif
                            </td>
                            <td>
                                @if ($deal->is_active && !$deal->is_expired)
                                    <span class="badge bg-success">نشط</span>
                                @elseif ($deal->is_expired)
                                    <span class="badge bg-secondary">منتهي</span>
                                @else
                                    <span class="badge bg-warning text-dark">موقوف</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('seller.daily-deals.edit', $deal) }}"
                                        class="btn btn-sm btn-outline-primary">تعديل</a>
                                    <form action="{{ route('seller.daily-deals.toggle', $deal) }}" method="POST">
                                        @csrf
                                        <button
                                            class="btn btn-sm btn-outline-warning">{{ $deal->is_active ? 'إيقاف' : 'تشغيل' }}</button>
                                    </form>
                                    <form action="{{ route('seller.daily-deals.destroy', $deal) }}" method="POST"
                                        data-confirm="حذف هذا العرض؟">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">ما عندك أي عروض يومية بعد. ابدأ بإضافة عرض جديد.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center">{{ $deals->links() }}</div>
    </div>
@endsection