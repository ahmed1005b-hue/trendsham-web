@php
    $hasDiscount = $product->compare_at_price && $product->compare_at_price > $product->price;
    $brand = $product->vendor?->store_name ?? $store->store_name ?? null;
    $reviewsCount = (int) ($product->reviews_count ?? 0);
    $rating = $product->average_rating;
    $cardImages = $product->relationLoaded('images') ? $product->images->take(5) : collect();
    if ($cardImages->isEmpty() && $product->primaryImage) $cardImages = collect([$product->primaryImage]);
@endphp
<article class="ts-market-card ts-store-product h-100" data-product-id="{{ $product->id }}"
         data-name="{{ $product->name }}" data-price="{{ $product->price / 100 }}"
         data-rating="{{ $product->average_rating ?? '' }}" data-stock="{{ $product->stock_quantity }}"
         data-category="{{ $product->category?->name ?? 'غير مصنف' }}"
         data-url="{{ route('products.show', $product) }}">
    <div class="ts-market-card__media-wrap">
        <button type="button" class="ts-product-zoom ts-market-card__media-link border-0 p-0 w-100 bg-transparent" data-bs-toggle="modal"
                data-bs-target="#storeImageModal" data-image="{{ $product->primaryImage ? asset('storage/'.$product->primaryImage->path) : '' }}"
                aria-label="{{ __('تكبير صورة :name', ['name' => $product->name]) }}">
            <span class="ts-market-card__media ts-card-slider {{ $cardImages->count() > 1 ? 'js-card-slider' : '' }}" @if($cardImages->count() > 1) data-interval="2200" @endif>
                @forelse ($cardImages as $img)
                    <img src="{{ $img->thumb_url }}" alt="{{ $product->name }}" loading="lazy" decoding="async" class="ts-card-slide {{ $loop->first ? 'is-active' : '' }}">
                @empty
                    <span class="ts-market-card__placeholder"><i class="bi bi-image" aria-hidden="true"></i></span>
                @endforelse
            </span>
        </button>
        @if ($hasDiscount && $product->discount_percentage)
            <span class="ts-market-badge">-{{ $product->discount_percentage }}%</span>
        @endif
        @if ($cardImages->count() > 1)
            <button type="button" class="ts-card-nav ts-card-nav--prev" data-dir="-1" aria-label="الصورة السابقة"><i class="bi bi-chevron-right"></i></button>
            <button type="button" class="ts-card-nav ts-card-nav--next" data-dir="1" aria-label="الصورة التالية"><i class="bi bi-chevron-left"></i></button>
            <div class="ts-card-dots" aria-hidden="true">@foreach ($cardImages as $img)<span class="{{ $loop->first ? 'is-active' : '' }}"></span>@endforeach</div>
        @endif
        <button type="button" class="ts-market-icon-btn ts-market-card__quickview" data-ts-quick-view="{{ $product->id }}" aria-label="{{ __('معاينة سريعة لـ :name', ['name' => $product->name]) }}" title="معاينة سريعة"><i class="bi bi-search"></i></button>
        @auth
            @if (auth()->user()->role === 'customer')
                <form action="{{ route('customer.favorites.toggle', $product) }}" method="POST" class="ts-market-card__fav">
                    @csrf
                    <button type="submit" class="ts-market-icon-btn" aria-label="{{ auth()->user()->hasFavorited($product->id) ? __('إزالة :name من المفضلة', ['name' => $product->name]) : __('إضافة :name إلى المفضلة', ['name' => $product->name]) }}">
                        <i class="bi {{ auth()->user()->hasFavorited($product->id) ? 'bi-heart-fill text-danger' : 'bi-heart' }}" aria-hidden="true"></i>
                    </button>
                </form>
            @endif
        @else
            <a href="{{ route('login') }}" class="ts-market-card__fav ts-market-icon-btn" aria-label="{{ __('سجّل الدخول لإضافة :name للمفضلة', ['name' => $product->name]) }}"><i class="bi bi-heart" aria-hidden="true"></i></a>
        @endauth
        <label class="ts-market-card__compare">
            <input type="checkbox" class="form-check-input ts-compare-check" aria-label="{{ __('قارن :name', ['name' => $product->name]) }}">
            <span>قارن</span>
        </label>
    </div>
    <div class="ts-market-card__body">
        @if ($brand)
            <div class="ts-market-card__brand" translate="no">{{ $brand }}</div>
        @endif
        <a href="{{ route('products.show', $product) }}" class="ts-market-card__title-link">
            <h3 class="ts-market-card__title" translate="no">{{ $product->name }}</h3>
        </a>
        <div class="ts-market-card__rating-row" aria-label="{{ $rating !== null ? __('التقييم :rating من 5، :count مراجعة', ['rating' => number_format($rating, 1), 'count' => $reviewsCount]) : __('لا توجد مراجعات') }}">
            @if ($rating !== null)
                <span class="ts-market-card__stars"><i class="bi bi-star-fill" aria-hidden="true"></i> {{ number_format($rating, 1) }}</span>
                <span class="ts-market-card__reviews">({{ number_format($reviewsCount) }})</span>
            @else
                <span class="ts-market-card__reviews">لا توجد مراجعات</span>
            @endif
        </div>
        <div class="ts-market-card__meta">
            <span class="{{ $product->stock_quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $product->stock_quantity > 0 ? 'متوفر' : 'نفد' }}</span>
        </div>
        <div class="ts-market-card__price-row">
            <span class="ts-market-card__price">{{ $product->formatted_price }}</span>
            @if ($hasDiscount)
                <del class="ts-market-card__old">{{ $product->formatted_compare_at_price }}</del>
                @if ($product->discount_percentage)
                    <span class="ts-market-card__off">-{{ $product->discount_percentage }}%</span>
                @endif
            @endif
        </div>
        <div class="ts-market-card__actions">
            <form action="{{ route('customer.cart.store') }}" method="POST" class="flex-fill">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="ts-market-cart-btn w-100" @disabled($product->stock_quantity < 1)>
                    <i class="bi bi-cart-plus" aria-hidden="true"></i> {{ $product->stock_quantity < 1 ? 'نفد' : 'أضف للسلة' }}
                </button>
            </form>
        </div>
    </div>
</article>
