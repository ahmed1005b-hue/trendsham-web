@extends('layouts.app')

@section('title', 'تريندشام - سوق الشام الإلكتروني')

{{-- الهيدر يلتصق مباشرة بشريط الأقسام: لا حشوة علوية لـ <main> في الرئيسية --}}
@section('main_class', 'pt-0 pb-4')

@section('topbar')
<div class="ts-topbar">
    <div class="container ts-topbar__row">
        <span><i class="bi bi-truck" aria-hidden="true"></i> توصيل لجميع المحافظات</span>
        <div class="ts-topbar__links">
            <a href="{{ auth()->check() ? route('customer.seller-application.create') : route('login') }}"><i class="bi bi-shop" aria-hidden="true"></i> بيع معنا</a>
            <a href="{{ route('pages.help') }}"><i class="bi bi-question-circle" aria-hidden="true"></i> مساعدة</a>
        </div>
    </div>
</div>
@endsection

@php
    $heroProduct = $dealProducts->first() ?? $newProducts->first();
    $heroImage = $heroProduct?->primaryImage;
    $featuredDailyDeal = ($dailyDeals ?? collect())->isNotEmpty() ? ($dailyDeal ?? $dailyDeals->first()) : null;
    $dealProduct = $featuredDailyDeal?->product;
    $dealsAnchor = $featuredDailyDeal ? '#flash-deals' : '#deals';
    $newestProduct = $newProducts->first();
    $activeDeals = ($dailyDeals ?? collect())->filter(fn ($d) => $d->product)->values();
    $dealCount = $activeDeals->count();
    // العدد يُعرض فقط حين يكون مقنعاً (3 فأكثر)
    $dealCountText = $dealCount >= 3 ? ($dealCount < 11 ? __(':n عروض نشطة الآن', ['n' => $dealCount]) : __(':n عرض نشط الآن', ['n' => $dealCount])) : null;
    $flashFillers = $dealProducts->reject(fn ($p) => $activeDeals->pluck('product_id')->contains($p->id))->values();
@endphp

@section('content')
{{-- 1) القصص --}}
@if ($stories->isNotEmpty())
<div class="container">
    <div class="ts-stories" data-ts-fade>
        @foreach ($stories as $story)
            <button type="button" class="ts-story-item {{ $story->isFromPlatform() ? 'is-platform' : '' }}"
                 data-bs-toggle="modal" data-bs-target="#story-modal"
                 data-story-image="{{ asset('storage/'.$story->image) }}"
                 data-story-caption="{{ $story->caption }}"
                 data-story-vendor="{{ $story->isFromPlatform() ? 'تريندشام' : $story->vendor->store_name }}"
                 data-story-platform="{{ $story->isFromPlatform() ? 'true' : 'false' }}"
                 aria-label="{{ __('مشاهدة قصة :name', ['name' => $story->isFromPlatform() ? __('تريندشام') : $story->vendor->store_name]) }}"
                 onclick="openStory({{ $loop->index }})">
                <span class="ts-story-ring">
                    <img src="{{ $story->image_thumb_url }}" alt="" loading="lazy">
                </span>
                <span class="ts-story-name" translate="no">{{ $story->isFromPlatform() ? 'تريندشام' : $story->vendor->store_name }}</span>
            </button>
        @endforeach
    </div>
</div>

<div class="modal fade" id="story-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content bg-dark border-0">
            <div class="modal-body p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2" style="z-index:2;" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                <div class="position-absolute top-0 start-0 end-0 d-flex gap-1 p-2" style="z-index:2;">
                    @foreach ($stories as $story)
                        <div class="flex-grow-1 overflow-hidden rounded" style="height:3px; background:rgba(255,255,255,.45);">
                            <div class="story-progress h-100 bg-white" style="width:0;"></div>
                        </div>
                    @endforeach
                </div>
                <img id="story-modal-img" src="" alt="" class="w-100" style="max-height:85vh; object-fit:contain; border-radius: .5rem;">
                <div class="position-absolute bottom-0 start-0 end-0 p-3 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,.7));">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <strong id="story-modal-vendor" class="small"></strong>
                        <i id="story-modal-badge" class="bi bi-patch-check-fill d-none" style="color: var(--ts-gold-light); font-size: .8rem;"></i>
                    </div>
                    <p id="story-modal-caption" class="small mb-0"></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- 2) البانرات الترويجية --}}
