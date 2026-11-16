@extends('layouts.app')

@section('title', 'كوبونات البائعين - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - كوبونات البائعين</span>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.coupon-campaigns.index') }}" class="btn btn-outline-secondary btn-sm">كوبونات المنصة</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
        @if (session($key))<div class="alert alert-{{ $cls }}">{{ session($key) }}</div>@endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted small">{{ __('البائع يتحمّل خصم كوبونه من أرباحه. الكوبونات التي تزيد نسبتها على :review% تنتظر موافقتك قبل أن تظهر للعملاء.', ['review' => \App\Coupons\Models\CouponCampaign::REVIEW_ABOVE_PERCENT]) }}</p>

    <ul class="nav nav-pills mb-3 flex-wrap gap-1">
        <li class="nav-item"><a class="nav-link {{ empty($filters['status']) ? 'active' : '' }}" href="{{ route('admin.seller-coupons.index', array_filter(['store' => $filters['store'] ?? null])) }}">الكل</a></li>
        @foreach (\App\Coupons\Models\CouponCampaign::STATUSES as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ ($filters['status'] ?? null) === $key ? 'active' : '' }}" href="{{ route('admin.seller-coupons.index', array_filter(['status' => $key, 'store' => $filters['store'] ?? null])) }}">
                    {{ $key === 'pending_review' ? __('بانتظار الموافقة (:n)', ['n' => $pendingCount]) : $label }}
                </a>
            </li>
        @endforeach
    </ul>

    <form method="GET" class="d-flex gap-2 mb-3" style="max-width: 420px">
        @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
        <select name="store" class="form-select form-select-sm" aria-label="المتجر">
            <option value="">كل المتاجر</option>
            @foreach ($stores as $s)
                <option value="{{ $s->id }}" @selected((int) ($filters['store'] ?? 0) === $s->id)>{{ $s->store_name }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary">تصفية</button>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>الكوبون</th><th>المتجر</th><th>الحالة</th><th>الصلاحية</th><th class="text-center">جُمع</th><th class="text-center">استُخدم</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $c)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $c->title }}</div>
                                <div class="small" style="color:#9A7425">{{ $c->valueLabel() }}</div>
                            </td>
                            <td>{{ $c->seller?->store_name }}</td>
                            <td>
                                <span class="badge {{ match ($c->status) { 'active' => 'text-bg-success', 'pending_review' => 'text-bg-warning', 'paused' => 'text-bg-secondary', default => 'text-bg-danger' } }}">{{ $c->statusLabel() }}</span>
                                @if ($c->review_note)<div class="small text-muted mt-1">{{ $c->review_note }}</div>@endif
                            </td>
                            <td class="small">{{ $c->starts_at?->format('Y-m-d') }} ← {{ $c->ends_at?->format('Y-m-d') }}</td>
                            <td class="text-center">{{ number_format($c->issued_count) }} / {{ number_format($c->total_limit) }}</td>
                            <td class="text-center">{{ number_format($c->redeemed_count) }}</td>
                            <td class="text-nowrap">
                                @if ($c->status === 'pending_review')
                                    <form method="POST" action="{{ route('admin.seller-coupons.approve', $c) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-success">موافقة</button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#rej{{ $c->id }}">رفض</button>
                                @endif
                                @if (in_array($c->status, ['active', 'paused', 'pending_review'], true))
                                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#sus{{ $c->id }}">إيقاف</button>
                                @endif
                            </td>
                        </tr>
                        @foreach (['rej' => ['admin.seller-coupons.reject', 'سبب الرفض', 'رفض'], 'sus' => ['admin.seller-coupons.suspend', 'سبب الإيقاف', 'إيقاف نهائي']] as $prefix => [$route, $label, $btn])
                            <tr class="collapse" id="{{ $prefix }}{{ $c->id }}">
                                <td colspan="7">
                                    <form method="POST" action="{{ route($route, $c) }}" class="d-flex gap-2">
                                        @csrf
                                        <input name="reason" class="form-control form-control-sm" maxlength="500" required placeholder="{{ __(':label (يصل للبائع)', ['label' => $label]) }}" aria-label="{{ $label }}">
                                        <button class="btn btn-sm btn-danger text-nowrap">{{ $btn }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">لا توجد كوبونات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $campaigns->links() }}</div>
</div>
@endsection
