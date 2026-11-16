@php
    $sortOptions = [
        'newest' => 'الأحدث',
        'price_asc' => 'السعر: من الأقل للأعلى',
        'price_desc' => 'السعر: من الأعلى للأقل',
        'rating' => 'الأعلى تقييماً',
    ];
@endphp

<form method="GET" class="ts-filter-bar mb-4" role="search" aria-label="تصفية المنتجات">
    @if (isset($query))
        <input type="hidden" name="q" value="{{ $query }}">
    @endif

    <div class="ts-filter-bar__field ts-filter-bar__sort">
        <label for="product-sort" class="form-label small mb-1">ترتيب حسب</label>
        <select id="product-sort" name="sort" class="form-select">
            @foreach ($sortOptions as $value => $label)
                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="ts-filter-bar__field">
        <label for="min-price" class="form-label small mb-1">السعر من (ل.س)</label>
        <input id="min-price" type="number" name="min_price" class="form-control"
               value="{{ $minPrice }}" min="0" step="any" inputmode="decimal">
    </div>

    <div class="ts-filter-bar__field">
        <label for="max-price" class="form-label small mb-1">السعر إلى (ل.س)</label>
        <input id="max-price" type="number" name="max_price" class="form-control"
               value="{{ $maxPrice }}" min="0" step="any" inputmode="decimal">
    </div>

    <button type="submit" class="btn btn-primary ts-filter-bar__submit">
        <i class="bi bi-sliders2" aria-hidden="true"></i> تطبيق
    </button>

    @if ($sort !== 'newest' || $minPrice || $maxPrice)
        <a href="{{ url()->current() }}{{ isset($query) && $query ? '?q='.urlencode($query) : '' }}"
           class="ts-filter-bar__reset">إزالة الفلاتر</a>
    @endif
</form>
