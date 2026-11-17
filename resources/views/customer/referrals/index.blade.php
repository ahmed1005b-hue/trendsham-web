@extends('layouts.customer-account')
@php($accountTitle = 'دعوة صديق')

@push('styles')
<style>
    .ts-ref-hero { background: linear-gradient(135deg, #0B5F4A, #073D30); color: #fff; border-radius: 12px; padding: 1.1rem 1.2rem; margin-bottom: 1rem; }
    .ts-ref-hero h2 { font-size: 1.05rem; font-weight: 800; color: #F1D9A0; margin-bottom: .35rem; }
    .ts-ref-hero p { font-size: .88rem; opacity: .9; margin-bottom: .9rem; }
    .ts-ref-code { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .ts-ref-code output { font-size: 1.5rem; font-weight: 800; letter-spacing: .15em; background: rgba(255,255,255,.12); border-radius: 8px; padding: .3rem .9rem; direction: ltr; }
    .ts-ref-link { display: flex; gap: .5rem; margin-top: .75rem; }
    .ts-ref-link input { direction: ltr; font-size: .85rem; }
    .ts-ref-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; margin-bottom: 1rem; }
    .ts-ref-stat { border: 1px solid #ededed; border-radius: 10px; padding: .75rem; text-align: center; background: #fff; }
    .ts-ref-stat .v { font-size: 1.4rem; font-weight: 800; }
    .ts-ref-stat .l { font-size: .78rem; color: #777; }
    .ts-ref-steps { background: #FBF6EA; border: 1px dashed #C9962C; border-radius: 10px; padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .ts-ref-steps ol { margin: 0; padding-inline-start: 1.2rem; }
    .ts-ref-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
    .ts-ref-row:last-child { border-bottom: 0; }
    .ts-ref-row .meta { color: #888; font-size: .78rem; }
    html.dark .ts-ref-hero { background: linear-gradient(135deg, #0d4a3a, #062a21); }
    html.dark .ts-ref-stat { background: var(--tsd-surface); border-color: var(--tsd-border); color: var(--tsd-text); }
    html.dark .ts-ref-stat .l, html.dark .ts-ref-row .meta { color: var(--tsd-muted, #aaa); }
    html.dark .ts-ref-steps { background: rgba(201, 150, 44, .1); color: var(--tsd-text); }
    html.dark .ts-ref-row { border-bottom-color: var(--tsd-border); }
    @media (max-width: 420px) { .ts-ref-stats { grid-template-columns: 1fr 1fr; } }
</style>
@endpush

@section('account_content')
@php($points = \App\Loyalty\LoyaltyCalculator::localPointsLabel($summary['reward_points']))
<div class="ts-account-card">
    <div class="ts-account-head">
        <h1><i class="bi bi-gift me-1" aria-hidden="true"></i> دعوة صديق</h1>
        <a href="{{ route('customer.loyalty.index') }}" class="small">نقاطي</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <div class="ts-ref-hero">
        <h2>{{ __('ادعُ أصدقاءك واحصلا معاً على :points', ['points' => $points]) }}</h2>
        <p>{{ __('شارك كودك أو الرابط. عندما يسجّل صديقك ويصله أول طلب بقيمة :amount أو أكثر، تحصل أنت وهو على النقاط.', ['amount' => number_format($summary['min_order'] / 100).' ل.س']) }}</p>
        <div class="ts-ref-code">
            <span class="small">كودك:</span>
            <output id="ref-code" translate="no">{{ $summary['code'] }}</output>
            <button type="button" class="btn btn-sm btn-light" data-copy="#ref-code"><i class="bi bi-clipboard" aria-hidden="true"></i> نسخ</button>
        </div>
        <div class="ts-ref-link">
            <label for="ref-link" class="visually-hidden">رابط الدعوة</label>
            <input id="ref-link" type="text" class="form-control form-control-sm" value="{{ $summary['link'] }}" readonly>
            <button type="button" class="btn btn-sm btn-warning text-nowrap" id="ref-share" data-text="{{ $summary['share_text'] }}"><i class="bi bi-share" aria-hidden="true"></i> مشاركة</button>
        </div>
    </div>

    <div class="ts-ref-stats">
        <div class="ts-ref-stat"><div class="v">{{ $summary['stats']['joined'] }}</div><div class="l">صديق انضم</div></div>
        <div class="ts-ref-stat"><div class="v">{{ $summary['stats']['rewarded'] }}</div><div class="l">وصله أول طلب</div></div>
        <div class="ts-ref-stat"><div class="v">{{ number_format($summary['stats']['points_earned']) }}</div><div class="l">نقطة ربحتها</div></div>
    </div>

    <div class="ts-ref-steps">
        <strong>كيف تعمل؟</strong>
        <ol>
            <li>أرسل رابطك أو كودك لصديق لم يسجّل في الموقع بعد.</li>
            <li>يُنشئ صديقك حساباً جديداً عبر الرابط، أو يُدخل كودك قبل أول طلب له.</li>
            <li>{{ __('عندما يصله أول طلب، تُضاف :points لك وله.', ['points' => $points]) }}</li>
        </ol>
    </div>

    @if ($summary['referred_by'])
        <div class="alert alert-light border small">
            {{ __('انضممت بدعوة من :name', ['name' => $summary['referred_by']['name']]) }} — {{ $summary['referred_by']['status_label'] }}
        </div>
    @elseif ($summary['can_apply'])
        <form method="POST" action="{{ route('customer.referrals.apply') }}" class="mb-3">
            @csrf
            <label for="code" class="form-label small fw-bold">هل دعاك صديق؟ أدخل كوده</label>
            <div class="d-flex gap-2">
                <input id="code" name="code" type="text" maxlength="20" value="{{ old('code') }}" dir="ltr" autocomplete="off"
                       class="form-control @error('code') is-invalid @enderror" aria-describedby="code-error">
                <button class="btn btn-success text-nowrap">تأكيد</button>
            </div>
            @error('code')
                <div id="code-error" class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </form>
    @endif

    <h2 class="h6 fw-bold mt-3">أصدقاء دعوتهم</h2>
    @forelse ($summary['invited'] as $friend)
        <div class="ts-ref-row">
            <div>
                <div translate="no">{{ $friend['name'] }}</div>
                <div class="meta">{{ __('انضم في :date', ['date' => $friend['joined_at']]) }}</div>
            </div>
            <span class="badge {{ $friend['status'] === 'rewarded' ? 'bg-success' : ($friend['status'] === 'reversed' ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ $friend['status_label'] }}</span>
        </div>
    @empty
        <p class="text-muted small mb-0">لم ينضم أحد بعد. شارك رابطك مع أصدقائك.</p>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function () {
    function flash(button) {
        var original = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i> ' + @json(__('تم النسخ'));
        setTimeout(function () { button.innerHTML = original; }, 1500);
    }
    function copy(text, button) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () { flash(button); });
        }
    }
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            copy(document.querySelector(button.dataset.copy).textContent.trim(), button);
        });
    });
    var share = document.getElementById('ref-share');
    share.addEventListener('click', function () {
        var link = document.getElementById('ref-link').value;
        if (navigator.share) {
            navigator.share({ text: share.dataset.text }).catch(function () {});
        } else {
            copy(link, share);
        }
    });
})();
</script>
@endpush
