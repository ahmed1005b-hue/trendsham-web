@extends('layouts.app')

@section('title', $query ? 'نتائج البحث عن '.$query.' - تريندشام' : 'كل المنتجات - تريندشام')

@section('content')
<div class="container ts-browse-page">
    <header class="ts-browse-heading">
        <div>
            <p class="ts-browse-kicker"><i class="bi bi-search" aria-hidden="true"></i> ابحث واكتشف</p>
            <h1>
                @if ($query)
                    نتائج البحث عن <span class="ts-browse-query">«{{ $query }}»</span>
                @else
                    كل المنتجات
                @endif
            </h1>
            <p class="ts-browse-summary">
                {{ number_format($products->total()) }} منتج{{ $products->total() === 1 ? '' : 'اً' }} متاح
            </p>
        </div>
        <a href="{{ route('home') }}" class="ts-browse-back">
            <i class="bi bi-arrow-return-right" aria-hidden="true"></i> إلى الرئيسية
        </a>
    </header>

    @include('partials.product-filter-bar')

    <div class="row g-3 g-lg-4 ts-product-grid">
        @forelse ($products as $product)
            <div class="col-6 col-md-4 col-xl-3">
                @include('partials.product-card', ['product' => $product])
            </div>
        @empty
            <div class="col-12">
                <div class="ts-browse-empty">
                    <span class="ts-browse-empty__icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <h2>ما لقينا منتجات بهالمواصفات</h2>
                    <p>
                        @if ($query)
                            جرّب كلمة بحث ثانية أو خفّف الفلاتر لتوسّع النتائج.
                        @else
                            ما في منتجات منشورة حالياً. ارجع بعد شوي أو تصفّح الأقسام.
                        @endif
                    </p>
                    <a href="{{ route('home') }}" class="btn btn-primary">تصفّح الأقسام</a>
                </div>
            </div>
        @endforelse
    </div>

    @if ($products->hasPages())
        <nav class="mt-4" aria-label="صفحات النتائج">{{ $products->links('pagination::bootstrap-5') }}</nav>
    @endif
</div>
@endsection
