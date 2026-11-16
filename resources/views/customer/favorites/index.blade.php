@extends('layouts.app')

@section('title', 'المفضلة - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - المفضلة</span>
        <a href="{{ route('customer.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحتي</a>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        @forelse ($favorites as $favorite)
            <div class="col-6 col-md-3 col-lg-2">
                <div class="card h-100">
                    <a href="{{ route('products.show', $favorite->product) }}" class="text-decoration-none text-reset">
                        <div style="aspect-ratio:1/1; background:#F3EEE1; border-radius:12px 12px 0 0; overflow:hidden;">
                            @if ($favorite->product->primaryImage)
                                <img src="{{ $favorite->product->primaryImage->thumb_url }}" alt="{{ $favorite->product->name }}" loading="lazy" class="w-100 h-100" style="object-fit:cover;">
                            @endif
                        </div>
                        <div class="p-2">
                            <p class="small mb-1 text-truncate">{{ $favorite->product->name }}</p>
                            <span class="fw-bold" style="color: var(--ts-emerald-dark);">{{ $favorite->product->formatted_price }}</span>
                        </div>
                    </a>
                    <form action="{{ route('customer.favorites.toggle', $favorite->product) }}" method="POST" class="p-2 pt-0">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger w-100">إزالة</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-5">لا يوجد منتجات مفضّلة بعد.</div>
        @endforelse
    </div>
</div>
@endsection
