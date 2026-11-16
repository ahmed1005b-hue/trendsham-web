@extends('layouts.app')

@section('title', 'الستوري - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الستوري</span>
        <div>
            <a href="{{ route('seller.stories.create') }}" class="btn btn-sm" style="background:var(--ts-gold); color:var(--ts-emerald-dark); font-weight:700;">+ ستوري جديدة</a>
            <a href="{{ route('seller.dashboard') }}" class="btn btn-outline-secondary btn-sm">لوحة المتجر</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted small">رصيد محفظتك</span>
                <div class="h5 mb-0">{{ $wallet->formatted_balance }}</div>
            </div>
            <div class="text-end">
                <span class="text-muted small">رسم نشر ستوري واحدة (24 ساعة)</span>
                <div class="h5 mb-0">{{ number_format($fee / 100) }} ل.س</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @forelse ($stories as $story)
            <div class="col-6 col-md-3">
                <div class="card h-100">
                    <img src="{{ $story->image_thumb_url }}" alt="صورة القصة" loading="lazy" class="card-img-top" style="aspect-ratio:9/16; object-fit:cover;">
                    <div class="card-body p-2">
                        @if ($story->caption)
                            <p class="small mb-1 text-truncate" translate="no">{{ $story->caption }}</p>
                        @endif
                        @if ($story->is_active && ! $story->isExpired())
                            <span class="badge bg-success">نشطة - تنتهي {{ $story->expires_at->diffForHumans() }}</span>
                        @else
                            <span class="badge bg-secondary">منتهية</span>
                        @endif
                        @if ($story->is_active && ! $story->isExpired())
                            <form action="{{ route('seller.stories.destroy', $story) }}" method="POST" class="mt-2" data-confirm="إخفاء هذه الستوري؟">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger w-100">إخفاء</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center text-muted py-5">لسا ما نشرت أي ستوري.</div>
        @endforelse
    </div>
</div>
@endsection