<section class="container ts-promo" aria-label="عروض وإعلانات">
    <div class="ts-banner" data-ts-banner aria-roledescription="عرض شرائح">
        <div class="ts-banner__track" aria-live="polite">
            {{-- الشريحة 1: هوية السوق --}}
            <article class="ts-slide ts-slide--emerald is-active" aria-roledescription="شريحة" aria-label="1 من 3">
                <div class="ts-slide__copy">
                    <p class="ts-slide__eyebrow"><span aria-hidden="true"></span> سوق الشام، أقرب لبيتك</p>
                    <h1 class="ts-slide__title">اختيارات من متاجر الشام، <em>بمكان واحد.</em></h1>
                    <p class="ts-slide__text">منتجات البائعين المحليين، عروض يومية، وتوصيل لكل المحافظات.</p>
                    <div class="ts-slide__actions">
                        <a href="#deals" class="ts-slide__cta">اكتشف العروض <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
                        <a href="#browse-categories" class="ts-slide__link">تصفّح الأقسام</a>
                    </div>
                </div>
                <div class="ts-slide__visual" aria-hidden="true">
                    @if ($heroProduct)
                        <a href="{{ route('products.show', $heroProduct) }}" class="ts-arch" tabindex="-1">
                            @if ($heroImage)
                                <img src="{{ asset('storage/'.$heroImage->path) }}" alt="" fetchpriority="high">
                            @else
                                <span class="ts-arch__fallback"><i class="bi bi-bag-heart"></i></span>
                            @endif
                            <span class="ts-arch__tag">
                                <span class="ts-arch__name" translate="no">{{ $heroProduct->name }}</span>
                                <span class="ts-arch__price">{{ $heroProduct->formatted_price }}</span>
                            </span>
                        </a>
                    @else
                        <span class="ts-arch"><span class="ts-arch__fallback"><i class="bi bi-bag-heart"></i></span></span>
                    @endif
                </div>
            </article>

            {{-- الشريحة 2: عروض اليوم --}}
            <article class="ts-slide ts-slide--gold" aria-roledescription="شريحة" aria-label="2 من 3">
                <div class="ts-slide__copy">
                    <p class="ts-slide__eyebrow"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> لفترة محدودة</p>
                    <h2 class="ts-slide__title">عروض اليوم، <em>بأسعار ما بتتكرر.</em></h2>
                    <p class="ts-slide__text">
                        @if ($featuredDailyDeal)
                            {{ $dealCountText ? $dealCountText.' — ' : '' }}الكمية محدودة وينتهي العرض مع نهاية الوقت.
                        @else
                            خصومات حقيقية من متاجر السوق، تتجدد باستمرار.
                        @endif
                    </p>
                    <div class="ts-slide__actions">
                        <a href="{{ $dealsAnchor }}" class="ts-slide__cta">تسوّق العروض <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="ts-slide__visual" aria-hidden="true">
                    <span class="ts-medal">
                        @if ($dealProduct && $dealProduct->primaryImage)
                            <img src="{{ $dealProduct->primaryImage->thumb_url }}" alt="" loading="lazy">
                        @else
                            <i class="bi bi-lightning-charge-fill"></i>
                        @endif
                        @if ($featuredDailyDeal && (int) round($featuredDailyDeal->discount_percentage) > 0)
                            <span class="ts-medal__off">-{{ (int) round($featuredDailyDeal->discount_percentage) }}%</span>
                        @endif
                    </span>
                </div>
            </article>

            {{-- الشريحة 3: دعوة البائعين --}}
            <article class="ts-slide ts-slide--night" aria-roledescription="شريحة" aria-label="3 من 3">
                <div class="ts-slide__copy">
                    <p class="ts-slide__eyebrow"><i class="bi bi-shop" aria-hidden="true"></i> للبائعين</p>
                    <h2 class="ts-slide__title">عندك متجر؟ <em>وصّل منتجاتك لكل المحافظات.</em></h2>
                    <p class="ts-slide__text">افتح متجرك على تريندشام، واعرض منتجاتك لآلاف المتسوقين.</p>
                    <div class="ts-slide__actions">
                        <a href="{{ auth()->check() ? route('customer.seller-application.create') : route('login') }}" class="ts-slide__cta">ابدأ البيع معنا <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="ts-slide__visual" aria-hidden="true">
                    <span class="ts-medal ts-medal--outline"><i class="bi bi-shop-window"></i></span>
                </div>
            </article>
        </div>

        <button type="button" class="ts-banner__nav ts-banner__nav--prev" data-dir="-1" aria-label="الشريحة السابقة"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        <button type="button" class="ts-banner__nav ts-banner__nav--next" data-dir="1" aria-label="الشريحة التالية"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <div class="ts-banner__dots">
            <button type="button" class="is-active" aria-label="الشريحة 1" aria-current="true"></button>
            <button type="button" aria-label="الشريحة 2"></button>
            <button type="button" aria-label="الشريحة 3"></button>
        </div>
    </div>

    <div class="ts-promo__side">
        <a href="{{ $dealsAnchor }}" class="ts-promo-tile ts-promo-tile--deal">
            <span class="ts-promo-tile__icon" aria-hidden="true"><i class="bi bi-lightning-charge-fill"></i></span>
            <span class="ts-promo-tile__body">
                <strong>عروض اليوم</strong>
                <span>{{ $dealCountText ?? 'خصومات يومية مختارة' }}</span>
            </span>
            <i class="bi bi-arrow-left ts-promo-tile__go" aria-hidden="true"></i>
        </a>
        <a href="#new-arrivals" class="ts-promo-tile ts-promo-tile--new">
            <span class="ts-promo-tile__icon" aria-hidden="true">
                @if ($newestProduct && $newestProduct->primaryImage)
                    <img src="{{ $newestProduct->primaryImage->thumb_url }}" alt="" loading="lazy">
                @else
                    <i class="bi bi-stars"></i>
                @endif
            </span>
            <span class="ts-promo-tile__body">
                <strong>وصل حديثاً</strong>
                <span>أحدث منتجات المتاجر</span>
            </span>
            <i class="bi bi-arrow-left ts-promo-tile__go" aria-hidden="true"></i>
        </a>
    </div>
</section>

{{-- 3) عروض اليوم (فلاش) --}}
@include('home.partials.showcase-placement', ['showcases' => $showcasesBySlot->get('home_top', collect())])

@if ($featuredDailyDeal)
<section id="flash-deals" class="container ts-home-section">
    <div class="ts-flash">
        <div class="ts-flash__bar">
            <div class="ts-flash__title">
                <span class="ts-flash__bolt" aria-hidden="true"><i class="bi bi-lightning-charge-fill"></i></span>
                <div>
                    <h2>عروض اليوم</h2>
                    <p>{{ $dealCountText ? $dealCountText.' · ' : '' }}خصومات مختارة لفترة محدودة</p>
                </div>
            </div>
            <div class="ts-flash__timer">
                <span class="ts-flash__timer-label"><i class="bi bi-clock-history" aria-hidden="true"></i> ينتهي الأحدث خلال</span>
                <span id="dealCountdown" class="ts-flash__clock" role="timer">{{ $featuredDailyDeal->remaining_time }}</span>
            </div>
        </div>

        @if ($activeDeals->count() === 1)
            {{-- عرض واحد: بطاقة مميزة عريضة + منتجات مخفّضة بجانبها بدل صندوق فارغ --}}
            @php
                $spot = $activeDeals->first();
                $spotProduct = $spot->product;
                $spotImage = $spotProduct->primaryImage;
                $spotPct = (int) round($spot->discount_percentage);
                $spotSave = max(0, (int) $spotProduct->price - (int) $spot->discounted_price);
                $spotMax = (int) ($spot->max_quantity ?? 0);
                $spotSold = (int) ($spot->sold_quantity ?? 0);
                $spotFill = $flashFillers->take(4);
            @endphp
            <div class="ts-spot {{ $spotFill->isEmpty() ? 'is-solo' : '' }}">
                <article class="ts-spot__main">
                    <a href="{{ route('products.show', $spotProduct) }}" class="ts-spot__media" aria-label="عرض {{ $spotProduct->name }}">
                        @if ($spotImage)
                            <img src="{{ $spotImage->thumb_url }}" alt="{{ $spotProduct->name }}" loading="lazy">
                        @else
                            <span class="ts-market-card__placeholder" aria-hidden="true"><span class="ts-market-card__initial" translate="no">{{ mb_substr($spotProduct->name, 0, 1) }}</span><i class="bi bi-image"></i></span>
                        @endif
                        @if ($spotPct > 0)<span class="ts-market-badge ts-spot__badge">-{{ $spotPct }}%</span>@endif
                    </a>
                    <div class="ts-spot__info">
                        <span class="ts-market-ribbon"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> عرض اليوم المميز</span>
                        @if ($spotProduct->vendor)
                            <p class="ts-spot__store" translate="no"><i class="bi bi-shop" aria-hidden="true"></i> {{ $spotProduct->vendor->store_name }}</p>
                        @endif
                        <h3 class="ts-spot__name"><a href="{{ route('products.show', $spotProduct) }}" translate="no">{{ $spotProduct->name }}</a></h3>
                        <div class="ts-spot__prices">
                            <strong>{{ $spot->formatted_discounted_price }}</strong>
                            @if ($spotSave > 0)<del>{{ $spotProduct->formatted_price }}</del>@endif
                        </div>
                        @if ($spotSave > 0)
                            <span class="ts-spot__save"><i class="bi bi-piggy-bank" aria-hidden="true"></i> {{ __('وفّر :amount ل.س', ['amount' => number_format($spotSave / 100)]) }}</span>
                        @endif
                        @if ($spotMax > 0)
                            <div class="ts-spot__stock" aria-label="الكمية المتبقية">
                                <span class="ts-spot__bar"><span style="width: {{ min(100, max(4, round($spotSold / $spotMax * 100))) }}%"></span></span>
                                <small>{{ __('تبقّى :left من :max', ['left' => max(0, $spotMax - $spotSold), 'max' => $spotMax]) }}</small>
                            </div>
                        @endif
                        <div class="ts-spot__actions">
                            <form action="{{ route('customer.cart.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $spotProduct->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="ts-spot__buy" @disabled($spotProduct->stock_quantity < 1)>
                                    <i class="bi bi-bag-plus" aria-hidden="true"></i> {{ $spotProduct->stock_quantity < 1 ? 'نفد من المخزون' : 'أضف للسلة' }}
                                </button>
                            </form>
                            <a href="{{ route('products.show', $spotProduct) }}" class="ts-spot__more">التفاصيل <i class="bi bi-arrow-left" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </article>
                @foreach ($spotFill as $fillProduct)
                    <div class="ts-spot__side {{ $loop->iteration === 4 ? 'd-md-none' : '' }}">
                        @include('partials.product-card', ['product' => $fillProduct, 'showDiscountBadge' => true])
                    </div>
                @endforeach
            </div>
        @else
            <div class="ts-rail ts-rail--cards" data-ts-rail>
                <button type="button" class="ts-rail__nav ts-rail__nav--prev" data-dir="-1" aria-label="السابق"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                <div class="ts-rail__track" data-ts-fade>
                    @foreach ($activeDeals as $dealItem)
                        <div class="ts-rail__item">
                            @include('partials.product-card', [
                                'product' => $dealItem->product,
                                'showDiscountBadge' => true,
                                'deal' => $dealItem,
                            ])
                        </div>
                    @endforeach
                    {{-- عروض قليلة: نكمل السكة بمنتجات مخفّضة --}}
                    @foreach ($flashFillers->take(max(0, 6 - $activeDeals->count())) as $fillProduct)
                        <div class="ts-rail__item">
                            @include('partials.product-card', ['product' => $fillProduct, 'showDiscountBadge' => true])
                        </div>
                    @endforeach
                    <a href="#deals" class="ts-rail__item ts-rail__more">
                        <span class="ts-rail__more-icon" aria-hidden="true"><i class="bi bi-tags"></i></span>
                        <strong>عروض جديدة كل يوم</strong>
                        <span>تصفّح المنتجات المخفّضة من كل المتاجر</span>
                        <span class="ts-rail__more-go">المزيد من العروض <i class="bi bi-arrow-left" aria-hidden="true"></i></span>
                    </a>
                </div>
                <button type="button" class="ts-rail__nav ts-rail__nav--next" data-dir="1" aria-label="التالي"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            </div>
        @endif
    </div>
