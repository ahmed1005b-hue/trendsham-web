{{-- شريط "كوبونات المتجر" (صفحة المتجر والمنتج). $storeCoupons: صفوف StoreCoupons (campaign, state, remaining) --}}
@if (($storeCoupons ?? collect())->isNotEmpty())
    @once
        @push('styles')
        <style>
            .ts-sc { margin-bottom: 1rem; }
            .ts-sc-rail { display: flex; gap: .65rem; overflow-x: auto; padding-bottom: .35rem; scroll-snap-type: x mandatory; }
            .ts-sc-item { flex: 0 0 min(250px, 82%); scroll-snap-align: start; display: flex; border-radius: 12px; overflow: hidden;
                border: 1px solid #ead9b0; background: #FFFBF0; }
            .ts-sc-val { background: linear-gradient(160deg, #0B5F4A, #073D30); color: #F1D9A0; font-weight: 800; padding: .7rem .6rem;
                display: flex; align-items: center; justify-content: center; text-align: center; width: 92px; font-size: .9rem; line-height: 1.3;
                border-inline-end: 2px dashed #FFFBF0; }
            .ts-sc-body { padding: .55rem .7rem; display: grid; gap: .2rem; flex: 1; min-width: 0; font-size: .8rem; }
            .ts-sc-body .t { font-weight: 700; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .ts-sc-body .m { color: #7a6a45; }
            .ts-sc-body .btn { padding: .15rem .6rem; font-size: .78rem; justify-self: start; }
            html.dark .ts-sc-item { background: rgba(201, 150, 44, .08); border-color: rgba(201, 150, 44, .35); }
            html.dark .ts-sc-val { border-inline-end-color: var(--tsd-surface); }
            html.dark .ts-sc-body { color: var(--tsd-text); }
            html.dark .ts-sc-body .m { color: #cdb98a; }
        </style>
        @endpush
    @endonce

    <section class="ts-sc" aria-label="كوبونات المتجر">
        @if (session('error'))
            <div class="alert alert-warning py-2 small" role="alert">{{ session('error') }}</div>
        @endif
        <h2 class="h6 fw-bold mb-2"><i class="bi bi-ticket-perforated" style="color:#C9962C" aria-hidden="true"></i> كوبونات المتجر</h2>
        <div class="ts-sc-rail">
            @foreach ($storeCoupons as $row)
                @php
                    $c = $row['campaign'];
                    $short = match ($c->type) {
                        'percentage' => $c->value.'%',
                        'fixed' => number_format($c->value / 100).' ل.س',
                        'free_shipping' => __('شحن مجاني'),
                        default => __('هدية'),
                    };
                @endphp
                <div class="ts-sc-item">
                    <div class="ts-sc-val">{{ $short }}</div>
                    <div class="ts-sc-body">
                        <div class="t" title="{{ $c->title }}">{{ $c->title }}</div>
                        <div class="m">{{ \App\Coupons\StoreCoupons::conditionLabel($c) }}</div>
                        <div class="m">{{ __('حتى :date', ['date' => $c->ends_at?->format('Y-m-d')]) }} · {{ __('باقي :n', ['n' => number_format($row['remaining'])]) }}</div>
                        @switch ($row['state'])
                            @case('collected')
                                <span class="badge text-bg-success justify-self-start"><i class="bi bi-check2" aria-hidden="true"></i> محفوظ</span>
                                @break
                            @case('sold_out')
                                <span class="badge text-bg-secondary">نفدت</span>
                                @break
                            @case('login_required')
                                <a href="{{ route('login') }}" class="btn btn-outline-success">احفظ</a>
                                @break
                            @default
                                <form method="POST" action="{{ route('coupons.collect', $c) }}">
                                    @csrf
                                    <button class="btn btn-success">احفظ</button>
                                </form>
                        @endswitch
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
