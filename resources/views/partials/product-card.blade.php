{{--
    بطاقة المنتج الموحّدة (الرئيسية، البحث، الأقسام).
    المعاملات: product (مطلوب)، deal، showDiscountBadge، shippingLabel، couponLabel — كلها اختيارية كما كانت.
--}}
@php
    $deal = $deal ?? ($product->relationLoaded('activeDailyDeal') ? $product->activeDailyDeal : null);
    $basePrice = $deal ? $deal->discounted_price : $product->price;
    $oldPrice = $deal ? $product->price : $product->compare_at_price;
    $discountPct = $deal ? (int) round($deal->discount_percentage) : $product->discount_percentage;
    $hasDiscount = ($oldPrice && $oldPrice > $basePrice) || $deal;
    $brand = $product->vendor?->store_name;
    $reviewsCount = (int) ($product->reviews_count ?? 0);
    $rating = $product->average_rating;
    $shippingLabel = $shippingLabel ?? null;
    $couponLabel = $couponLabel ?? null;
    $fmt = fn ($minor) => number_format((int) $minor / 100).' ل.س';
    $cardImages = $product->relationLoaded('images') ? $product->images->take(5) : collect();
    if ($cardImages->isEmpty() && $product->primaryImage) $cardImages = collect([$product->primaryImage]);
    $outOfStock = $product->stock_quantity < 1;
    $initial = mb_substr(trim((string) $product->name), 0, 1);
    $showDiscount = ($hasDiscount && $discountPct) || $deal;
    $isNew = ! $showDiscount && $product->isNew();
@endphp