</section>
@endif

{{-- 4) عروض تستحق الاكتشاف --}}
<section id="deals" class="container ts-home-section">
    <div class="ts-sh">
        <div>
            <p class="ts-sh__kicker">أسعار خاصة من المتاجر</p>
            <h2 class="ts-sh__title">عروض تستحق الاكتشاف</h2>
        </div>
        <a href="{{ route('search') }}" class="ts-sh__more">عرض الكل <i class="bi bi-chevron-left" aria-hidden="true"></i></a>
    </div>
    @if ($dealProducts->isNotEmpty())
        <div class="ts-grid ts-grid--4">
            @foreach ($dealProducts as $product)
                @include('partials.product-card', ['product' => $product, 'showDiscountBadge' => true])
            @endforeach
        </div>
    @else
        <div class="ts-section-empty"><i class="bi bi-tag" aria-hidden="true"></i> لا توجد منتجات مخفّضة حالياً.</div>
    @endif
</section>

{{-- 5) وصل حديثاً --}}
@include('home.partials.showcase-placement', ['showcases' => $showcasesBySlot->get('home_mid', collect())])

<section id="new-arrivals" class="container ts-home-section">
    <div class="ts-sh">
        <div>
            <p class="ts-sh__kicker">تعرّف على الجديد</p>
            <h2 class="ts-sh__title">وصل حديثاً</h2>
        </div>
        <a href="{{ route('search', ['sort' => 'newest']) }}" class="ts-sh__more">عرض الكل <i class="bi bi-chevron-left" aria-hidden="true"></i></a>
    </div>
    @if ($newProducts->isNotEmpty())
        <div class="ts-grid ts-grid--dense">
            @foreach ($newProducts as $product)
                @include('partials.product-card', ['product' => $product])
            @endforeach
        </div>
    @else
        <div class="ts-section-empty"><i class="bi bi-box-seam" aria-hidden="true"></i> لا توجد منتجات منشورة بعد.</div>
    @endif
</section>

{{-- 6) شريط الثقة --}}
@include('home.partials.showcase-placement', ['showcases' => $showcasesBySlot->get('home_bottom', collect())])

<section class="container ts-home-section ts-trust-wrap" aria-label="لماذا تريندشام">
    <ul class="ts-trust">
        <li><span class="ts-trust__icon" aria-hidden="true"><i class="bi bi-truck"></i></span><span><strong>توصيل لكل المحافظات</strong><small>من باب المتجر لباب بيتك</small></span></li>
        <li><span class="ts-trust__icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span><span><strong>دفع آمن</strong><small>بياناتك محمية بالكامل</small></span></li>
        <li><span class="ts-trust__icon" aria-hidden="true"><i class="bi bi-arrow-repeat"></i></span><span><strong>إرجاع سهل</strong><small>حسب سياسة الإرجاع</small></span></li>
        <li><span class="ts-trust__icon" aria-hidden="true"><i class="bi bi-patch-check"></i></span><span><strong>بائعون موثّقون</strong><small>متاجر تمت مراجعتها</small></span></li>
    </ul>
</section>
@endsection

