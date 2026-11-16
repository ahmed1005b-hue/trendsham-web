@extends('layouts.app')

@section('title', 'طرق استلام الأرباح - تريندشام')

@push('styles')
<style>
    .ts-pm-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 14px; padding: 1.1rem; height: 100%; display: flex; gap: .9rem; align-items: flex-start; }
    .ts-pm-card.is-default { border-color: var(--ts-emerald); box-shadow: 0 0 0 1px var(--ts-emerald) inset; }
    .ts-pm-icon { width: 46px; height: 46px; flex: 0 0 46px; border-radius: 12px; display: grid; place-items: center; font-size: 1.35rem; }
    .ts-pm-icon.bank_account { background: rgba(11,95,74,.12); color: #0B5F4A; }
    .ts-pm-icon.bank_card { background: rgba(37,99,235,.12); color: #2563eb; }
    .ts-pm-icon.electronic_wallet { background: rgba(201,150,44,.15); color: #b8861f; }
    .ts-pm-number { font-family: ui-monospace, Consolas, monospace; letter-spacing: .08em; direction: ltr; display: inline-block; }
    .ts-form-panel { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 14px; padding: 1.4rem; }
    .ts-type-picker .btn { flex: 1 1 0; white-space: nowrap; }
    .ts-secure-note { font-size: .82rem; color: var(--bs-secondary-color); }
    html.dark .ts-pm-icon.bank_account { color: #5cc8a0; }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - طرق استلام الأرباح</span>
        <a href="{{ route('seller.wallet.index') }}" class="btn btn-outline-light btn-sm">العودة للمحفظة</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="ts-form-panel">
                <h2 class="h5 mb-1"><i class="bi bi-plus-circle"></i> إضافة طريقة استلام</h2>
                <p class="ts-secure-note mb-3">الحساب الذي ستحوّل الإدارة أرباحك إليه.</p>

                <form action="{{ route('seller.wallet.payment-methods.add') }}" method="POST" id="pmForm">
                    @csrf
                    <label class="form-label small fw-bold">النوع</label>
                    <div class="btn-group ts-type-picker w-100 mb-3" role="group">
                        @foreach ($types as $key => $label)
                            <input type="radio" class="btn-check" name="type" id="type-{{ $key }}" value="{{ $key }}" @checked(old('type', 'bank_account') === $key)>
                            <label class="btn btn-outline-success btn-sm" for="type-{{ $key }}">{{ $label }}</label>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" for="provider" data-label-for="provider">البنك</label>
                        <input type="text" name="provider" id="provider" class="form-control" list="providers-list" value="{{ old('provider') }}" required autocomplete="off">
                        <datalist id="providers-list"></datalist>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" for="account_number" data-label-for="number">رقم IBAN</label>
                        <input type="text" name="account_number" id="account_number" class="form-control ts-pm-number w-100 text-start" value="{{ old('account_number') }}" required autocomplete="off" inputmode="text">
                        <div class="form-text" id="number-help"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold" for="holder">اسم صاحب الحساب (كما في البنك)</label>
                        <input type="text" name="account_holder_name" id="holder" class="form-control" value="{{ old('account_holder_name', auth()->user()->name) }}" required>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" @checked(old('is_default') || $paymentMethods->isEmpty())>
                        <label class="form-check-label" for="is_default">اجعلها الطريقة الافتراضية للسحب</label>
                    </div>

                    <button class="btn btn-success w-100">حفظ طريقة الاستلام</button>
                    <p class="ts-secure-note mt-3 mb-0"><i class="bi bi-shield-lock"></i> رقم الحساب يُحفظ مشفّراً ولا يظهر كاملاً إلا للإدارة عند تنفيذ الحوالة.</p>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <h2 class="h5 mb-3">طرقي المحفوظة</h2>
            @if ($paymentMethods->isEmpty())
                <div class="text-center text-muted border rounded-3 py-5">
                    <i class="bi bi-bank fs-1 d-block mb-2 opacity-50"></i>
                    لا توجد طرق استلام بعد. أضف حسابك البنكي أو محفظتك لتتمكن من سحب أرباحك.
                </div>
            @else
                <div class="row g-3">
                    @foreach ($paymentMethods as $method)
                        <div class="col-md-6">
                            <div class="ts-pm-card {{ $method->is_default ? 'is-default' : '' }}">
                                <div class="ts-pm-icon {{ $method->type }}">
                                    <i class="bi {{ ['bank_account' => 'bi-bank', 'bank_card' => 'bi-credit-card-2-front', 'electronic_wallet' => 'bi-phone'][$method->type] ?? 'bi-wallet2' }}"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-bold">{{ $method->provider }}</div>
                                    <div class="small text-muted">{{ $method->type_label }}</div>
                                    <div class="ts-pm-number my-1">{{ $method->masked_number }}</div>
                                    <div class="small text-truncate">{{ $method->account_holder_name }}</div>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @if ($method->is_default)<span class="badge bg-success">افتراضية</span>@endif
                                        @if ($method->is_verified)
                                            <span class="badge bg-primary"><i class="bi bi-patch-check"></i> موثّقة</span>
                                        @else
                                            <span class="badge bg-warning text-dark">بانتظار التوثيق</span>
                                        @endif
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        @unless ($method->is_default)
                                            <form action="{{ route('seller.wallet.payment-methods.default', $method) }}" method="POST">
                                                @csrf
                                                <button class="btn btn-outline-success btn-sm">افتراضية</button>
                                            </form>
                                        @endunless
                                        <form action="{{ route('seller.wallet.payment-methods.remove', $method) }}" method="POST" data-confirm="حذف طريقة الاستلام هذه؟">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm">حذف</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var providers = @json($providers);
    var meta = {
        bank_account: { provider: @json(__('البنك')), number: @json(__('رقم IBAN أو رقم الحساب')), help: @json(__('مثال: TR33 0006 1005 1978 6457 8413 26 أو SY.. — انسخه من تطبيق البنك.')) },
        bank_card: { provider: @json(__('نوع البطاقة')), number: @json(__('رقم البطاقة')), help: @json(__('16 رقماً كما هو مكتوب على البطاقة. لا نطلب CVV أو تاريخ الانتهاء.')) },
        electronic_wallet: { provider: @json(__('المحفظة')), number: @json(__('رقم المحفظة / الهاتف')), help: @json(__('رقم الهاتف المرتبط بالمحفظة، مثل 0933123456.')) }
    };
    var form = document.getElementById('pmForm');
    function apply() {
        var type = form.querySelector('input[name=type]:checked').value;
        document.querySelector('[data-label-for=provider]').textContent = meta[type].provider;
        document.querySelector('[data-label-for=number]').textContent = meta[type].number;
        document.getElementById('number-help').textContent = meta[type].help;
        var list = document.getElementById('providers-list');
        list.innerHTML = '';
        (providers[type] || []).forEach(function (p) { var o = document.createElement('option'); o.value = p; list.appendChild(o); });
        document.getElementById('account_number').setAttribute('inputmode', type === 'bank_account' ? 'text' : 'numeric');
    }
    form.querySelectorAll('input[name=type]').forEach(function (r) { r.addEventListener('change', function () { form.provider.value = ''; apply(); }); });
    apply();
})();
</script>
@endpush
@endsection