<article class="ts-market-card h-100 {{ $deal ? 'is-deal' : '' }}">
    <div class="ts-market-card__media-wrap">
        <a href="{{ route('products.show', $product) }}" class="ts-market-card__media-link"
           aria-label="{{ __('عرض :name', ['name' => $product->name]) }}">
            <div class="ts-market-card__media ts-card-slider {{ $cardImages->count() > 1 ? 'js-card-slider' : '' }}" @if($cardImages->count() > 1) data-interval="2200" @endif>
                @forelse ($cardImages as $img)
                    <img src="{{ $img->thumb_url }}"
                         alt="{{ $product->name }}" loading="lazy" decoding="async"
                         class="ts-card-slide {{ $loop->first ? 'is-active' : '' }}">
                @empty
                    <span class="ts-market-card__placeholder" aria-hidden="true">
                        <span class="ts-market-card__initial" translate="no">{{ $initial }}</span>
                        <i class="bi bi-image"></i>
                    </span>
                @endforelse
            </div>
        </a>

        {{-- شارة الخصم تظهر دائماً في الزاوية عند وجود خصم (showDiscountBadge مقبول للتوافق فقط) --}}
        @if ($showDiscount || $isNew)
            <div class="ts-market-card__flags">
                @if ($hasDiscount && $discountPct)
                    <span class="ts-market-badge">-{{ $discountPct }}%</span>
                @endif
                @if ($deal)
                    <span class="ts-market-ribbon"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> عرض اليوم</span>
                @endif
                @if ($isNew)
                    <span class="ts-market-badge ts-market-badge--new">{{ __('جديد') }}</span>
                @endif
            </div>
        @endif

        @if ($cardImages->count() > 1)
            <button type="button" class="ts-card-nav ts-card-nav--prev" data-dir="-1" aria-label="الصورة السابقة"><i class="bi bi-chevron-right"></i></button>
            <button type="button" class="ts-card-nav ts-card-nav--next" data-dir="1" aria-label="الصورة التالية"><i class="bi bi-chevron-left"></i></button>
            <div class="ts-card-dots" aria-hidden="true">@foreach ($cardImages as $img)<span class="{{ $loop->first ? 'is-active' : '' }}"></span>@endforeach</div>
        @endif

        @auth
            @if (auth()->user()->role === 'customer')
                <form action="{{ route('customer.favorites.toggle', $product) }}" method="POST" class="ts-market-card__fav">
                    @csrf
                    <button type="submit" class="ts-market-icon-btn {{ auth()->user()->hasFavorited($product->id) ? 'is-active' : '' }}" aria-label="{{ auth()->user()->hasFavorited($product->id) ? __('إزالة :name من المفضلة', ['name' => $product->name]) : __('إضافة :name إلى المفضلة', ['name' => $product->name]) }}">
                        <i class="bi {{ auth()->user()->hasFavorited($product->id) ? 'bi-heart-fill text-danger' : 'bi-heart' }}" aria-hidden="true"></i>
                    </button>
                </form>
            @endif
        @else
            <a href="{{ route('login') }}" class="ts-market-card__fav ts-market-icon-btn" aria-label="{{ __('سجّل الدخول لإضافة :name للمفضلة', ['name' => $product->name]) }}"><i class="bi bi-heart" aria-hidden="true"></i></a>
        @endauth
        <button type="button" class="ts-market-icon-btn ts-market-card__quickview" data-ts-quick-view="{{ $product->id }}" aria-label="{{ __('معاينة سريعة لـ :name', ['name' => $product->name]) }}" title="معاينة سريعة"><i class="bi bi-eye" aria-hidden="true"></i></button>
    </div>

    <div class="ts-market-card__body">
        <div class="ts-market-card__brand {{ $brand ? '' : 'is-empty' }}" translate="no">
            @if ($brand)<i class="bi bi-shop" aria-hidden="true"></i> {{ $brand }}@else&nbsp;@endif
        </div>
        <a href="{{ route('products.show', $product) }}" class="ts-market-card__title-link">
            <h3 class="ts-market-card__title" translate="no">{{ $product->name }}</h3>
        </a>
        <div class="ts-market-card__rating-row" aria-label="{{ $rating !== null ? __('التقييم :rating من 5، :count مراجعة', ['rating' => number_format($rating, 1), 'count' => $reviewsCount]) : __('لا توجد مراجعات') }}">
            @if ($rating !== null)
                <span class="ts-market-card__stars" aria-hidden="true">
                    @for ($s = 1; $s <= 5; $s++)
                        <i class="bi {{ $rating >= $s ? 'bi-star-fill' : ($rating >= $s - 0.5 ? 'bi-star-half' : 'bi-star') }}"></i>
                    @endfor
                </span>
                <span class="ts-market-card__score">{{ number_format($rating, 1) }}</span>
                <span class="ts-market-card__reviews">({{ number_format($reviewsCount) }})</span>
            @else
                <span class="ts-market-card__reviews">لا توجد مراجعات</span>
            @endif
        </div>

        @if (($deal && (int) ($deal->sold_quantity ?? 0) > 0) || $couponLabel || $shippingLabel)
            <div class="ts-market-card__offers">
                @if ($deal && (int) ($deal->sold_quantity ?? 0) > 0)<span class="ts-market-chip ts-market-chip--coupon"><i class="bi bi-fire" aria-hidden="true"></i> {{ __(':n بيعت', ['n' => (int) $deal->sold_quantity]) }}</span>@endif
                @if ($couponLabel)<span class="ts-market-chip ts-market-chip--coupon"><i class="bi bi-ticket-perforated" aria-hidden="true"></i> {{ $couponLabel }}</span>@endif
                @if ($shippingLabel)<span class="ts-market-chip ts-market-chip--ship"><i class="bi bi-truck" aria-hidden="true"></i> {{ $shippingLabel }}</span>@endif
            </div>
        @endif

        <div class="ts-market-card__buy">
            <div class="ts-market-card__price-row">
                <span class="ts-market-card__price">{{ $fmt($basePrice) }}</span>
                @if ($hasDiscount && $oldPrice > $basePrice)
                    <del class="ts-market-card__old">{{ $fmt($oldPrice) }}</del>
                @endif
            </div>
            <div class="ts-market-card__actions">
                <form action="{{ route('customer.cart.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="ts-market-cart-btn" @disabled($outOfStock)
                            title="{{ $outOfStock ? __('نفد من المخزون') : __('أضف للسلة') }}">
                        <i class="bi {{ $outOfStock ? 'bi-x-lg' : 'bi-plus-lg' }}" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ $outOfStock ? __('نفد من المخزون') : __('أضف :name إلى السلة', ['name' => $product->name]) }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</article>
