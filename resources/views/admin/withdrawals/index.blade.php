@extends('layouts.app')

@section('title', 'تحويل أرباح البائعين - لوحة المدير')

@push('styles')
<style>
    .ts-wd-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 14px; padding: 1.1rem 1.2rem; margin-bottom: 1rem; }
    .ts-wd-dest { background: var(--bs-tertiary-bg); border-radius: 10px; padding: .75rem .9rem; font-size: .9rem; }
    .ts-wd-dest code { font-size: 1rem; letter-spacing: .05em; color: inherit; }
    .ts-copy { border: 0; background: none; color: var(--bs-secondary-color); padding: 0 .25rem; }
    .ts-copy:hover { color: var(--ts-emerald); }
    .ts-wd-status { font-size: .75rem; font-weight: 700; padding: .25rem .6rem; border-radius: 999px; }
    .ts-wd-status.pending { background: #fff3e0; color: #b25e00; }
    .ts-wd-status.approved, .ts-wd-status.processing { background: #e8f0fe; color: #1a56db; }
    .ts-wd-status.paid { background: #e6f6ec; color: #18794e; }
    .ts-wd-status.failed, .ts-wd-status.rejected { background: #fdecec; color: #b42318; }
    html.dark .ts-wd-status.pending { background: rgba(255,153,0,.15); color: #ffb454; }
    html.dark .ts-wd-status.approved, html.dark .ts-wd-status.processing { background: rgba(26,86,219,.2); color: #8fb3ff; }
    html.dark .ts-wd-status.paid { background: rgba(24,121,78,.2); color: #6fe0a6; }
    html.dark .ts-wd-status.failed, html.dark .ts-wd-status.rejected { background: rgba(180,35,24,.2); color: #ff9b91; }
    .ts-filter .nav-link { color: var(--bs-body-color); }
    .ts-filter .nav-link.active { background: var(--ts-emerald); color: #fff; }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - تحويل أرباح البائعين</span>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-light btn-sm">لوحة المدير</a>
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

    <div class="alert {{ $automatic ? 'alert-success' : 'alert-info' }} small">
        @if ($automatic)
            <i class="bi bi-lightning-charge"></i> <strong>{{ __('التحويل التلقائي مفعّل (:gateway):', ['gateway' => $gatewayName]) }}</strong>
            عند الموافقة يُرسل المبلغ مباشرة إلى حساب البائع عبر البنك، وتصل النتيجة تلقائياً.
        @else
            <i class="bi bi-info-circle"></i> <strong>التحويل يدوي:</strong>
            ١) وافق على الطلب فيُخصم المبلغ من محفظة البائع. ٢) حوّل المبلغ من حساب المنصة إلى الحساب الظاهر.
            ٣) سجّل رقم الحوالة (وصورة الإيصال إن وُجدت) فيصل للبائع إشعار بأن المال وصله.
            إن فشلت الحوالة اختر «فشل التحويل» ليُعاد المبلغ لمحفظة البائع.
        @endif
    </div>

    @php
        $tabs = ['open' => 'بحاجة لإجراء', 'pending' => 'بانتظار المراجعة', 'approved' => 'بانتظار التحويل', 'processing' => 'لدى البنك', 'paid' => 'تم التحويل', 'failed' => 'فشل', 'rejected' => 'مرفوض', 'all' => 'الكل'];
        $openCount = ($counts['pending']->c ?? 0) + ($counts['approved']->c ?? 0) + ($counts['processing']->c ?? 0);
    @endphp
    <ul class="nav nav-pills ts-filter gap-1 mb-3 flex-wrap">
        @foreach ($tabs as $key => $label)
            @php $n = $key === 'open' ? $openCount : ($key === 'all' ? null : ($counts[$key]->c ?? 0)); @endphp
            <li class="nav-item">
                <a class="nav-link py-1 px-3 {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.withdrawals.index', ['status' => $key]) }}">
                    {{ $label }} @if ($n)<span class="badge bg-secondary ms-1">{{ $n }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>

    @forelse ($requests as $req)
        <div class="ts-wd-card">
            <div class="row g-3 align-items-start">
                <div class="col-lg-3">
                    <div class="fw-bold">{{ $req->vendor->store_name }}</div>
                    <div class="small text-muted">{{ $req->vendor->user?->name }} · طلب #{{ $req->id }}</div>
                    <div class="fs-4 fw-bold mt-1">{{ $req->formatted_amount }}</div>
                    <div class="small text-muted">{{ $req->created_at->format('Y-m-d H:i') }}</div>
                    <span class="ts-wd-status {{ $req->status }} d-inline-block mt-2">{{ $req->status_label }}</span>
                </div>

                <div class="col-lg-5">
                    @if ($req->payout_account_number)
                        <div class="ts-wd-dest">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">{{ $req->payout_type_label }}</span>
                                @if ($req->paymentMethod)
                                    <form action="{{ route('admin.payout-methods.verify', $req->paymentMethod) }}" method="POST">
                                        @csrf
                                        <button class="btn btn-link btn-sm p-0 {{ $req->paymentMethod->is_verified ? 'text-primary' : 'text-warning' }}" title="تبديل حالة التوثيق">
                                            <i class="bi {{ $req->paymentMethod->is_verified ? 'bi-patch-check-fill' : 'bi-patch-exclamation' }}"></i>
                                            {{ $req->paymentMethod->is_verified ? 'موثّق' : 'غير موثّق' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                            <div class="fw-bold">{{ $req->payout_provider }}</div>
                            <div class="d-flex align-items-center gap-1">
                                <code dir="ltr">{{ $req->payout_account_number }}</code>
                                <button type="button" class="ts-copy" data-copy="{{ $req->payout_account_number }}" title="نسخ"><i class="bi bi-copy"></i></button>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <span>{{ $req->payout_account_holder_name }}</span>
                                <button type="button" class="ts-copy" data-copy="{{ $req->payout_account_holder_name }}" title="نسخ"><i class="bi bi-copy"></i></button>
                            </div>
                        </div>
                        @if ($req->note)<div class="small mt-2"><i class="bi bi-chat-left-text"></i> {{ $req->note }}</div>@endif
                    @else
                        <span class="text-danger small">لا توجد بيانات تحويل محفوظة لهذا الطلب.</span>
                    @endif
                </div>

                <div class="col-lg-4">
                    @switch($req->status)
                        @case('pending')
                            <form action="{{ route('admin.withdrawals.approve', $req) }}" method="POST" class="mb-2" data-confirm="{{ $automatic ? __('الموافقة ستخصم :amount من محفظة البائع وترسل الحوالة للبنك. متابعة؟', ['amount' => $req->formatted_amount]) : __('الموافقة ستخصم :amount من محفظة البائع. متابعة؟', ['amount' => $req->formatted_amount]) }}">
                                @csrf
                                <button class="btn btn-success w-100" @disabled(! $req->payout_account_number)>
                                    <i class="bi bi-check2-circle"></i> {{ $automatic ? 'موافقة وتحويل عبر البنك' : 'موافقة وخصم من المحفظة' }}
                                </button>
                            </form>
                            <form action="{{ route('admin.withdrawals.reject', $req) }}" method="POST" class="input-group input-group-sm">
                                @csrf
                                <input type="text" name="admin_note" class="form-control" placeholder="سبب الرفض" required>
                                <button class="btn btn-outline-danger">رفض</button>
                            </form>
                            @break

                        @case('approved')
                        @case('processing')
                            @if ($req->failure_reason)
                                <div class="alert alert-warning small py-1 px-2 mb-2">{{ $req->failure_reason }}</div>
                            @endif
                            @if ($req->status === 'approved' && $automatic)
                                <form action="{{ route('admin.withdrawals.send', $req) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-send"></i> إرسال/إعادة إرسال للبنك</button>
                                </form>
                            @endif
                            <form action="{{ route('admin.withdrawals.paid', $req) }}" method="POST" enctype="multipart/form-data" class="mb-2">
                                @csrf
                                <input type="text" name="transfer_reference" class="form-control form-control-sm mb-1" placeholder="رقم الحوالة / المرجع البنكي" value="{{ $req->transfer_reference }}" required>
                                <input type="file" name="receipt" class="form-control form-control-sm mb-1" accept="image/*,application/pdf">
                                <button class="btn btn-success btn-sm w-100"><i class="bi bi-cash-coin"></i> تم التحويل</button>
                            </form>
                            <form action="{{ route('admin.withdrawals.failed', $req) }}" method="POST" class="input-group input-group-sm" data-confirm="سيُعاد المبلغ إلى محفظة البائع. متابعة؟">
                                @csrf
                                <input type="text" name="failure_reason" class="form-control" placeholder="سبب فشل التحويل" required>
                                <button class="btn btn-outline-danger">فشل التحويل</button>
                            </form>
                            @break

                        @case('paid')
                            <div class="small">
                                <div>رقم الحوالة: <code dir="ltr">{{ $req->transfer_reference }}</code></div>
                                <div class="text-muted">{{ $req->paid_at?->format('Y-m-d H:i') }} · {{ $req->processor?->name }}</div>
                                @if ($req->receipt_path)
                                    <a href="{{ route('withdrawals.receipt', $req) }}" target="_blank" class="btn btn-outline-secondary btn-sm mt-2"><i class="bi bi-receipt"></i> الإيصال</a>
                                @endif
                            </div>
                            @break

                        @default
                            <div class="small text-muted">{{ $req->admin_note ?: $req->failure_reason }}</div>
                    @endswitch
                </div>
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-5 border rounded-3">لا توجد طلبات في هذا القسم.</div>
    @endforelse

    <div class="mt-3">{{ $requests->links() }}</div>
</div>

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-copy]');
    if (!btn || !navigator.clipboard) return;
    navigator.clipboard.writeText(btn.dataset.copy).then(function () {
        var icon = btn.querySelector('i'); icon.className = 'bi bi-check2';
        setTimeout(function () { icon.className = 'bi bi-copy'; }, 1200);
    });
});
</script>
@endpush
@endsection
