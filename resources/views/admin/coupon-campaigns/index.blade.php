@extends('layouts.app')

@section('title', 'كوبونات المحفظة - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - كوبونات المحفظة</span>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.coupon-campaigns.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> كوبون جديد</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
        @if (session($key))<div class="alert alert-{{ $cls }}">{{ session($key) }}</div>@endif
    @endforeach

    <p class="text-muted small">الكوبونات تصل لمحافظ العملاء مباشرة دون رموز. كوبونات المنصة تتحمّل المنصة خصمها، وكوبونات البائعين يتحمّلها البائع.</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>الكوبون</th><th>الممول</th><th>الجمهور</th><th>الصلاحية</th><th class="text-center">وصل لـ</th><th class="text-center">استُخدم</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $c)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $c->title }}</div>
                                <div class="small text-muted">{{ $c->valueLabel() }}@if ($c->min_order_amount) · {{ __('للطلبات من :amount ل.س', ['amount' => number_format($c->min_order_amount / 100)]) }} @endif</div>
                            </td>
                            <td class="small">{{ $c->funder === 'platform' ? 'المنصة' : 'متجر '.$c->seller?->store_name }}</td>
                            <td class="small">{{ \App\Coupons\Models\CouponCampaign::AUDIENCES[$c->audience] ?? $c->audience }}</td>
                            <td class="small text-nowrap">
                                {{ $c->starts_at?->format('Y-m-d') ?? '—' }} ← {{ $c->ends_at?->format('Y-m-d') ?? 'مفتوح' }}
                                @if ($c->valid_days)<div class="text-muted">{{ __(':n يوماً لكل عميل', ['n' => $c->valid_days]) }}</div>@endif
                            </td>
                            <td class="text-center">{{ number_format($c->issued_count) }}</td>
                            <td class="text-center">{{ number_format($c->redeemed_count) }}@if ($c->total_limit) / {{ number_format($c->total_limit) }}@endif</td>
                            <td class="text-end text-nowrap">
                                @if (! $c->isRunning() && $c->status === 'active')
                                    <span class="badge text-bg-secondary">منتهٍ</span>
                                @endif
                                <form action="{{ route('admin.coupon-campaigns.toggle', $c) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm {{ $c->status === 'active' ? 'btn-outline-warning' : 'btn-success' }}">
                                        {{ $c->status === 'active' ? 'إيقاف' : 'تفعيل' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">لا توجد كوبونات بعد. <a href="{{ route('admin.coupon-campaigns.create') }}">أنشئ أول كوبون</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $campaigns->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
