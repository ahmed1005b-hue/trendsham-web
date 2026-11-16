{{-- بطاقة كوبون: $coupon (CustomerCoupon) ، $muted (bool) ، $note (?string) --}}
@php($c = $coupon->campaign)
@php($exp = $coupon->effectiveExpiry())
<div class="ts-coupon {{ $c->funder === 'seller' ? 'is-seller' : '' }} {{ ($muted ?? false) ? 'is-muted' : '' }}">
    <div class="ts-coupon__value">
        <i class="bi {{ $c->type === 'free_shipping' ? 'bi-truck' : 'bi-ticket-perforated' }}" aria-hidden="true"></i>
        <strong>{{ $c->valueLabel() }}</strong>
    </div>
    <div class="ts-coupon__body">
        <div class="ts-coupon__title">{{ $c->title }}</div>
        <div class="ts-coupon__meta">{{ $c->sourceLabel() }}</div>
        @if ($coupon->status === 'available' && $c->campaignStateLabel())
            <div class="ts-coupon__note text-secondary">{{ $c->campaignStateLabel() }}</div>
        @endif
        @if ($c->min_order_amount > 0)
            <div class="ts-coupon__meta">{{ __('للطلبات من :amount ل.س', ['amount' => number_format($c->min_order_amount / 100)]) }}</div>
        @endif
        @if (! empty($note))
            <div class="ts-coupon__note">{{ $note }}</div>
        @elseif ($exp && $coupon->status === 'available')
            <div class="ts-coupon__meta {{ $exp->diffInDays(now(), true) < 3 ? 'text-danger fw-bold' : '' }}">
                {{ $exp->diffInDays(now(), true) < 3 ? __('ينتهي خلال :time', ['time' => $exp->locale(app()->getLocale())->diffForHumans(null, true)]) : __('ينتهي في :date', ['date' => $exp->format('Y-m-d')]) }}
            </div>
        @endif
    </div>
    </div>

@once
@push('styles')
<style>
    .ts-coupon { position: relative; display: flex; align-items: stretch; border: 1px solid var(--ts-border, #E8DFCC); border-radius: 14px; background: var(--ts-surface, #fff); overflow: hidden; }
    .ts-coupon__value { flex: 0 0 104px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem; padding: .7rem .4rem; background: var(--ts-emerald, #0B5F4A); color: #fff; text-align: center; font-size: .82rem; line-height: 1.3; border-inline-end: 2px dashed rgba(255,255,255,.55); }
    .ts-coupon__value i { font-size: 1.4rem; color: var(--ts-gold-light, #F1D9A0); }
    .ts-coupon.is-seller .ts-coupon__value { background: var(--ts-gold, #C9962C); }
    .ts-coupon.is-seller .ts-coupon__value i { color: #fff; }
    .ts-coupon.is-muted { opacity: .62; filter: grayscale(.5); }
    .ts-coupon__body { flex: 1; min-width: 0; padding: .7rem .85rem; }
    .ts-coupon__title { font-weight: 800; font-size: .95rem; margin-bottom: .15rem; }
    .ts-coupon__meta { font-size: .78rem; color: var(--ts-muted, #6b7770); }
    .ts-coupon__note { margin-top: .25rem; font-size: .78rem; font-weight: 700; color: var(--ts-emerald, #0B5F4A); }
</style>
@endpush
@endonce
