{{--
    بطاقة البائع في صفحة المنتج: شعار المتجر (بحلقة متحركة إن كانت له قصة الآن، والضغط يفتح القصة)،
    واسمه مع شارة التوثيق، وزر زيارة المتجر.
    المتغيرات: $vendor (SellerProfile)، $stories (قصصه الظاهرة، قد تكون فارغة).
--}}
@php
    $hasStory = ($stories ?? collect())->isNotEmpty();
    $storeUrl = route('stores.show', $vendor);
    $initial = mb_substr(trim((string) $vendor->store_name), 0, 1);
@endphp
<div class="ts-seller-box">
    @if ($hasStory)
        <button type="button" class="ts-seller-box__avatar has-story" data-ts-story="0"
                aria-label="{{ __('مشاهدة قصة :name', ['name' => $vendor->store_name]) }}">
    @else
        <a href="{{ $storeUrl }}" class="ts-seller-box__avatar" aria-label="{{ __('زيارة متجر :store', ['store' => $vendor->store_name]) }}">
    @endif
            <span class="ts-seller-box__ring">
                @if ($vendor->store_logo)
                    <img src="{{ asset('storage/'.$vendor->store_logo) }}" alt="" loading="lazy">
                @else
                    <span class="ts-seller-box__initial" aria-hidden="true" translate="no">{{ $initial }}</span>
                @endif
            </span>
            @if ($hasStory)
                <span class="ts-seller-box__live">{{ __('قصة') }}</span>
            @endif
    @if ($hasStory)
        </button>
    @else
        </a>
    @endif

    <div class="ts-seller-box__info">
        <span class="ts-seller-box__label">{{ __('يبيعه') }}</span>
        <a href="{{ $storeUrl }}" class="ts-seller-box__name">
            <span translate="no">{{ $vendor->store_name }}</span>
            @if ($vendor->isIdentityVerified())
                <i class="bi bi-patch-check-fill" title="{{ __('متجر موثّق') }}" aria-label="{{ __('متجر موثّق') }}"></i>
            @endif
        </a>
        @if ($hasStory)
            <span class="ts-seller-box__hint">{{ __('اضغط الشعار لمشاهدة قصته') }}</span>
        @endif
    </div>

    <a href="{{ $storeUrl }}" class="ts-seller-box__visit">
        <i class="bi bi-shop" aria-hidden="true"></i> {{ __('زيارة المتجر') }}
    </a>
</div>