@push('styles')
<style>
    /* ===== الشريط العلوي ===== */
    .ts-topbar { background: var(--ts-emerald-dark) var(--ts-lattice) 0 0/40px; color: #EFE7D6; font-size: .8rem; }
    .ts-topbar__row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 32px; }
    .ts-topbar i { color: var(--ts-gold-light); margin-inline-end: .2rem; }
    .ts-topbar__links { display: flex; gap: 1.1rem; }
    .ts-topbar a { color: #EFE7D6; text-decoration: none; }
    .ts-topbar a:hover, .ts-topbar a:focus-visible { color: var(--ts-gold-light); }

    /* حواف متلاشية عند وجود محتوى إضافي للتمرير (يضبطها السكربت) */
    [data-ts-fade].fade-start { -webkit-mask-image: linear-gradient(to left, transparent, #000 40px); mask-image: linear-gradient(to left, transparent, #000 40px); }
    [data-ts-fade].fade-end { -webkit-mask-image: linear-gradient(to right, transparent, #000 40px); mask-image: linear-gradient(to right, transparent, #000 40px); }
    html[dir="ltr"] [data-ts-fade].fade-start { -webkit-mask-image: linear-gradient(to right, transparent, #000 40px); mask-image: linear-gradient(to right, transparent, #000 40px); }
    html[dir="ltr"] [data-ts-fade].fade-end { -webkit-mask-image: linear-gradient(to left, transparent, #000 40px); mask-image: linear-gradient(to left, transparent, #000 40px); }
    [data-ts-fade].fade-start.fade-end { -webkit-mask-image: linear-gradient(to right, transparent, #000 40px, #000 calc(100% - 40px), transparent); mask-image: linear-gradient(to right, transparent, #000 40px, #000 calc(100% - 40px), transparent); }

    /* ===== القصص ===== */
    .ts-stories { display: flex; gap: .85rem; overflow-x: auto; padding: 1rem 0 .25rem; scrollbar-width: none; }
    .ts-stories::-webkit-scrollbar { display: none; }
    .ts-story-item { flex: 0 0 auto; width: 74px; padding: 0; border: 0; background: transparent; color: var(--ts-ink); text-align: center; }
    .ts-story-ring { display: block; width: 66px; height: 66px; margin: 0 auto; padding: 3px; border-radius: 50%; background: conic-gradient(from 210deg, var(--ts-emerald), var(--ts-gold), var(--ts-gold-light), var(--ts-emerald)); transition: transform .2s ease; }
    .ts-story-item.is-platform .ts-story-ring { background: conic-gradient(from 210deg, var(--ts-gold), var(--ts-gold-light), var(--ts-gold)); }
    .ts-story-ring img { display: block; width: 100%; height: 100%; border: 2.5px solid var(--ts-surface); border-radius: 50%; object-fit: cover; background: var(--ts-surface-2); }
    .ts-story-item:hover .ts-story-ring { transform: scale(1.05); }
    .ts-story-name { display: block; margin-top: .3rem; overflow: hidden; font-size: .72rem; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
    .ts-story-item:focus-visible { outline: 2px solid var(--ts-emerald); outline-offset: 3px; border-radius: 8px; }

    /* ===== البانر والبلاطات الجانبية ===== */
    .ts-promo { display: grid; grid-template-columns: minmax(0, 2.55fr) minmax(0, 1fr); gap: 1rem; margin-top: 1.1rem; }
    .ts-banner { position: relative; overflow: hidden; border-radius: 16px; isolation: isolate; }
    .ts-banner__track { display: grid; }
    .ts-slide { grid-area: 1 / 1; display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); align-items: center; gap: 1.5rem; min-height: 340px; padding: 2.25rem 2.75rem 3rem; opacity: 0; visibility: hidden; transition: opacity .6s ease, visibility .6s; }
    .ts-slide.is-active { opacity: 1; visibility: visible; }
    .ts-slide--emerald { color: #fff; background: var(--ts-lattice) 0 0/48px, radial-gradient(circle at 18% 55%, rgba(201,150,44,.32), transparent 42%), linear-gradient(120deg, #0B5F4A 0%, #073D30 80%); }
    .ts-slide--gold { color: var(--ts-emerald-dark); background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 48 48'%3E%3Cg fill='none' stroke='%23073D30' stroke-opacity='.12'%3E%3Cpath d='M24 4 44 24 24 44 4 24Z'/%3E%3Cpath d='M10 10h28v28H10z'/%3E%3Cpath d='M24 0v4M24 44v4M0 24h4M44 24h4'/%3E%3Ccircle cx='24' cy='24' r='5'/%3E%3C/g%3E%3C/svg%3E") 0 0/48px, radial-gradient(circle at 20% 50%, #FFF4D6, transparent 50%), linear-gradient(120deg, #F1D9A0 0%, #D9AE52 70%, #C9962C 100%); }
    .ts-slide--night { color: #fff; background: var(--ts-lattice) 0 0/48px, radial-gradient(circle at 20% 40%, rgba(11,95,74,.8), transparent 55%), linear-gradient(140deg, #0E2A22 0%, #071C16 100%); }
    .ts-slide__eyebrow { display: flex; align-items: center; gap: .5rem; margin: 0 0 .6rem; color: var(--ts-gold-light); font-size: .85rem; font-weight: 700; }
    .ts-slide__eyebrow span { width: 26px; height: 2px; background: var(--ts-gold); }
    .ts-slide--gold .ts-slide__eyebrow { color: var(--ts-emerald-dark); }
    .ts-slide__title { margin: 0; font-family: 'Reem Kufi', sans-serif; font-size: clamp(1.45rem, 3.2vw, 2.65rem); font-weight: 700; line-height: 1.3; }
    .ts-slide__title em { font-style: normal; color: var(--ts-gold-light); }
    .ts-slide--gold .ts-slide__title em { color: #fff; text-shadow: 0 1px 0 rgba(122,84,16,.35); }
    .ts-slide__text { max-width: 440px; margin: .8rem 0 1.3rem; color: rgba(255,255,255,.82); font-size: .98rem; line-height: 1.8; }
    .ts-slide--gold .ts-slide__text { color: rgba(7,61,48,.86); }
    .ts-slide__actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1.1rem; }
    .ts-slide__cta { display: inline-flex; align-items: center; gap: .55rem; padding: .7rem 1.2rem; border-radius: 10px; background: var(--ts-gold); color: var(--ts-emerald-dark); font-weight: 900; text-decoration: none; box-shadow: 0 8px 18px -8px rgba(0,0,0,.45); transition: transform .15s ease, background .15s ease; }
    .ts-slide__cta:hover, .ts-slide__cta:focus-visible { background: #E2B653; color: var(--ts-emerald-dark); transform: translateY(-2px); }
    .ts-slide--gold .ts-slide__cta { background: var(--ts-emerald-dark); color: #fff; }
    .ts-slide--gold .ts-slide__cta:hover, .ts-slide--gold .ts-slide__cta:focus-visible { background: var(--ts-emerald); color: #fff; }
    .ts-slide__link { color: #fff; font-weight: 700; text-decoration: none; border-bottom: 1px solid rgba(241,217,160,.5); padding-bottom: 2px; }
    .ts-slide__link:hover, .ts-slide__link:focus-visible { color: var(--ts-gold-light); }
    .ts-slide__cta:focus-visible, .ts-slide__link:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }
    .ts-slide__visual { display: grid; place-items: center; }

    /* إطار على شكل قوس شامي */
    .ts-arch { position: relative; display: block; width: min(100%, 250px); aspect-ratio: 4 / 5; overflow: hidden; border-radius: 999px 999px 14px 14px; background: #F7F1E4; box-shadow: 0 0 0 6px rgba(241,217,160,.18), 0 0 0 7px rgba(241,217,160,.45), 0 24px 50px -18px rgba(0,0,0,.55); color: var(--ts-ink); text-decoration: none; }
    .ts-arch img { width: 100%; height: 100%; object-fit: contain; padding: 14% 8% 26%; }
    .ts-arch__fallback { display: grid; width: 100%; height: 100%; place-items: center; color: var(--ts-emerald); font-size: 4rem; background: var(--ts-lattice-ink) center/40px; }
    .ts-arch__tag { position: absolute; inset-inline: 10px; bottom: 10px; display: flex; flex-direction: column; gap: 1px; padding: .5rem .7rem; border-radius: 10px; background: var(--ts-emerald-dark); color: #fff; }
    .ts-arch__name { overflow: hidden; font-size: .8rem; text-overflow: ellipsis; white-space: nowrap; opacity: .85; }
    .ts-arch__price { color: var(--ts-gold-light); font-weight: 900; font-variant-numeric: tabular-nums; }

    .ts-medal { position: relative; display: grid; width: min(100%, 220px); aspect-ratio: 1; place-items: center; overflow: visible; border-radius: 50%; background: #FFFBF1; box-shadow: 0 0 0 8px rgba(255,255,255,.35), 0 0 0 9px rgba(7,61,48,.25), 0 24px 50px -20px rgba(7,61,48,.6); color: var(--ts-gold); font-size: 4.5rem; }
    .ts-medal img { width: 72%; height: 72%; object-fit: contain; }
    .ts-medal__off { position: absolute; top: 4%; inset-inline-start: -4%; display: grid; width: 74px; aspect-ratio: 1; place-items: center; border-radius: 50%; background: var(--ts-sale); color: #fff; font-size: 1.15rem; font-weight: 900; direction: ltr; box-shadow: 0 8px 18px -6px rgba(194,59,42,.6); transform: rotate(-10deg); }
    .ts-medal--outline { background: rgba(255,255,255,.04); box-shadow: 0 0 0 1px rgba(241,217,160,.55), 0 0 0 14px rgba(241,217,160,.06), 0 0 0 15px rgba(241,217,160,.3); color: var(--ts-gold-light); font-size: 5rem; }

    .ts-banner__nav { position: absolute; top: 50%; z-index: 3; display: grid; width: 38px; height: 38px; place-items: center; border: 0; border-radius: 50%; background: rgba(255,255,255,.92); color: var(--ts-emerald-dark); box-shadow: 0 4px 14px rgba(0,0,0,.18); transform: translateY(-50%); opacity: 0; transition: opacity .2s ease; }
    .ts-banner__nav--prev { inset-inline-start: .75rem; }
    .ts-banner__nav--next { inset-inline-end: .75rem; }
    .ts-banner:hover .ts-banner__nav, .ts-banner__nav:focus-visible { opacity: 1; }
    .ts-banner__nav:focus-visible { outline: 2px solid var(--ts-gold); outline-offset: 2px; }
    .ts-banner__dots { position: absolute; bottom: 1rem; inset-inline-start: 2.75rem; z-index: 3; display: flex; gap: .35rem; }
    .ts-banner__dots button { width: 8px; height: 8px; padding: 0; border: 0; border-radius: 4px; background: rgba(255,255,255,.5); transition: width .25s ease, background .25s ease; }
    .ts-banner__dots button.is-active { width: 26px; background: var(--ts-gold-light); }
    .ts-banner__dots button:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .ts-banner.on-gold .ts-banner__dots button { background: rgba(7,61,48,.3); }
    .ts-banner.on-gold .ts-banner__dots button.is-active { background: var(--ts-emerald-dark); }

    .ts-promo__side { display: grid; grid-template-rows: 1fr 1fr; gap: 1rem; }
    .ts-promo-tile { position: relative; display: flex; align-items: center; gap: .9rem; padding: 1.1rem 1.2rem; overflow: hidden; border-radius: 16px; text-decoration: none; transition: transform .2s ease, box-shadow .2s ease; }
    .ts-promo-tile:hover, .ts-promo-tile:focus-visible { transform: translateY(-3px); box-shadow: var(--ts-shadow-lift); }
    .ts-promo-tile:focus-visible { outline: 2px solid var(--ts-emerald); outline-offset: 3px; }
    .ts-promo-tile--deal { background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 48 48'%3E%3Cg fill='none' stroke='%23073D30' stroke-opacity='.1'%3E%3Cpath d='M24 4 44 24 24 44 4 24Z'/%3E%3Cpath d='M10 10h28v28H10z'/%3E%3C/g%3E%3C/svg%3E") 0 0/40px, linear-gradient(135deg, #F6E3B4, #D6A846); color: var(--ts-emerald-dark); }
    .ts-promo-tile--new { border: 1px solid var(--ts-border); background: var(--ts-surface); color: var(--ts-ink); }
    .ts-promo-tile__icon { display: grid; flex: 0 0 64px; width: 64px; height: 64px; place-items: center; overflow: hidden; border-radius: 50%; background: var(--ts-emerald-dark); color: var(--ts-gold-light); font-size: 1.6rem; }
    .ts-promo-tile--new .ts-promo-tile__icon { border-radius: 14px; background: var(--ts-media-bg); color: var(--ts-emerald); box-shadow: inset 0 0 0 1px var(--ts-border); }
    .ts-promo-tile__icon img { width: 100%; height: 100%; object-fit: contain; }
    .ts-promo-tile__body { display: flex; flex: 1; flex-direction: column; min-width: 0; gap: .15rem; }
    .ts-promo-tile__body strong { font-family: 'Reem Kufi', sans-serif; font-size: 1.2rem; line-height: 1.3; }
    .ts-promo-tile__body span { font-size: .84rem; opacity: .85; }
    .ts-promo-tile__go { font-size: 1.1rem; transition: transform .2s ease; }
    .ts-promo-tile:hover .ts-promo-tile__go { transform: translateX(-4px); }
    html[dir="ltr"] .ts-promo-tile:hover .ts-promo-tile__go { transform: translateX(4px); }

    /* ===== عناوين الأقسام ===== */
    .ts-home-section { margin-top: clamp(1.75rem, 3.5vw, 2.6rem); }
    .ts-sh { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
    .ts-sh__kicker { margin: 0 0 .15rem; color: var(--ts-deal-ink); font-size: .8rem; font-weight: 700; }
    .ts-sh__title { position: relative; display: flex; align-items: center; gap: .55rem; margin: 0; color: var(--ts-ink); font-size: clamp(1.25rem, 2vw, 1.55rem); font-weight: 700; line-height: 1.3; }
    .ts-sh__title::before { content: ""; flex: 0 0 auto; width: 11px; height: 11px; background: var(--ts-gold); transform: rotate(45deg); box-shadow: 0 0 0 3px rgba(201,150,44,.2); }
    .ts-sh__more { display: inline-flex; align-items: center; gap: .25rem; flex: 0 0 auto; padding: .35rem .2rem; color: var(--ts-emerald); font-size: .88rem; font-weight: 700; text-decoration: none; }
    .ts-sh__more:hover, .ts-sh__more:focus-visible { color: var(--ts-emerald-dark); text-decoration: underline; text-underline-offset: 4px; }

    /* ===== سكك التمرير الأفقي (فقاعات الأقسام + عروض اليوم) ===== */
    .ts-rail { position: relative; }
    .ts-rail__track { display: flex; gap: .85rem; overflow-x: auto; scroll-snap-type: x mandatory; scroll-behavior: smooth; scrollbar-width: none; overscroll-behavior-x: contain; }
    .ts-rail__track::-webkit-scrollbar { display: none; }
    .ts-rail__nav { position: absolute; top: 50%; z-index: 4; display: none; width: 40px; height: 40px; place-items: center; border: 1px solid var(--ts-border); border-radius: 50%; background: var(--ts-surface); color: var(--ts-emerald-dark); box-shadow: 0 6px 16px -6px rgba(0,0,0,.25); transform: translateY(-50%); transition: opacity .2s ease, background .15s ease; }
    .ts-rail__nav:hover { background: var(--ts-emerald); border-color: var(--ts-emerald); color: #fff; }
    .ts-rail__nav:focus-visible { outline: 2px solid var(--ts-gold); outline-offset: 2px; }
    .ts-rail__nav[disabled] { opacity: 0; pointer-events: none; }
    .ts-rail__nav--prev { inset-inline-start: -14px; }
    .ts-rail__nav--next { inset-inline-end: -14px; }
    @media (hover: hover) and (min-width: 992px) { .ts-rail.is-scrollable .ts-rail__nav { display: grid; } }

    /* ===== شريط عروض اليوم ===== */
    .ts-flash { overflow: hidden; border: 1px solid rgba(201,150,44,.45); border-radius: 16px; background: var(--ts-surface); }
    .ts-flash__bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem 1rem; padding: .95rem 1.25rem; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 48 48'%3E%3Cg fill='none' stroke='%23073D30' stroke-opacity='.12'%3E%3Cpath d='M24 4 44 24 24 44 4 24Z'/%3E%3Cpath d='M10 10h28v28H10z'/%3E%3Cpath d='M24 0v4M24 44v4M0 24h4M44 24h4'/%3E%3Ccircle cx='24' cy='24' r='5'/%3E%3C/g%3E%3C/svg%3E") 0 0/44px, linear-gradient(100deg, #F3DDA6, #D9AE52 60%, #C9962C); color: var(--ts-emerald-dark); }
    .ts-flash__title { display: flex; align-items: center; gap: .75rem; }
    .ts-flash__bolt { display: grid; flex: 0 0 auto; width: 46px; height: 46px; place-items: center; border-radius: 50%; background: var(--ts-emerald-dark); color: var(--ts-gold-light); font-size: 1.35rem; box-shadow: 0 0 0 4px rgba(255,255,255,.35); animation: ts-bolt 2.4s ease-in-out infinite; }
    @keyframes ts-bolt { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
    .ts-flash__title h2 { margin: 0; font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
    .ts-flash__title p { margin: .15rem 0 0; font-size: .84rem; font-weight: 500; opacity: .9; }
    .ts-flash__timer { display: flex; align-items: center; gap: .6rem; }
    .ts-flash__timer-label { font-size: .84rem; font-weight: 700; }
    .ts-flash__clock { display: inline-block; min-width: 6.4ch; padding: .4rem .75rem; border-radius: 10px; background: var(--ts-emerald-dark); color: var(--ts-gold-light); font-size: 1.15rem; font-weight: 900; letter-spacing: .06em; text-align: center; direction: ltr; font-variant-numeric: tabular-nums; }
    .ts-flash .ts-rail { padding: 1rem 1.25rem 1.2rem; }
    .ts-flash .ts-rail__nav--prev { inset-inline-start: .35rem; }
    .ts-flash .ts-rail__nav--next { inset-inline-end: .35rem; }
    .ts-rail--cards .ts-rail__track { align-items: stretch; }
    .ts-rail__item { flex: 0 0 210px; scroll-snap-align: start; }
    .ts-rail__more { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .45rem; padding: 1.25rem 1rem; border: 1.5px dashed rgba(201,150,44,.6); border-radius: var(--ts-radius); background: var(--ts-lattice-ink) 0 0/40px, var(--ts-deal-soft); color: var(--ts-emerald-dark); text-align: center; text-decoration: none; transition: border-color .2s ease, background-color .2s ease; }
    .ts-rail__more:hover, .ts-rail__more:focus-visible { border-color: var(--ts-gold); color: var(--ts-emerald-dark); }
    .ts-rail__more-icon { display: grid; width: 56px; height: 56px; place-items: center; border-radius: 50%; background: var(--ts-surface); color: var(--ts-gold); font-size: 1.5rem; box-shadow: 0 0 0 1px rgba(201,150,44,.4); }
    .ts-rail__more strong { font-family: 'Reem Kufi', sans-serif; font-size: 1.1rem; }
    .ts-rail__more > span:not([class]) { color: var(--ts-muted); font-size: .82rem; line-height: 1.6; }
    .ts-rail__more-go { margin-top: .35rem; color: var(--ts-emerald); font-size: .85rem; font-weight: 800; }

    /* عرض واحد: بطاقة مميزة عريضة */
    .ts-spot { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; padding: 1rem 1.25rem 1.2rem; }
    .ts-spot__main { grid-column: 1 / -1; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); overflow: hidden; border: 1px solid rgba(201,150,44,.55); border-radius: var(--ts-radius); background: var(--ts-surface); }
    .ts-spot__media { position: relative; display: block; aspect-ratio: 1 / 1; overflow: hidden; background: var(--ts-media-bg); }
    .ts-spot__media img { width: 100%; height: 100%; object-fit: contain; transition: transform .3s ease; }
    .ts-spot__main:hover .ts-spot__media img { transform: scale(1.03); }
    .ts-spot__badge { position: absolute; top: .6rem; inset-inline-start: .6rem; z-index: 2; font-size: .9rem; }
    .ts-spot__info { display: flex; flex-direction: column; align-items: flex-start; gap: .45rem; min-width: 0; padding: 1.1rem 1.2rem; background: var(--ts-lattice-ink) 0 0/44px, linear-gradient(160deg, var(--ts-surface), var(--ts-deal-soft)); }
    .ts-spot__store { max-width: 100%; margin: .2rem 0 0; overflow: hidden; color: var(--ts-muted); font-size: .78rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .ts-spot__store i { color: var(--ts-gold); }
    .ts-spot__name { display: -webkit-box; margin: 0; overflow: hidden; font-family: 'Reem Kufi', sans-serif; font-size: clamp(1.1rem, 1.6vw, 1.45rem); line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
    .ts-spot__name a { color: var(--ts-ink); text-decoration: none; }
    .ts-spot__name a:hover { color: var(--ts-emerald); }
    .ts-spot__prices { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; margin-top: .15rem; }
    .ts-spot__prices strong { color: var(--ts-emerald); font-size: clamp(1.35rem, 2vw, 1.7rem); font-weight: 900; font-variant-numeric: tabular-nums; }
    .ts-spot__prices del { color: var(--ts-muted); font-size: .9rem; font-variant-numeric: tabular-nums; }
    .ts-spot__save { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .55rem; border-radius: 6px; background: var(--ts-sale); color: #fff; font-size: .78rem; font-weight: 800; }
    .ts-spot__stock { display: flex; flex-direction: column; gap: .25rem; width: 100%; max-width: 280px; }
    .ts-spot__bar { display: block; height: 7px; overflow: hidden; border-radius: 4px; background: rgba(201,150,44,.22); }
    .ts-spot__bar span { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, var(--ts-gold), var(--ts-sale)); }
    .ts-spot__stock small { color: var(--ts-muted); font-size: .75rem; font-weight: 700; }
    .ts-spot__actions { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; margin-top: auto; padding-top: .4rem; }
    .ts-spot__buy { display: inline-flex; align-items: center; gap: .4rem; min-height: 42px; padding: .45rem 1.2rem; border: 0; border-radius: 10px; background: var(--ts-emerald); color: #fff; font-weight: 800; transition: background .15s ease; }
    .ts-spot__buy:hover:not(:disabled) { background: var(--ts-emerald-dark); }
    .ts-spot__buy:disabled { background: var(--ts-surface-2); color: var(--ts-muted); cursor: not-allowed; }
    .ts-spot__buy:focus-visible, .ts-spot__more:focus-visible { outline: 2px solid var(--ts-gold); outline-offset: 2px; }
    .ts-spot__more { color: var(--ts-emerald); font-size: .88rem; font-weight: 800; text-decoration: none; }
    .ts-spot__more:hover { text-decoration: underline; text-underline-offset: 4px; }
    @media (min-width: 768px) { .ts-spot { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; } }
    @media (min-width: 992px) {
        .ts-spot { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .ts-spot__main { grid-column: span 2; grid-template-columns: 1fr; }
        .ts-spot__media { aspect-ratio: 16 / 10; }
        .ts-spot.is-solo { grid-template-columns: 1fr; }
        .ts-spot.is-solo .ts-spot__main { grid-column: 1 / -1; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); }
        .ts-spot.is-solo .ts-spot__media { aspect-ratio: 4 / 3; }
    }
    @media (max-width: 575.98px) {
        .ts-spot { padding: .8rem .9rem .95rem; gap: .6rem; }
        .ts-spot__main { grid-template-columns: 1fr; }
        .ts-spot__media { aspect-ratio: 4 / 3; }
        .ts-spot__info { padding: .9rem; }
    }

    /* ===== شبكات المنتجات ===== */
    .ts-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    @media (min-width: 768px) { .ts-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; } }
    @media (min-width: 992px) { .ts-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (min-width: 1400px) { .ts-grid--dense { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
    @media (min-width: 768px) and (max-width: 991.98px) { .ts-grid--4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ts-section-empty { display: flex; align-items: center; justify-content: center; gap: .65rem; min-height: 140px; padding: 1.5rem; border: 1px dashed var(--ts-border); border-radius: 14px; background: var(--ts-lattice-ink) 0 0/44px, var(--ts-surface); color: var(--ts-muted); text-align: center; }
    .ts-section-empty i { color: var(--ts-gold); font-size: 1.2rem; }

    /* ===== شريط الثقة ===== */
    .ts-trust { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 0; padding: 0; overflow: hidden; border: 1px solid var(--ts-border); border-radius: 16px; background: var(--ts-surface); list-style: none; }
    .ts-trust li { display: flex; align-items: center; gap: .8rem; padding: 1.15rem 1.25rem; }
    .ts-trust li + li { border-inline-start: 1px solid var(--ts-border); }
    .ts-trust__icon { display: grid; flex: 0 0 48px; width: 48px; height: 48px; place-items: center; border-radius: 50%; background: var(--ts-emerald-soft); color: var(--ts-emerald); font-size: 1.35rem; }
    .ts-trust li:nth-child(even) .ts-trust__icon { background: var(--ts-deal-soft); color: var(--ts-deal-ink); }
    .ts-trust strong { display: block; font-size: .95rem; color: var(--ts-ink); }
    .ts-trust small { display: block; margin-top: .1rem; color: var(--ts-muted); font-size: .78rem; }

    /* ===== الاستجابة ===== */
    @media (max-width: 991.98px) {
        .ts-promo { grid-template-columns: 1fr; }
        .ts-promo__side { grid-template-columns: 1fr 1fr; grid-template-rows: none; }
        .ts-slide { min-height: 280px; padding: 1.75rem 1.75rem 2.75rem; }
        .ts-banner__dots { inset-inline-start: 1.75rem; }
        .ts-trust { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ts-trust li:nth-child(3) { border-inline-start: 0; }
        .ts-trust li:nth-child(n+3) { border-top: 1px solid var(--ts-border); }
    }
    @media (max-width: 575.98px) {
        .ts-topbar__row { font-size: .74rem; }
        .ts-topbar__row > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ts-topbar__links { gap: .8rem; flex: 0 0 auto; }
        .ts-promo { gap: .65rem; margin-top: .75rem; }
        .ts-banner { border-radius: 14px; }
        .ts-slide { grid-template-columns: minmax(0, 1fr) 112px; gap: .75rem; min-height: 230px; padding: 1.25rem 1.1rem 2.4rem; }
        .ts-slide__eyebrow { font-size: .74rem; margin-bottom: .4rem; }
        .ts-slide__title { font-size: 1.3rem; }
        .ts-slide__text { display: none; }
        .ts-slide__actions { gap: .75rem; margin-top: .9rem; }
        .ts-slide__cta { padding: .55rem .85rem; font-size: .85rem; }
        .ts-slide__link { font-size: .82rem; }
        .ts-arch { width: 112px; box-shadow: 0 0 0 4px rgba(241,217,160,.18), 0 0 0 5px rgba(241,217,160,.45), 0 16px 30px -14px rgba(0,0,0,.55); }
        .ts-arch img { padding: 14% 8% 10%; }
        .ts-arch__tag { display: none; }
        .ts-medal { width: 104px; font-size: 2.6rem; }
        .ts-medal__off { width: 46px; font-size: .78rem; }
        .ts-medal--outline { font-size: 2.8rem; }
        .ts-banner__dots { bottom: .8rem; inset-inline-start: 1.1rem; }
        .ts-promo__side { gap: .65rem; }
        .ts-promo-tile { flex-direction: column; align-items: flex-start; gap: .55rem; padding: .85rem; border-radius: 14px; }
        .ts-promo-tile__icon { flex-basis: 44px; width: 44px; height: 44px; font-size: 1.15rem; }
        .ts-promo-tile__body strong { font-size: 1rem; }
        .ts-promo-tile__body span { font-size: .76rem; }
        .ts-promo-tile__go { position: absolute; top: .9rem; inset-inline-end: .9rem; }
        .ts-sh { align-items: center; }
        .ts-flash { border-radius: 14px; }
        .ts-flash__bar { padding: .8rem .9rem; }
        .ts-flash__bolt { width: 38px; height: 38px; font-size: 1.1rem; }
        .ts-flash__title h2 { font-size: 1.15rem; }
        .ts-flash__timer { width: 100%; justify-content: space-between; padding-top: .55rem; border-top: 1px solid rgba(7,61,48,.15); }
        .ts-flash__clock { font-size: 1rem; padding: .3rem .6rem; }
        .ts-flash .ts-rail { padding: .8rem .9rem .95rem; }
        .ts-rail__track { gap: .6rem; }
        .ts-rail__item { flex-basis: 44vw; max-width: 180px; }
        .ts-trust li { flex-direction: column; align-items: flex-start; gap: .5rem; padding: .9rem; }
        .ts-trust__icon { flex-basis: 40px; width: 40px; height: 40px; font-size: 1.1rem; }
        .ts-trust strong { font-size: .86rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .ts-slide, .ts-promo-tile, .ts-story-ring { transition: none; }
        .ts-flash__bolt { animation: none; }
        .ts-rail__track { scroll-behavior: auto; }
        .ts-promo-tile:hover, .ts-slide__cta:hover { transform: none; }
    }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const storyItems = Array.from(document.querySelectorAll('[data-story-image]'));
    const modalElement = document.getElementById('story-modal');
    if (!modalElement) return;
    const progressBars = Array.from(modalElement.querySelectorAll('.story-progress'));
    let currentStoryIndex = 0;
    let storyTimer;

    function showStory(index) {
        const item = storyItems[index];
        if (!item) {
            bootstrap.Modal.getOrCreateInstance(modalElement).hide();
            return;
        }

        currentStoryIndex = index;
        document.getElementById('story-modal-img').src = item.dataset.storyImage;
        document.getElementById('story-modal-caption').textContent = item.dataset.storyCaption || '';
        document.getElementById('story-modal-vendor').textContent = item.dataset.storyVendor || '';
        document.getElementById('story-modal-badge').classList.toggle('d-none', item.dataset.storyPlatform !== 'true');
        progressBars.forEach((bar, barIndex) => {
            bar.style.transition = 'none';
            bar.style.width = barIndex < index ? '100%' : '0';
        });

        clearTimeout(storyTimer);
        requestAnimationFrame(() => {
            const progressBar = progressBars[index];
            if (progressBar) {
                progressBar.style.transition = 'width 10s linear';
                progressBar.style.width = '100%';
            }
        });
        storyTimer = setTimeout(() => showStory(index + 1), 10000);
    }

    window.openStory = (index) => showStory(index);
    modalElement.addEventListener('hidden.bs.modal', () => clearTimeout(storyTimer));
    modalElement.addEventListener('shown.bs.modal', () => showStory(currentStoryIndex));
})();

(() => {
    const countdownEl = document.getElementById('dealCountdown');
    if (!countdownEl) return;
    let remaining = parseInt(countdownEl.textContent, 10);
    if (remaining <= 0) {
        countdownEl.textContent = @json(__('انتهى'));
        return;
    }
    const update = () => {
        remaining--;
        const h = Math.floor(remaining / 3600);
        const m = Math.floor((remaining % 3600) / 60);
        const s = remaining % 60;
        countdownEl.textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        if (remaining <= 0) {
            clearInterval(timer);
            countdownEl.textContent = @json(__('انتهى العرض'));
            countdownEl.classList.add('text-danger');
        }
    };
    const timer = setInterval(update, 1000);
    update();
})();

// البانر الترويجي: تبديل تلقائي كل 6 ثوانٍ، يتوقف عند المرور أو التركيز، ويحترم تقليل الحركة
(() => {
    const banner = document.querySelector('[data-ts-banner]');
    if (!banner) return;
    const slides = Array.from(banner.querySelectorAll('.ts-slide'));
    const dots = Array.from(banner.querySelectorAll('.ts-banner__dots button'));
    if (slides.length < 2) return;
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let current = 0, timer = null;

    const show = (n) => {
        current = (n + slides.length) % slides.length;
        slides.forEach((s, i) => {
            const active = i === current;
            s.classList.toggle('is-active', active);
            s.setAttribute('aria-hidden', active ? 'false' : 'true');
            s.querySelectorAll('a').forEach((a) => { if (active) a.removeAttribute('tabindex'); else a.setAttribute('tabindex', '-1'); });
        });
        dots.forEach((d, i) => {
            d.classList.toggle('is-active', i === current);
            if (i === current) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current');
        });
        banner.classList.toggle('on-gold', slides[current].classList.contains('ts-slide--gold'));
    };
    const start = () => { if (!reduce && !timer) timer = setInterval(() => show(current + 1), 6000); };
    const stop = () => { clearInterval(timer); timer = null; };

    banner.querySelectorAll('.ts-banner__nav').forEach((btn) => btn.addEventListener('click', () => { show(current + Number(btn.dataset.dir)); stop(); start(); }));
    dots.forEach((d, i) => d.addEventListener('click', () => { show(i); stop(); start(); }));
    banner.addEventListener('mouseenter', stop);
    banner.addEventListener('mouseleave', start);
    banner.addEventListener('focusin', stop);
    banner.addEventListener('focusout', start);

    // سحب باللمس على الجوال
    let x0 = null;
    banner.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; stop(); }, { passive: true });
    banner.addEventListener('touchend', (e) => {
        if (x0 === null) return;
        const dx = e.changedTouches[0].clientX - x0;
        if (Math.abs(dx) > 40) show(current + (dx > 0 ? 1 : -1)); // RTL: السحب لليمين = التالي
        x0 = null; start();
    });

    show(0);
    start();
})();

// السكك الأفقية: أزرار التمرير + الحواف المتلاشية (RTL: scrollLeft سالب أو صفر)
(() => {
    const sync = (el) => {
        const max = el.scrollWidth - el.clientWidth;
        const pos = Math.abs(el.scrollLeft);
        const canStart = pos > 2, canEnd = pos < max - 2;
        el.classList.toggle('fade-start', canStart);
        el.classList.toggle('fade-end', max > 2 && canEnd);
        const rail = el.closest('[data-ts-rail]');
        if (rail) {
            rail.classList.toggle('is-scrollable', max > 2);
            const prev = rail.querySelector('.ts-rail__nav--prev'), next = rail.querySelector('.ts-rail__nav--next');
            if (prev) prev.disabled = !canStart;
            if (next) next.disabled = !canEnd;
        }
    };
    const els = Array.from(document.querySelectorAll('[data-ts-fade]'));
    els.forEach((el) => {
        sync(el);
        el.addEventListener('scroll', () => sync(el), { passive: true });
    });
    window.addEventListener('resize', () => els.forEach(sync));
    document.querySelectorAll('[data-ts-rail]').forEach((rail) => {
        const track = rail.querySelector('.ts-rail__track');
        rail.querySelectorAll('.ts-rail__nav').forEach((btn) => btn.addEventListener('click', () => {
            // التقدّم للأمام: قيمة سالبة في RTL وموجبة في LTR
            const sign = getComputedStyle(track).direction === 'rtl' ? -1 : 1;
            track.scrollBy({ left: sign * Number(btn.dataset.dir) * track.clientWidth * 0.8 });
        }));
    });
})();

</script>
@endpush
