{{--
    إعلان مساحة مؤجرة: خلفية (صورة البائع فوق تدرجه) وعليها سكة أفقية من بطاقات المنتجات الموحّدة،
    مع أسهم تمرير على الكمبيوتر وسحب باليد على الجوال (سكة ts-rail من الرئيسية).
--}}
@once
    <style>
        .ts-showcase { position: relative; overflow: hidden; border-radius: 18px; padding: 1rem 1.1rem 1.25rem; color: var(--sc-text, #fff);
            background-size: cover; background-position: center; box-shadow: 0 10px 30px -18px rgba(7, 61, 48, .55); }
        .ts-showcase__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem 1rem; margin-bottom: .85rem; }
        .ts-showcase__store { display: inline-flex; align-items: center; gap: .55rem; color: inherit; text-decoration: none; min-width: 0; }
        .ts-showcase__store:hover, .ts-showcase__store:focus-visible { color: inherit; text-decoration: underline; text-underline-offset: 4px; }
        .ts-showcase__logo { width: 42px; height: 42px; flex: 0 0 auto; border-radius: 50%; object-fit: cover; background: #fff; box-shadow: 0 0 0 2px rgba(255, 255, 255, .7); }
        .ts-showcase__logo--icon { display: grid; place-items: center; color: #0B5F4A; font-size: 1.2rem; }
        .ts-showcase__title { margin: 0; font-size: 1.25rem; font-weight: 800; line-height: 1.25; }
        .ts-showcase__sub { margin: .1rem 0 0; font-size: .88rem; opacity: .85; }
        .ts-showcase__tag { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .65rem; border-radius: 999px;
            background: rgba(255, 255, 255, .92); color: #17251f; font-size: .75rem; font-weight: 700; }
        .ts-showcase .ts-rail__item { flex-basis: 200px; }
        .ts-showcase .ts-rail__nav--prev { inset-inline-start: -.35rem; }
        .ts-showcase .ts-rail__nav--next { inset-inline-end: -.35rem; }
        .ts-showcase [data-ts-fade] { -webkit-mask-image: none !important; mask-image: none !important; }   /* الحواف المتلاشية تشوّه الخلفية */
        .ts-showcase .ts-market-card { box-shadow: 0 4px 14px -8px rgba(0, 0, 0, .35); }
        @media (max-width: 575.98px) {
            .ts-showcase { padding: .85rem .75rem 1rem; border-radius: 14px; }
            .ts-showcase .ts-rail__item { flex-basis: 165px; }
            .ts-showcase__title { font-size: 1.05rem; }
        }
    </style>
@endonce

@foreach ($showcases as $showcase)
    @php
        $gradientAngle = match ($showcase->gradient_direction) {
            'horizontal' => '90deg',
            'diagonal' => '135deg',
            default => '180deg',
        };
        $gradientColors = is_array($showcase->gradient_colors) && count($showcase->gradient_colors) >= 2
            ? $showcase->gradient_colors
            : ['#0B5F4A', '#073D30'];
        $layers = 'linear-gradient('.$gradientAngle.', '.implode(', ', $gradientColors).')';
        if ($bgUrl = $showcase->backgroundUrl()) {
            $layers = "url('".e($bgUrl)."'), ".$layers;
        }
        $store = $showcase->seller;
    @endphp
    <section class="container ts-home-section" aria-label="{{ __('إعلان ممول من :store', ['store' => $store->store_name]) }}">
        <article class="ts-showcase" style="--sc-text: {{ $showcase->textHex() }}; background-image: {!! $layers !!};">
            <div class="ts-showcase__head">
                <a href="{{ route('stores.show', $store) }}" class="ts-showcase__store" aria-label="{{ __('زيارة متجر :store', ['store' => $store->store_name]) }}">
                    @if ($store->store_logo)
                        <img src="{{ asset('storage/'.$store->store_logo) }}" alt="" class="ts-showcase__logo" loading="lazy">
                    @else
                        <span class="ts-showcase__logo ts-showcase__logo--icon" aria-hidden="true"><i class="bi bi-shop"></i></span>
                    @endif
                    <span class="min-w-0">
                        <h2 class="ts-showcase__title" translate="no">{{ $showcase->title }}</h2>
                        @if ($showcase->subtitle)
                            <p class="ts-showcase__sub" translate="no">{{ $showcase->subtitle }}</p>
                        @endif
                    </span>
                </a>
                <span class="ts-showcase__tag"><i class="bi bi-megaphone-fill" aria-hidden="true"></i> {{ __('إعلان ممول') }} · <span translate="no">{{ $store->store_name }}</span></span>
            </div>

            <div class="ts-rail ts-rail--cards" data-ts-rail>
                <button type="button" class="ts-rail__nav ts-rail__nav--prev" data-dir="-1" aria-label="السابق"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                <div class="ts-rail__track" data-ts-fade>
                    @foreach ($showcase->products as $product)
                        <div class="ts-rail__item">
                            @include('partials.product-card', ['product' => $product, 'showDiscountBadge' => true])
                        </div>
                    @endforeach
                </div>
                <button type="button" class="ts-rail__nav ts-rail__nav--next" data-dir="1" aria-label="التالي"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            </div>
        </article>
    </section>
@endforeach
