@extends('layouts.app')

@section('title', $campaign->title.' - كوبونات المتجر')

@push('styles')
<style>
    .ts-cs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .ts-cs-box { border: 1px solid #ececec; border-radius: 10px; padding: .9rem 1rem; background: #fff; }
    .ts-cs-box .n { font-size: 1.4rem; font-weight: 800; color: #0B5F4A; }
    .ts-cs-box .l { font-size: .8rem; color: #777; }
    html.dark .ts-cs-box { background: var(--tsd-surface); border-color: var(--tsd-border); color: var(--tsd-text); }
    html.dark .ts-cs-box .n { color: #6fd3a8; }
</style>
@endpush

@section('content')
@php
    $fmt = fn ($m) => number_format($m / 100).' ل.س';
    $orderStatus = ['pending' => 'قيد المراجعة', 'paid' => 'مدفوع', 'processing' => 'قيد التجهيز', 'shipped' => 'تم الشحن', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي'];
@endphp
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - إحصائيات الكوبون</span>
        <a href="{{ route('seller.coupons.index') }}" class="btn btn-outline-secondary btn-sm">كل الكوبونات</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h5 mb-1">{{ $campaign->title }}</h1>
            <div class="fw-bold" style="color:#C9962C">{{ $campaign->valueLabel() }}</div>
            <div class="small text-muted">{{ $campaign->statusLabel() }} · حتى {{ $campaign->ends_at?->format('Y-m-d') }}</div>
            @if (in_array($campaign->status, ['rejected', 'suspended'], true) && $campaign->review_note)
                <div class="small text-danger mt-1">سبب الإدارة: {{ $campaign->review_note }}</div>
            @endif
        </div>
        <div class="d-flex gap-2">
            @if (in_array($campaign->status, ['active', 'paused', 'pending_review'], true))
                <a href="{{ route('seller.coupons.edit', $campaign) }}" class="btn btn-outline-secondary btn-sm">{{ $campaign->isLocked() ? 'تمديد / زيادة العدد' : 'تعديل' }}</a>
            @endif
            @if (in_array($campaign->status, ['active', 'paused'], true))
                <form method="POST" action="{{ route('seller.coupons.toggle', $campaign) }}">
                    @csrf
                    <button class="btn btn-outline-{{ $campaign->status === 'active' ? 'warning' : 'success' }} btn-sm">{{ $campaign->status === 'active' ? 'إيقاف مؤقت' : 'تشغيل' }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="ts-cs-grid">
        <div class="ts-cs-box"><div class="n">{{ number_format($stats['issued']) }}</div><div class="l">{{ __('جُمع من :total', ['total' => number_format($campaign->total_limit)]) }}</div></div>
        <div class="ts-cs-box"><div class="n">{{ number_format($stats['redeemed']) }}</div><div class="l">استُخدم في طلبات</div></div>
        <div class="ts-cs-box"><div class="n">{{ $fmt($stats['discount_total']) }}</div><div class="l">مجموع الخصم الذي تحمّلته</div></div>
        <div class="ts-cs-box"><div class="n">{{ $fmt($stats['sales_total']) }}</div><div class="l">مبيعاتك في الطلبات التي استخدمته</div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <h2 class="h6 fw-bold">آخر الطلبات</h2>
            @forelse ($recent as $r)
                <div class="d-flex justify-content-between border-bottom py-2 small">
                    <span>{{ $r->order?->order_number }} · {{ $orderStatus[$r->order?->status] ?? $r->order?->status }}{{ $r->status === 'reversed' ? ' ('.__('أُلغي الخصم').')' : '' }}</span>
                    <span>{{ $fmt($r->discount_amount + $r->shipping_discount) }}</span>
                </div>
            @empty
                <p class="text-muted small mb-0">لم يُستخدم في أي طلب بعد.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
