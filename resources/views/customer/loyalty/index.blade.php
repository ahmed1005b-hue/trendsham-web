@extends('layouts.customer-account')
@php($accountTitle = 'نقاطي')

@push('styles')
<style>
    .ts-pts-hero { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 170px), 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .ts-pts-box { border: 1px solid #ededed; border-radius: 10px; padding: .9rem 1rem; background: #fff; }
    .ts-pts-box--main { background: linear-gradient(135deg, #0B5F4A, #073D30); color: #fff; border: 0; }
    .ts-pts-box .v { font-size: 1.6rem; font-weight: 800; line-height: 1.2; }
    .ts-pts-box .l { font-size: .8rem; opacity: .8; }
    .ts-pts-box--main .v { color: #F1D9A0; }
    .ts-pts-rule { background: #FBF6EA; border: 1px dashed #C9962C; border-radius: 10px; padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .ts-pts-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
    .ts-pts-row:last-child { border-bottom: 0; }
    .ts-pts-row .meta { color: #888; font-size: .78rem; }
    .ts-pts-plus { color: #0B5F4A; font-weight: 800; white-space: nowrap; }
    .ts-pts-minus { color: #B3261E; font-weight: 800; white-space: nowrap; }
    html.dark .ts-pts-box { background: var(--tsd-surface); border-color: var(--tsd-border); color: var(--tsd-text); }
    html.dark .ts-pts-box--main { background: linear-gradient(135deg, #0d4a3a, #062a21); }
    html.dark .ts-pts-rule { background: rgba(201, 150, 44, .1); color: var(--tsd-text); }
    html.dark .ts-pts-row { border-bottom-color: var(--tsd-border); }
    html.dark .ts-pts-plus { color: #6fd3a8; }
    html.dark .ts-pts-minus { color: #ff8a80; }
</style>
@endpush

@section('account_content')
@php($fmt = fn ($minor) => number_format($minor / 100).' ل.س')
<div class="ts-account-card">
    <div class="ts-account-head">
        <h1><i class="bi bi-gem me-1" aria-hidden="true"></i> نقاطي</h1>
        <a href="{{ route('customer.coupons.index') }}" class="small">محفظة الكوبونات</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @error('points')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    @unless ($settings->enabled())
        <div class="alert alert-warning">برنامج النقاط متوقف مؤقتاً. رصيدك محفوظ.</div>
    @endunless

    <div class="ts-pts-hero">
        <div class="ts-pts-box ts-pts-box--main">
            <div class="l">رصيدك</div>
            <div class="v">{{ number_format($account->balance) }} <small class="fs-6">{{ __('نقطة') }}</small></div>
            <div class="l">{{ __('قيمتها :value', ['value' => $fmt($balanceValue)]) }}</div>
        </div>
        <div class="ts-pts-box">
            <div class="l">معلّقة</div>
            <div class="v">{{ number_format($pending) }}</div>
            <div class="l">تُضاف عند استلام طلباتك الحالية</div>
        </div>
        <div class="ts-pts-box">
            <div class="l">كسبت حتى الآن</div>
            <div class="v">{{ number_format($account->lifetime_earned) }}</div>
            <div class="l">منذ انضمامك</div>
        </div>
    </div>

    @if ($account->debt > 0)
        <div class="alert alert-secondary small">{{ __('عليك :points من طلب مرتجع، تُسدَّد تلقائياً من نقاطك القادمة.', ['points' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($account->debt)]) }}</div>
    @endif

    @if ($expiringPoints > 0)
        <div class="alert alert-warning small" role="status">
            <i class="bi bi-hourglass-split" aria-hidden="true"></i>
            {{ __(':points تنتهي خلال 30 يوماً (أقربها في :date). استخدمها قبل ذلك.', ['points' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($expiringPoints), 'date' => $expiringAt->format('Y-m-d')]) }}
        </div>
    @endif

    <div class="ts-pts-rule">
        <strong>كيف تعمل؟</strong>
        {{ __('كل :amount تدفعها = نقطة، تُضاف عند استلام الطلب.', ['amount' => $fmt($settings->spendPerPoint())]) }}
        {{ __('النقطة = خصم :value. استخدمها مباشرة في صفحة الدفع، أو حوّلها إلى كوبون.', ['value' => $fmt($settings->pointValue())]) }}
        {{ __('صلاحية النقاط :months شهراً.', ['months' => $settings->expiryMonths()]) }}
    </div>

    @if ($settings->enabled())
        <form method="POST" action="{{ route('customer.loyalty.convert') }}" class="ts-pts-box mb-3">
            @csrf
            <label for="ptsConvert" class="fw-bold mb-2 d-block">حوّل نقاطك إلى كوبون</label>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input type="number" id="ptsConvert" name="points" class="form-control" style="max-width: 160px"
                       min="{{ $settings->minRedeem() }}" max="{{ $account->balance }}" step="1"
                       value="{{ old('points', $account->balance >= $settings->minRedeem() ? $account->balance : '') }}"
                       @disabled($account->balance < $settings->minRedeem())>
                <span class="text-muted small" id="ptsConvertValue"></span>
                <button class="btn btn-success ms-auto" @disabled($account->balance < $settings->minRedeem())>
                    <i class="bi bi-ticket-perforated" aria-hidden="true"></i> حوّل إلى كوبون
                </button>
            </div>
            <div class="small text-muted mt-2">
                @if ($account->balance < $settings->minRedeem())
                    {{ __('تحتاج :min على الأقل. باقي لك :left.', ['min' => \App\Loyalty\LoyaltyCalculator::localPointsLabel($settings->minRedeem()), 'left' => $settings->minRedeem() - $account->balance]) }}
                @else
                    {{ __('الكوبون يظهر في محفظتك وصالح :days يوماً. إن لم تستخدمه تعود نقاطه إليك.', ['days' => $settings->couponValidDays()]) }}
                @endif
            </div>
        </form>
    @endif

    <h2 class="h6 fw-bold mt-3 mb-2">السجل</h2>
    @forelse ($transactions as $t)
        <div class="ts-pts-row">
            <div>
                <div>{{ $t->label() }}</div>
                <div class="meta">
                    {{ $t->created_at?->format('Y-m-d') }}
                    @if (in_array($t->type, \App\Loyalty\Models\LoyaltyTransaction::LOT_TYPES, true) && $t->remaining > 0 && $t->expires_at)
                        · {{ __('تنتهي :date', ['date' => $t->expires_at->format('Y-m-d')]) }}
                    @endif
                </div>
            </div>
            <span class="{{ $t->points >= 0 ? 'ts-pts-plus' : 'ts-pts-minus' }}" dir="ltr">{{ $t->points >= 0 ? '+' : '' }}{{ number_format($t->points) }}</span>
        </div>
    @empty
        <div class="text-center text-muted py-4">
            <i class="bi bi-gem fs-1 d-block mb-2" aria-hidden="true"></i>
            لا توجد حركات بعد. اطلب الآن واكسب أول نقاطك عند الاستلام.
        </div>
    @endforelse

    <div class="mt-3">{{ $transactions->links() }}</div>
</div>

@if ($settings->enabled())
<script>
(() => {
    const input = document.getElementById('ptsConvert');
    const out = document.getElementById('ptsConvertValue');
    if (!input || !out) return;
    const value = {{ $settings->pointValue() }} / 100, cur = @json(__('ل.س'));
    const show = () => { const n = parseInt(input.value || '0', 10); out.textContent = n > 0 ? '= ' + (n * value).toLocaleString('en-US') + ' ' + cur : ''; };
    input.addEventListener('input', show); show();
})();
</script>
@endif
@endsection
