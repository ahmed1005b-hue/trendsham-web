@extends('layouts.app')

@section('title', 'محفظتي - تريندشام')

@push('styles')
<style>
    .ts-wallet-stat { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 14px; padding: 1.1rem 1.2rem; height: 100%; }
    .ts-wallet-stat .l { font-size: .85rem; color: var(--bs-secondary-color); }
    .ts-wallet-stat .v { font-size: 1.6rem; font-weight: 800; }
    .ts-wallet-stat.main { background: linear-gradient(135deg, #073D30, #0B5F4A); color: #fff; border: 0; }
    .ts-wallet-stat.main .l { color: #cfe3db; }
    .ts-wd-status { font-size: .75rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; white-space: nowrap; }
    .ts-wd-status.pending { background: #fff3e0; color: #b25e00; }
    .ts-wd-status.approved, .ts-wd-status.processing { background: #e8f0fe; color: #1a56db; }
    .ts-wd-status.paid { background: #e6f6ec; color: #18794e; }
    .ts-wd-status.failed, .ts-wd-status.rejected { background: #fdecec; color: #b42318; }
    html.dark .ts-wd-status.pending { background: rgba(255,153,0,.15); color: #ffb454; }
    html.dark .ts-wd-status.approved, html.dark .ts-wd-status.processing { background: rgba(26,86,219,.2); color: #8fb3ff; }
    html.dark .ts-wd-status.paid { background: rgba(24,121,78,.2); color: #6fe0a6; }
    html.dark .ts-wd-status.failed, html.dark .ts-wd-status.rejected { background: rgba(180,35,24,.2); color: #ff9b91; }
    .ts-steps { display: flex; gap: .5rem; font-size: .8rem; color: var(--bs-secondary-color); flex-wrap: wrap; }
    .ts-steps span { display: inline-flex; align-items: center; gap: .3rem; }
    .ts-steps b { width: 20px; height: 20px; border-radius: 50%; background: var(--bs-secondary-bg); display: inline-grid; place-items: center; font-size: .7rem; }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - محفظتي</span>
        <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-light btn-sm">لوحة المتجر</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="ts-wallet-stat main">
                <div class="l">الرصيد الكلي</div>
                <div class="v">{{ $wallet->formatted_balance }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ts-wallet-stat">
                <div class="l">المتاح للسحب الآن</div>
                <div class="v text-success">{{ number_format($available / 100) }} ل.س</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ts-wallet-stat">
                <div class="l">طلبات سحب معلّقة</div>
                <div class="v">{{ number_format($pendingWithdrawals / 100) }} ل.س</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="ts-wallet-stat">
                <h2 class="h6 mb-3">طلب سحب أرباح</h2>
                @if (! $defaultMethod)
                    <div class="alert alert-warning py-2 small">
                        أضف حساباً بنكياً أو محفظة لاستلام أرباحك أولاً.
                        <a href="{{ route('seller.wallet.payment-methods') }}" class="alert-link">إضافة طريقة استلام</a>
                    </div>
                @else
                    <div class="d-flex align-items-center justify-content-between border rounded-3 p-2 mb-3 small">
                        <div>
                            <div class="text-muted">سيُحوَّل إلى</div>
                            <div class="fw-bold">{{ $defaultMethod->provider }} <span dir="ltr">{{ $defaultMethod->masked_number }}</span></div>
                        </div>
                        <a href="{{ route('seller.wallet.payment-methods') }}" class="btn btn-sm btn-outline-secondary">تغيير</a>
                    </div>
                @endif
                <form action="{{ route('seller.wallet.withdraw') }}" method="POST">
                    @csrf
                    <div class="input-group mb-2">
                        <input type="number" step="1" min="{{ $minAmount }}" max="{{ (int) ($available / 100) }}" name="amount" class="form-control" placeholder="المبلغ بالليرة" required @disabled(! $defaultMethod || $available <= 0)>
                        <button type="button" class="btn btn-outline-secondary" onclick="this.form.amount.value={{ (int) ($available / 100) }}" @disabled(! $defaultMethod || $available <= 0)>الكل</button>
                    </div>
                    <input type="text" name="note" class="form-control form-control-sm mb-2" placeholder="ملاحظة للإدارة (اختياري)" maxlength="255">
                    <button class="btn btn-success w-100" @disabled(! $defaultMethod || $available <= 0)>إرسال طلب السحب</button>
                    <div class="form-text">أقل مبلغ للسحب {{ number_format($minAmount) }} ل.س.</div>
                </form>
                <hr>
                <div class="ts-steps">
                    <span><b>1</b> تطلب السحب</span>
                    <span><b>2</b> توافق الإدارة</span>
                    <span><b>3</b> تُحوَّل الأرباح لحسابك برقم حوالة</span>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="ts-wallet-stat">
                <h2 class="h6 mb-3">طلبات السحب</h2>
                @forelse ($withdrawals as $wd)
                    <div class="d-flex align-items-start justify-content-between gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                        <div class="small">
                            <div class="fw-bold fs-6">{{ $wd->formatted_amount }}</div>
                            <div class="text-muted">{{ $wd->created_at->format('Y-m-d') }} · {{ $wd->payout_provider }} <span dir="ltr">{{ $wd->masked_account }}</span></div>
                            @if ($wd->status === 'paid')
                                <div class="mt-1">رقم الحوالة: <code dir="ltr">{{ $wd->transfer_reference }}</code>
                                    @if ($wd->receipt_path)· <a href="{{ route('withdrawals.receipt', $wd) }}" target="_blank">الإيصال</a>@endif
                                </div>
                            @elseif ($wd->status === 'rejected' && $wd->admin_note)
                                <div class="text-danger mt-1">السبب: {{ $wd->admin_note }}</div>
                            @elseif ($wd->status === 'failed' && $wd->failure_reason)
                                <div class="text-danger mt-1">{{ $wd->failure_reason }}</div>
                            @endif
                        </div>
                        <span class="ts-wd-status {{ $wd->status }}">{{ $wd->status_label }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">لم تطلب أي سحب بعد.</p>
                @endforelse
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">سجل الحركات</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>النوع</th><th>السبب</th><th>المبلغ</th><th>الرصيد بعد الحركة</th><th>التاريخ</th></tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td>
                                @if ($tx->type === 'credit')
                                    <span class="badge bg-success">إيداع</span>
                                @else
                                    <span class="badge bg-danger">خصم</span>
                                @endif
                            </td>
                            <td>{{ \App\Models\WalletTransaction::reasonLabel($tx->reason) }}</td>
                            <td>{{ $tx->formatted_amount }}</td>
                            <td>{{ number_format($tx->balance_after / 100) }} ل.س</td>
                            <td>{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">لا يوجد حركات بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $transactions->links() }}</div>
</div>
@endsection
