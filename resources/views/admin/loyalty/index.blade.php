@extends('layouts.app')

@section('title', 'نقاط الولاء - لوحة المدير')

@section('content')
@php($fmt = fn ($minor) => number_format($minor / 100).' ل.س')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - نقاط الولاء</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container pb-5">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">النقاط القائمة</div>
            <div class="h4 fw-bold mb-0">{{ number_format($outstanding) }}</div>
            <div class="small text-muted">مستحقة على المنصة: <strong>{{ $fmt($outstandingValue) }}</strong></div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">مُنحت هذا الشهر</div>
            <div class="h4 fw-bold mb-0 text-success">{{ number_format($earnedThisMonth) }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">استُبدلت هذا الشهر</div>
            <div class="h4 fw-bold mb-0">{{ number_format($redeemedThisMonth) }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">انتهت هذا الشهر</div>
            <div class="h4 fw-bold mb-0 text-secondary">{{ number_format($expiredThisMonth) }}</div>
        </div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h6 mb-0">أرصدة العملاء</h2>
                        <form method="GET" class="d-flex gap-2">
                            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="اسم، بريد، أو هاتف" aria-label="بحث عن عميل">
                            <button class="btn btn-sm btn-outline-secondary">بحث</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>العميل</th><th>الرصيد</th><th>دين</th><th>كسب إجمالاً</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($customers as $acct)
                                    <tr>
                                        <td>{{ trim(($acct->user->first_name ?? '').' '.($acct->user->last_name ?? '')) ?: '—' }}
                                            <div class="small text-muted">{{ $acct->user->email ?? $acct->user->phone }}</div></td>
                                        <td class="fw-bold">{{ number_format($acct->balance) }}</td>
                                        <td>{{ $acct->debt ?: '—' }}</td>
                                        <td>{{ number_format($acct->lifetime_earned) }}</td>
                                        <td><a href="{{ route('admin.loyalty.show', $acct->user_id) }}" class="btn btn-sm btn-outline-primary">السجل</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد عملاء بنقاط بعد.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $customers->links() }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">إعدادات البرنامج</h2>
                    <form method="POST" action="{{ route('admin.loyalty.settings') }}">
                        @csrf
                        @method('PUT')
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="loyalty_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="lEnabled" name="loyalty_enabled" value="1" @checked($settings->enabled())>
                            <label class="form-check-label" for="lEnabled">البرنامج مفعّل</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small" for="lSpend">ل.س لكل نقطة</label>
                                <input class="form-control" type="number" min="1" id="lSpend" name="loyalty_spend_per_point" value="{{ $settings->spendPerPoint() / 100 }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small" for="lValue">قيمة النقطة (ل.س)</label>
                                <input class="form-control" type="number" min="1" id="lValue" name="loyalty_point_value" value="{{ $settings->pointValue() / 100 }}" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small" for="lMin">أقل استبدال</label>
                                <input class="form-control" type="number" min="1" id="lMin" name="loyalty_min_redeem" value="{{ $settings->minRedeem() }}" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small" for="lExp">صلاحية (شهر)</label>
                                <input class="form-control" type="number" min="1" id="lExp" name="loyalty_expiry_months" value="{{ $settings->expiryMonths() }}" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small" for="lCoupon">الكوبون (يوم)</label>
                                <input class="form-control" type="number" min="1" id="lCoupon" name="loyalty_coupon_valid_days" value="{{ $settings->couponValidDays() }}" required>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-3">
                            {{ __('حالياً: العميل يسترجع :rate% من مشترياته. خصم النقاط تتحمّله المنصة ولا يمس أرباح البائعين.', ['rate' => rtrim(rtrim(number_format($settings->pointValue() / $settings->spendPerPoint() * 100, 2), '0'), '.')]) }}
                        </p>
                        <button class="btn btn-primary w-100">حفظ الإعدادات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
