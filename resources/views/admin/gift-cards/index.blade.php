@extends('layouts.app')

@section('title', 'بطاقات الهدايا - لوحة المدير')

@section('content')
@php($fmt = fn ($minor) => number_format($minor / 100).' ل.س')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - بطاقات الهدايا</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المدير</a>
    </div>
</nav>

<div class="container pb-5">
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">بيعت</div>
            <div class="h4 fw-bold mb-0">{{ $fmt($soldAmount) }}</div>
            <div class="small text-muted">{{ __(':count بطاقة مدفوعة', ['count' => number_format($soldCount)]) }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">صُرفت في طلبات</div>
            <div class="h4 fw-bold mb-0 text-success">{{ $fmt($spent) }}</div>
            <div class="small text-muted">بعد طرح ما أُرجع من طلبات ملغاة</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100 border-warning"><div class="card-body">
            <div class="small text-muted">مستحق على المنصة للعملاء</div>
            <div class="h4 fw-bold mb-0 text-warning">{{ $fmt($owed) }}</div>
            <div class="small text-muted">{{ __('أرصدة :balances + بطاقات لم تُدخل :cards', ['balances' => $fmt($balances), 'cards' => $fmt($unredeemed)]) }}</div>
        </div></div></div>
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body">
            <div class="small text-muted">انتهت دون استخدام</div>
            <div class="h4 fw-bold mb-0 text-muted">{{ $fmt($expiredUnused) }}</div>
            <div class="small text-muted">بطاقات مضت سنة على شرائها</div>
        </div></div></div>
    </div>

    <form method="GET" class="row g-2 mb-3" role="search">
        <div class="col-md-5">
            <label for="q" class="visually-hidden">بحث</label>
            <input id="q" name="q" value="{{ $q }}" class="form-control" placeholder="بحث باسم المشتري أو بريده أو هاتفه">
        </div>
        <div class="col-md-4">
            <label for="status" class="visually-hidden">الحالة</label>
            <select id="status" name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach (['active' => 'جاهزة للإرسال', 'redeemed' => 'استُخدمت', 'pending_payment' => 'بانتظار الدفع', 'failed' => 'لم يكتمل الدفع'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><button class="btn btn-primary w-100">عرض</button></div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المشتري</th>
                        <th>القيمة</th>
                        <th>الحالة</th>
                        <th>استخدمها</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cards as $card)
                        <tr>
                            <td>{{ $card->id }}</td>
                            <td translate="no">{{ $card->purchaser?->first_name }} {{ $card->purchaser?->last_name }}<div class="small text-muted">{{ $card->purchaser?->email }}</div></td>
                            <td class="fw-bold">{{ $fmt($card->amount) }}</td>
                            <td><span class="badge {{ $card->status === 'redeemed' ? 'bg-success' : ($card->status === 'active' ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $card->statusLabel() }}</span></td>
                            <td translate="no">{{ $card->redeemer ? $card->redeemer->first_name.' '.$card->redeemer->last_name : '—' }}</td>
                            <td class="small">{{ ($card->paid_at ?? $card->created_at)?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد بطاقات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $cards->links() }}</div>
    <p class="small text-muted mt-3 mb-0">الأكواد نفسها لا تظهر للمدير: يراها المشتري فقط.</p>
</div>
@endsection
