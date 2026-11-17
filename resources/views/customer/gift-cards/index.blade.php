@extends('layouts.customer-account')
@php($accountTitle = 'بطاقات الهدايا')

@push('styles')
<style>
    .ts-gc-hero { background: linear-gradient(135deg, #0B5F4A, #073D30); color: #fff; border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1rem; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: center; }
    .ts-gc-hero .v { font-size: 1.7rem; font-weight: 800; color: #F1D9A0; line-height: 1.2; }
    .ts-gc-hero .l { font-size: .82rem; opacity: .85; }
    .ts-gc-box { border: 1px solid #ededed; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; background: #fff; }
    .ts-gc-box h2 { font-size: 1rem; font-weight: 800; margin-bottom: .75rem; }
    .ts-gc-presets { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
    .ts-gc-presets .btn-check + label { min-width: 110px; }
    .ts-gc-card { border: 1px dashed #C9962C; border-radius: 10px; padding: .75rem; margin-bottom: .6rem; display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; align-items: center; }
    .ts-gc-code { font-family: monospace; font-size: 1.05rem; font-weight: 700; letter-spacing: .08em; direction: ltr; }
    .ts-gc-row { display: flex; justify-content: space-between; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
    .ts-gc-row:last-child { border-bottom: 0; }
    html.dark .ts-gc-box { background: var(--tsd-surface); border-color: var(--tsd-border); color: var(--tsd-text); }
    html.dark .ts-gc-hero { background: linear-gradient(135deg, #0d4a3a, #062a21); }
    html.dark .ts-gc-row { border-bottom-color: var(--tsd-border); }
</style>
@endpush

@section('account_content')
<div class="ts-account-card">
    <div class="ts-account-head">
        <h1><i class="bi bi-credit-card-2-front me-1" aria-hidden="true"></i> بطاقات الهدايا</h1>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info" role="status">{{ session('info') }}</div>
    @endif

    <div class="ts-gc-hero">
        <div>
            <div class="l">رصيد الهدايا</div>
            <div class="v">{{ number_format($balance / 100) }} <small class="fs-6">ل.س</small></div>
            <div class="l">يُستخدم في صفحة الدفع، ويغطي المنتجات والشحن.</div>
        </div>
    </div>

    <div class="ts-gc-box">
        <h2><i class="bi bi-ticket-detailed" aria-hidden="true"></i> لديك بطاقة هدية؟</h2>
        <form method="POST" action="{{ route('customer.gift-cards.redeem') }}">
            @csrf
            <label for="code" class="form-label small">أدخل الكود كما وصلك</label>
            <div class="d-flex gap-2">
                <input id="code" name="code" type="text" maxlength="30" dir="ltr" autocomplete="off" placeholder="TS-XXXX-XXXX-XXXX"
                       value="{{ old('code') }}" class="form-control @error('code') is-invalid @enderror" aria-describedby="code-error">
                <button class="btn btn-success text-nowrap">أضف إلى رصيدي</button>
            </div>
            @error('code')
                <div id="code-error" class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </form>
    </div>

    <div class="ts-gc-box">
        <h2><i class="bi bi-gift" aria-hidden="true"></i> اشترِ بطاقة هدية</h2>
        @if (! $onlineEnabled)
            <div class="alert alert-secondary small mb-0">الدفع الإلكتروني غير متاح حالياً، لذا لا يمكن شراء بطاقات الهدايا الآن.</div>
        @else
            <form method="POST" action="{{ route('customer.gift-cards.purchase') }}">
                @csrf
                <fieldset>
                    <legend class="form-label small">القيمة</legend>
                    <div class="ts-gc-presets">
                        @foreach ($presets as $i => $preset)
                            <input type="radio" class="btn-check" name="amount" id="gc-amount-{{ $i }}" value="{{ $preset / 100 }}"
                                   @checked((int) old('amount', $presets[1] / 100) === $preset / 100)>
                            <label class="btn btn-outline-success" for="gc-amount-{{ $i }}">{{ number_format($preset / 100) }} ل.س</label>
                        @endforeach
                    </div>
                </fieldset>
                @error('amount')
                    <div class="alert alert-danger small py-2" role="alert">{{ $message }}</div>
                @enderror
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <label for="recipient_name" class="form-label small">اسم من ستهديه (اختياري)</label>
                        <input id="recipient_name" name="recipient_name" type="text" maxlength="60" class="form-control" value="{{ old('recipient_name') }}">
                    </div>
                    <div class="col-md-7">
                        <label for="message" class="form-label small">رسالة قصيرة (اختياري)</label>
                        <input id="message" name="message" type="text" maxlength="200" class="form-control" value="{{ old('message') }}">
                    </div>
                </div>
                <button class="btn btn-warning"><i class="bi bi-lock" aria-hidden="true"></i> ادفع بالبطاقة البنكية</button>
                <p class="small text-muted mt-2 mb-0">بعد الدفع يظهر لك كود البطاقة هنا، فترسله لمن تريد. صالحة للإدخال سنة كاملة.</p>
            </form>
        @endif
    </div>

    @if ($cards->isNotEmpty())
        <div class="ts-gc-box">
            <h2>بطاقات اشتريتها</h2>
            @foreach ($cards as $card)
                <div class="ts-gc-card">
                    <div>
                        <div class="fw-bold">{{ number_format($card->amount / 100) }} ل.س
                            @if ($card->recipient_name)
                                <span class="text-muted small">— {{ __('إلى :name', ['name' => $card->recipient_name]) }}</span>
                            @endif
                        </div>
                        @if ($card->status === \App\GiftCards\Models\GiftCard::ACTIVE && $card->code)
                            <div class="ts-gc-code" translate="no" id="gc-code-{{ $card->id }}">{{ \App\GiftCards\GiftCardService::format($card->code) }}</div>
                            <div class="small text-muted">{{ __('صالحة حتى :date', ['date' => $card->expires_at?->format('Y-m-d')]) }}</div>
                        @elseif ($card->redeemer)
                            <div class="small text-muted">{{ __('استخدمها :name في :date', ['name' => $card->redeemer->first_name, 'date' => $card->redeemed_at?->format('Y-m-d')]) }}</div>
                        @endif
                    </div>
                    @if ($card->status === \App\GiftCards\Models\GiftCard::ACTIVE && $card->code)
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-copy="#gc-code-{{ $card->id }}"><i class="bi bi-clipboard" aria-hidden="true"></i> نسخ</button>
                    @else
                        <span class="badge bg-secondary">{{ $card->statusLabel() }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($transactions->isNotEmpty())
        <div class="ts-gc-box">
            <h2>حركات الرصيد</h2>
            @foreach ($transactions as $t)
                <div class="ts-gc-row">
                    <div>
                        <div>{{ $t->label() }}</div>
                        <div class="small text-muted">{{ $t->created_at?->format('Y-m-d') }}</div>
                    </div>
                    <div class="fw-bold {{ $t->amount > 0 ? 'text-success' : 'text-danger' }}" dir="ltr">{{ $t->amount > 0 ? '+' : '' }}{{ number_format($t->amount / 100) }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
        var text = document.querySelector(button.dataset.copy).textContent.trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                var original = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> ' + @json(__('تم النسخ'));
                setTimeout(function () { button.innerHTML = original; }, 1500);
            });
        }
    });
});
</script>
@endpush
