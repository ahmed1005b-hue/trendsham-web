@extends('layouts.app')

@section('title', 'الكوبونات - لوحة البائع')

@push('styles')
<style>
    .ts-cp-card { border: 1px solid #ececec; border-radius: 12px; background: #fff; padding: 1rem; display: grid; gap: .5rem; }
    .ts-cp-card .v { font-size: 1.15rem; font-weight: 800; color: #0B5F4A; }
    .ts-cp-meta { color: #777; font-size: .82rem; display: flex; flex-wrap: wrap; gap: .25rem .9rem; }
    .ts-cp-bar { height: 6px; background: #f0f0f0; border-radius: 4px; overflow: hidden; }
    .ts-cp-bar > span { display: block; height: 100%; background: #C9962C; }
    .ts-cp-st { font-size: .75rem; border-radius: 20px; padding: .15rem .6rem; font-weight: 700; }
    .ts-cp-st.active { background: #e3f4ec; color: #0B5F4A; }
    .ts-cp-st.paused { background: #f1f1f1; color: #666; }
    .ts-cp-st.pending_review { background: #fff4dc; color: #8a5a00; }
    .ts-cp-st.rejected, .ts-cp-st.suspended { background: #fde8e6; color: #B3261E; }
    .ts-cp-empty { border: 2px dashed #e2d6b5; border-radius: 14px; padding: 2rem 1rem; text-align: center; background: #FBF6EA; }
    html.dark .ts-cp-card { background: var(--tsd-surface); border-color: var(--tsd-border); color: var(--tsd-text); }
    html.dark .ts-cp-card .v { color: #6fd3a8; }
    html.dark .ts-cp-bar { background: var(--tsd-border); }
    html.dark .ts-cp-empty { background: rgba(201, 150, 44, .08); color: var(--tsd-text); }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الكوبونات</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة التحكم</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h5 mb-1">كوبونات متجري</h1>
            <p class="text-muted small mb-0">العميل يحفظ الكوبون من صفحة متجرك، أو يصله إن كان متابعاً. الخصم يُحسب من أرباحك.</p>
        </div>
        <a href="{{ route('seller.coupons.create') }}" class="btn btn-success">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> إضافة كوبون
        </a>
    </div>

    @if ($blockReason)
        <div class="alert alert-warning">{{ $blockReason }}</div>
    @endif

    @if ($campaigns->isEmpty())
        <div class="ts-cp-empty">
            <i class="bi bi-ticket-perforated fs-1 d-block mb-2" style="color:#C9962C" aria-hidden="true"></i>
            <p class="fw-bold mb-1">لا توجد كوبونات بعد</p>
            <p class="text-muted small mb-3">أنشئ كوبون خصم أو شحن مجاني أو هدية، ليجذب عملاء جدداً ويعيد متابعيك للشراء.</p>
            <a href="{{ route('seller.coupons.create') }}" class="btn btn-success btn-sm">إضافة كوبون</a>
        </div>
    @else
        <div class="row g-3">
            @foreach ($campaigns as $c)
                @php
                    $pct = $c->total_limit ? min(100, (int) round($c->issued_count * 100 / $c->total_limit)) : 0;
                @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="ts-cp-card h-100">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-bold">{{ $c->title }}</div>
                                <div class="v">{{ $c->valueLabel() }}</div>
                            </div>
                            <span class="ts-cp-st {{ $c->status }}">{{ $c->statusLabel() }}</span>
                        </div>
                        @if (in_array($c->status, ['rejected', 'suspended'], true) && $c->review_note)
                            <div class="small text-danger">سبب الإدارة: {{ $c->review_note }}</div>
                        @endif
                        <div class="small">{{ __('جُمع :issued من :total · استُخدم :used', ['issued' => number_format($c->issued_count), 'total' => number_format($c->total_limit), 'used' => number_format($c->redeemed_count)]) }}</div>
                        <div class="ts-cp-bar" aria-hidden="true"><span style="width: {{ $pct }}%"></span></div>
                        <div class="ts-cp-meta">
                            <span>{{ $c->audience === 'followers' ? 'لمتابعي المتجر' : ($c->auto_followers ? 'للجمع + المتابعين' : 'للجمع من المتجر') }}</span>
                            @if ($c->min_order_amount > 0)
                                <span>{{ __('حد أدنى :amount ل.س', ['amount' => number_format($c->min_order_amount / 100)]) }}</span>
                            @endif
                            <span>حتى {{ $c->ends_at?->format('Y-m-d') }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            <a href="{{ route('seller.coupons.show', $c) }}" class="btn btn-outline-secondary btn-sm">الإحصائيات</a>
                            @if (in_array($c->status, ['active', 'paused', 'pending_review'], true))
                                <a href="{{ route('seller.coupons.edit', $c) }}" class="btn btn-outline-secondary btn-sm">تعديل</a>
                            @endif
                            @if (in_array($c->status, ['active', 'paused'], true))
                                <form method="POST" action="{{ route('seller.coupons.toggle', $c) }}">
                                    @csrf
                                    <button class="btn btn-outline-{{ $c->status === 'active' ? 'warning' : 'success' }} btn-sm">{{ $c->status === 'active' ? 'إيقاف مؤقت' : 'تشغيل' }}</button>
                                </form>
                            @endif
                            @unless ($c->isLocked())
                                <form method="POST" action="{{ route('seller.coupons.destroy', $c) }}" data-confirm="حذف الكوبون؟">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">حذف</button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $campaigns->links() }}</div>
    @endif
</div>
@endsection
