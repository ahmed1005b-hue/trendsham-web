@extends('layouts.app')

@section('title', __('categories.'.$category->name).' - تريندشام')

@section('content')
<div class="container ts-browse-page">
    <header class="ts-browse-heading">
        <div>
            <p class="ts-browse-kicker"><i class="bi bi-grid" aria-hidden="true"></i> تصفّح حسب الفئة</p>
            <h1 translate="no">{{ __('categories.'.$category->name) }}</h1>
            <p class="ts-browse-summary">{{ number_format($products->total()) }} منتج ضمن هذا القسم</p>
        </div>
        <a href="{{ route('home') }}" class="ts-browse-back">
            <i class="bi bi-arrow-return-right" aria-hidden="true"></i> كل الأقسام
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
                    <span class="ts-browse-empty__icon"><i class="bi bi-grid" aria-hidden="true"></i></span>
                    <h2>القسم هاد فاضي حالياً</h2>
                    <p>ما في منتجات مطابقة للفلاتر. جرّب إزالة بعضها أو استكشف قسماً آخر.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary">اكتشف أقساماً أخرى</a>
                </div>
            </div>
        @endforelse
    </div>

    @if ($products->hasPages())
        <nav class="mt-4" aria-label="صفحات المنتجات">{{ $products->links() }}</nav>
    @endif
</div>
@endsection
