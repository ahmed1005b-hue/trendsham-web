@extends('layouts.app')

@section('title', 'العروض المؤجّرة - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - العروض المؤجّرة</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h2 class="h5 mb-0">جميع العروض المؤجّرة</h2>
        <span class="badge bg-warning text-dark">{{ $showcases->where('status', 'pending_review')->count() }} قيد المراجعة</span>
    </div>

    <form method="GET" action="{{ route('admin.showcases.index') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label for="f-q" class="form-label small mb-1">بحث بالعنوان</label>
                <input id="f-q" type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="عنوان العرض...">
            </div>
            <div class="col-md-2">
                <label for="f-status" class="form-label small mb-1">الحالة</label>
                <select id="f-status" name="status" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    <option value="pending_review" @selected(request('status') === 'pending_review')>قيد المراجعة</option>
                    <option value="approved" @selected(request('status') === 'approved')>معتمد</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>مرفوض</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>ملغي</option>
                    <option value="expired" @selected(request('status') === 'expired')>منتهي</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="f-slot" class="form-label small mb-1">المساحة</label>
                <select id="f-slot" name="slot" class="form-select form-select-sm">
                    <option value="">الكل</option>
                    @foreach (\App\Models\ShowcaseSlot::orderBy('position')->get() as $slot)
                        <option value="{{ $slot->id }}" @selected(request('slot') == $slot->id)>{{ $slot->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-sm btn-primary">تصفية</button>
                <a href="{{ route('admin.showcases.index') }}" class="btn btn-sm btn-outline-secondary">مسح</a>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>المتجر</th>
                        <th>المساحة</th>
                        <th>العنوان</th>
                        <th>الفترة</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>ظهور/نقرات</th>
                        <th style="min-width:280px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($showcases as $showcase)
                        <tr>
                            <td>
                                <div class="fw-bold" translate="no">{{ $showcase->seller->store_name }}</div>
                                <div class="small text-muted">{{ $showcase->seller->user->name }}</div>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark">{{ $showcase->slot->name }}</span>
                            </td>
                            <td>
                                <div class="fw-bold">{{ $showcase->title }}</div>
                                @if ($showcase->subtitle)
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($showcase->subtitle, 50) }}</div>
                                @endif
                            </td>
                            <td class="small">
                                {{ $showcase->start_date->format('Y-m-d') }} → {{ $showcase->end_date->format('Y-m-d') }}
                                <div>({{ $showcase->start_date->diffInDays($showcase->end_date) + 1 }} أيام)</div>
                            </td>
                            <td class="fw-bold text-success">{{ number_format($showcase->amount, 2) }} ل.س</td>
                            <td>
                                @switch($showcase->status)
                                    @case('pending_review')
                                        <span class="badge bg-warning text-dark">قيد المراجعة</span>
                                        @break
                                    @case('approved')
                                        <span class="badge bg-success">معتمد</span>
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
                                    <div class="small text-danger mt-1">{{ \Illuminate\Support\Str::limit($showcase->rejection_reason, 80) }}</div>
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
                                <button type="button" class="btn btn-sm btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#previewModal{{ $showcase->id }}">
                                    <i class="bi bi-eye"></i> معاينة
                                </button>

                                @if ($showcase->status === 'pending_review')
                                    <form action="{{ route('admin.showcases.approve', $showcase) }}" method="POST" class="d-inline mb-1" data-confirm="الموافقة على هذا العرض؟">
                                        @csrf
                                        <button class="btn btn-sm btn-success w-100">موافقة</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 mb-1" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $showcase->id }}">رفض</button>
                                @elseif ($showcase->status === 'approved')
                                    <form action="{{ route('admin.showcases.remove', $showcase) }}" method="POST" class="d-inline mb-1" data-confirm="إزالة هذا العرض الجاري؟ سيتم استرجاع مبلغ الأيام المتبقية.">
                                        @csrf
                                        <input type="text" name="reason" class="form-control form-control-sm mb-1" placeholder="سبب الإزالة" required maxlength="200">
                                        <button class="btn btn-sm btn-outline-danger w-100">إزالة</button>
                                    </form>
                                @endif
                            </td>
                        </tr>

                        {{-- Modal المعاينة --}}
                        <div class="modal fade" id="previewModal{{ $showcase->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">معاينة العرض: {{ $showcase->title }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-0">
                                        @php
                                            $presenter = \App\Showcases\ShowcasePresenter::card($showcase);
                                            $grad = $presenter['gradient'];
                                            $textColor = $presenter['text_hex'];
                                            $direction = $grad['direction'];
                                            $begin = match($direction) {
                                                'vertical' => 'top center',
                                                'horizontal' => 'right center',
                                                default => 'top right',
                                            };
                                            $end = match($direction) {
                                                'vertical' => 'bottom center',
                                                'horizontal' => 'left center',
                                                default => 'bottom left',
                                            };
                                        @endphp
                                        <div style="background: {!! $presenter['background_url'] ? "url('".e($presenter['background_url'])."') center/cover, " : '' !!}linear-gradient({{ ['vertical' => '180deg', 'horizontal' => '270deg'][$direction] ?? '135deg' }}, {{ implode(', ', $grad['colors']) }}); border-radius: 20px; padding: 20px; color: {{ $textColor }}; direction: rtl;">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-3">
                                                    @if ($presenter['store']['logo_url'])
                                                        <img src="{{ $presenter['store']['logo_url'] }}" alt="" width="50" height="50" class="rounded-circle">
                                                    @else
                                                        <div class="d-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-20" style="width:50px;height:50px;">
                                                            <i class="bi bi-shop fs-3"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="mb-1">{{ $presenter['title'] }}</h5>
                                                    @if ($presenter['subtitle'])
                                                        <small>{{ $presenter['subtitle'] }}</small>
                                                    @endif
                                                </div>
                                                <span class="badge bg-white bg-opacity-20 px-3 py-1">{{ $presenter['sponsored_label'] }}</span>
                                            </div>
                                            <div class="d-flex gap-2 overflow-auto pb-2" style="scroll-snap-type: x mandatory;">
                                                @foreach ($presenter['products'] as $product)
                                                    <div class="flex-shrink-0" style="width: 140px; scroll-snap-align: start;">
                                                        <div class="bg-white bg-opacity-10 rounded p-2 text-center">
                                                            @if ($product['image_url'])
                                                                <img src="{{ $product['image_url'] }}" alt="" style="width:100%;height:100px;object-fit:cover;border-radius:8px;">
                                                            @else
                                                                <div style="height:100px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;">
                                                                    <i class="bi bi-image fs-2"></i>
                                                                </div>
                                                            @endif
                                                            <div class="mt-2 text-start">
                                                                <small class="d-block text-truncate">{{ $product['name'] }}</small>
                                                                <span class="fw-bold">{{ number_format($product['price'], 0) }} ل.س</span>
                                                                @if ($product['old_price'])
                                                                    <del class="small text-white-50">{{ number_format($product['old_price'], 0) }} ل.س</del>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Modal الرفض --}}
                        <div class="modal fade" id="rejectModal{{ $showcase->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">رفض العرض</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="{{ route('admin.showcases.reject', $showcase) }}" method="POST">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">سبب الرفض (سيراه البائع)</label>
                                                <textarea name="reason" class="form-control" rows="3" required maxlength="200" placeholder="اكتب سبب الرفض هنا..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                            <button type="submit" class="btn btn-danger">تأكيد الرفض واسترجاع المبلغ</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">لا توجد عروض.</td></tr>
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