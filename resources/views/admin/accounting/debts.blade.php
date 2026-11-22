@extends('layouts.app')

@section('title', 'ديون البائعين - المحاسبة')

@section('content')
    <nav class="navbar ts-header mb-4">
        <div class="container">
            <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - ديون البائعين</span>
            <a href="{{ route('admin.accounting.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المحاسبة</a>
        </div>
    </nav>

    <div class="container mb-5">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="alert alert-light border small">
            متاجر توصل طلباتها بنفسها وتحصّل المال نقداً من الزبون، فتبقى عليها عمولة المنصة.
            الدين يُخصم تلقائياً من مبيعاتها القادمة، وعندما يسدّد البائع بنفسه (تحويل أو نقداً) سجّل المبلغ هنا.
        </div>

        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted">مجموع الديون</span>
                <strong class="fs-5 text-danger">{{ number_format($total / 100) }} ل.س</strong>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>المتجر</th>
                            <th>الهاتف</th>
                            <th>الدين (ل.س)</th>
                            <th>تسجيل تسديد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($wallets as $wallet)
                            @php($vendor = $wallet->vendor)
                            <tr>
                                <td>
                                    <strong translate="no">{{ $vendor?->store_name }}</strong>
                                    <div class="small text-muted">{{ $vendor?->user?->name }}</div>
                                </td>
                                <td dir="ltr" class="text-end">{{ $vendor?->user?->phone }}</td>
                                <td class="text-danger fw-bold">{{ number_format(-$wallet->balance / 100) }}</td>
                                <td>
                                    @if ($vendor)
                                        <form action="{{ route('admin.accounting.debts.pay', $vendor) }}" method="POST" class="d-flex gap-2 flex-wrap"
                                              data-confirm="تأكد أنك استلمت المبلغ فعلاً قبل تسجيله.">
                                            @csrf
                                            <input type="number" name="amount" class="form-control form-control-sm" style="max-width:9rem" min="1" step="1"
                                                   max="{{ -$wallet->balance / 100 }}" value="{{ -$wallet->balance / 100 }}" required aria-label="المبلغ">
                                            <input type="text" name="note" class="form-control form-control-sm" style="max-width:12rem" maxlength="150" placeholder="ملاحظة (حوالة رقم...)" aria-label="ملاحظة">
                                            <button class="btn btn-sm btn-success">تسجيل</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">لا توجد ديون على أي متجر.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $wallets->links() }}</div>
    </div>
@endsection
