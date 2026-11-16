@extends('layouts.app')

@section('title', 'عرض مساحة مؤجّرة - لوحة البائع')

@push('styles')
<style>
    .preview-card { border: 1px solid #ECE4D3; border-radius: 20px; overflow: hidden; }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - تفاصيل الحجز</span>
        <a href="{{ route('seller.showcases.index') }}" class="btn btn-outline-secondary btn-sm">عودة للقائمة</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        {{-- بطاقة المعاينة --}}
        <div class="col-lg-8">
            <div class="card preview-card">
                @php
                    $grad = $preview['gradient'];
                    $textColor = $preview['text_hex'];
                    $direction = $grad['direction'];
                    $begin = match($direction) {
                        'vertical' => 'to bottom',
                        'horizontal' => 'to left',
                        default => '45deg',
                    };
                @endphp
                <div style="background: {!! $preview['background_url'] ? "url('".e($preview['background_url'])."') center/cover, " : '' !!}linear-gradient({{ $begin }}, {{ implode(', ', $grad['colors']) }}); padding: 24px; color: {{ $textColor }};">
                    <div class="d-flex align-items-center mb-4">
                        <div class="me-3">
                            @if ($preview['store']['logo_url'])
                                <img src="{{ $preview['store']['logo_url'] }}" alt="" width="60" height="60" class="rounded-circle">
                            @else
                                <div class="d-flex align-items-center justify-content-center rounded-circle" style="width:60px;height:60px;background:rgba(255,255,255,0.2);">
                                    <i class="bi bi-shop fs-2"></i>
                                </div>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <h3 class="mb-1">{{ $preview['title'] }}</h3>
                            @if ($preview['subtitle'])
                                <p class="mb-0 opacity-75">{{ $preview['subtitle'] }}</p>
                            @endif
                        </div>
                        <span class="badge px-3 py-2" style="background:rgba(255,255,255,0.2); font-size:0.85rem;">{{ $preview['sponsored_label'] }}</span>
                    </div>
                    
                    <div class="d-flex gap-3 overflow-auto pb-2" style="scroll-snap-type: x mandatory;">
                        @foreach ($preview['products'] as $product)
                            <div class="flex-shrink-0" style="width: 150px; scroll-snap-align: start;">
                                <div class="bg-white bg-opacity-10 rounded p-3 text-center" style="min-height: 220px; display:flex; flex-direction:column;">
                                    @if ($product['image_url'])
                                        <img src="{{ $product['image_url'] }}" alt="" style="width:100%;height:120px;object-fit:cover;border-radius:10px;">
                                    @else
                                        <div style="height:120px;border-radius:10px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;">
                                            <i class="bi bi-image fs-1"></i>
                                        </div>
                                    @endif
                                    <div class="mt-3 text-start flex-grow-1">
                                        <h6 class="mb-1 text-truncate">{{ $product['name'] }}</h6>
                                        <div class="fw-bold fs-6">{{ number_format($product['price'], 0) }} ل.س</div>
                                        @if ($product['old_price'])
                                            <div class="text-decoration-line-through small opacity-75">{{ number_format($product['old_price'], 0) }} ل.س</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- معلومات الحجز --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>معلومات الحجز</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th>المساحة</th><td>{{ $showcase->slot->name }}</td></tr>
                            <tr><th>الموضع</th><td>{{ $showcase->slot->key }}</td></tr>
                            <tr><th>العنوان</th><td>{{ $showcase->title }}</td></tr>
                            @if ($showcase->subtitle)
                                <tr><th>النص الفرعي</th><td>{{ $showcase->subtitle }}</td></tr>
                            @endif
                            <tr><th>الفترة</th><td>{{ $showcase->start_date->format('Y-m-d') }} → {{ $showcase->end_date->format('Y-m-d') }}</td></tr>
                            <tr><th>المدة</th><td>{{ $showcase->start_date->diffInDays($showcase->end_date) + 1 }} أيام</td></tr>
                            <tr><th>المبلغ</th><td class="fw-bold text-success">{{ number_format($showcase->amount, 2) }} ل.س</td></tr>
                            <tr><th>الحالة</th><td>
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
                                @endswitch>
                            </td></tr>
                            @if ($showcase->rejection_reason)
                                <tr><th>سبب الرفض</th><td class="text-danger">{{ $showcase->rejection_reason }}</td></tr>
                            @endif
                            @if ($showcase->reviewed_at)
                                <tr><th>تاريخ المراجعة</th><td>{{ $showcase->reviewed_at->format('Y-m-d H:i') }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- الإحصائيات --}}
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>الإحصائيات</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="display-6 text-primary">{{ number_format($showcase->impressions) }}</div>
                            <div class="text-muted small">ظهور</div>
                        </div>
                        <div class="col-6">
                            <div class="display-6 text-success">{{ number_format($showcase->clicks) }}</div>
                            <div class="text-muted small">نقرات</div>
                        </div>
                        <div class="col-12">
                            <div class="display-6 text-info">{{ number_format($ctr, 2) }}%</div>
                            <div class="text-muted small">معدل النقر (CTR)</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- الإجراءات --}}
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>إجراءات</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    @if (in_array($showcase->status, ['pending_review', 'approved']) && $showcase->start_date->gt(today()))
                        <form action="{{ route('seller.showcases.cancel', $showcase) }}" method="POST" 
                            data-confirm="هل أنت متأكد من إلغاء هذا الحجز؟ سيتم استرجاع المبلغ كاملاً إلى محفظتك.">
                            @csrf
                            <button class="btn btn-outline-danger">
                                <i class="bi bi-x-circle me-2"></i> إلغاء الحجز واسترجاع المبلغ
                            </button>
                        </form>
                    @elseif ($showcase->status === 'approved' && $showcase->start_date->lte(today()) && $showcase->end_date->gte(today()))
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i>العرض نشط حالياً على الشاشة الرئيسية
                        </div>
                    @elseif ($showcase->status === 'rejected')
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-x-circle me-2"></i>تم رفض العرض. تم استرجاع المبلغ إلى محفظتك.
                        </div>
                    @elseif ($showcase->status === 'expired')
                        <div class="alert alert-secondary mb-0">
                            <i class="bi bi-clock me-2"></i>انتهت فترة العرض تلقائياً.
                        </div>
                    @endif
                    
                    <a href="{{ route('seller.showcases.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-right me-2"></i> العودة للقائمة
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection