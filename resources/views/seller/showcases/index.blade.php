@extends('layouts.app')

@section('title', 'مساحات العرض المؤجّرة - لوحة البائع')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - مساحات العرض</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة التحكم</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">حجوزات مساحات العرض</h2>
        <a href="{{ route('seller.showcases.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i> حجز مساحة جديدة
        </a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>المساحة</th>
                        <th>العنوان</th>
                        <th>الفترة</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>الإحصائيات</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($showcases as $showcase)
                        <tr>
                            <td>
                                <span class="badge bg-info text-dark">{{ $showcase->slot->name }}</span>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $showcase->title }}</div>
                                @if ($showcase->subtitle)
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($showcase->subtitle, 60) }}</div>
                                @endif
                            </td>
                            <td class="small">
                                {{ $showcase->start_date->format('Y-m-d') }} → {{ $showcase->end_date->format('Y-m-d') }}
                                <div>({{ __(':n أيام', ['n' => $showcase->start_date->diffInDays($showcase->end_date) + 1]) }})</div>
                            </td>
                            <td class="fw-bold text-success">{{ number_format($showcase->amount, 2) }} ل.س</td>
                            <td>
                                @switch($showcase->status)
                                    @case('pending_review')
                                        <span class="badge bg-warning text-dark">قيد المراجعة</span>
                                        @break
                                    @case('approved')
                                        <span class="badge bg-success">معتمد</span>
                                        @if ($showcase->start_date->gt(today()))
                                            <span class="badge bg-info text-dark ms-1">مجدول</span>
                                        @elseif ($showcase->end_date->gte(today()))
                                            <span class="badge bg-primary ms-1">نشط حالياً</span>
                                        @endif
                                        @break
                                    @case('rejected')
                                        <span class="badge bg-danger">مرفوض</span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge bg-secondary">ملغي</span>
                                        @break
                                    @case('expired')
                                        <span class="badge bg-dark">منتهي</span>
                                        @break
                                @endswitch
                                @if ($showcase->rejection_reason)
                                    <div class="small text-danger mt-1">{{ \Illuminate\Support\Str::limit($showcase->rejection_reason, 100) }}</div>
                                @endif
                            </td>
                            <td class="small">
                                <div>ظهور: <strong>{{ number_format($showcase->impressions) }}</strong></div>
                                <div>نقرات: <strong>{{ number_format($showcase->clicks) }}</strong></div>
                                @if ($showcase->impressions > 0)
                                    <div class="text-muted">CTR: {{ number_format($showcase->clicks / $showcase->impressions * 100, 2) }}%</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('seller.showcases.show', $showcase) }}" class="btn btn-sm btn-outline-primary mb-1 d-block">
                                    <i class="bi bi-eye me-1"></i> عرض / معاينة
                                </a>
                                @if (in_array($showcase->status, ['pending_review', 'approved']) && $showcase->start_date->gt(today()))
                                    <form action="{{ route('seller.showcases.cancel', $showcase) }}" method="POST" class="d-inline"
                                        data-confirm="هل أنت متأكد من إلغاء هذا الحجز؟ سيتم استرجاع المبلغ كاملاً إلى محفظتك.">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger w-100">
                                            <i class="bi bi-x-circle me-1"></i> إلغاء
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                لا توجد حجوزات بعد.
                                <a href="{{ route('seller.showcases.create') }}" class="btn btn-sm btn-primary ms-2">احجز الآن</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $showcases->links() }}
        </div>
    </div>
</div>
@endsection