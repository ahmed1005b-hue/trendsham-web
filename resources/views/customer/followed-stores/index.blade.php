@extends('layouts.customer-account')
@php($accountTitle = 'المتاجر التي أتابعها')

@section('account_content')
    <div class="ts-account-card">
        <div class="ts-account-head">
            <h1>المتاجر التي أتابعها</h1>
            <span class="small text-muted">{{ $stores->total() }} متجر</span>
        </div>

        @if ($stores->isEmpty())
            <div class="text-center text-muted py-5 border rounded-2" style="border-color:var(--ts-soft-border, #ededed) !important">
                <i class="bi bi-shop fs-3 d-block mb-2"></i>
                لا تتابع أي متجر حالياً.
                <div class="mt-2"><a href="{{ route('home') }}" class="btn btn-sm btn-buy">تصفح المتاجر والمنتجات</a></div>
            </div>
        @else
            <div class="row g-3">
                @foreach ($stores as $store)
                    <div class="col-md-6 col-xl-4">
                        <article class="border rounded-3 h-100 d-flex flex-column" style="border-color:var(--ts-soft-border, #ededed) !important; overflow:hidden;">
                            <div style="height:72px; background: linear-gradient(135deg, #0b5f4a, #c9962c);">
                                @if ($store->store_banner)
                                    <img src="{{ asset('storage/'.$store->store_banner) }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                                @endif
                            </div>
                            <div class="p-3 pt-0 d-flex flex-column flex-grow-1">
                                <div class="d-flex align-items-end gap-2" style="margin-top:-22px;">
                                    <div class="rounded-circle border bg-white d-grid" style="width:48px;height:48px;place-items:center;overflow:hidden;border-color:var(--ts-soft-border, #ededed) !important;">
                                        @if ($store->store_logo)
                                            <img src="{{ asset('storage/'.$store->store_logo) }}" alt="{{ $store->store_name }}" style="width:100%;height:100%;object-fit:cover;">
                                        @else
                                            <i class="bi bi-shop text-muted"></i>
                                        @endif
                                    </div>
                                    <a href="{{ route('stores.show', $store) }}" class="fw-bold text-decoration-none text-truncate" style="color:#333;" translate="no">{{ $store->store_name }}</a>
                                </div>
                                @if ($store->store_description)
                                    <p class="small text-muted mt-2 mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $store->store_description }}</p>
                                @endif
                                <div class="d-flex flex-wrap gap-2 small text-muted mb-3">
                                    <span><i class="bi bi-box-seam"></i> {{ $store->products_count ?? 0 }} منتج</span>
                                    <span><i class="bi bi-people"></i> {{ $store->followers_count ?? 0 }} متابع</span>
                                    @if (($store->seller_reviews_avg_rating ?? null) !== null)
                                        <span><i class="bi bi-star-fill text-warning"></i> {{ number_format((float) $store->seller_reviews_avg_rating, 1) }}</span>
                                    @endif
                                </div>
                                <div class="mt-auto d-flex gap-2">
                                    <a href="{{ route('stores.show', $store) }}" class="btn btn-sm btn-outline-secondary flex-fill">زيارة المتجر</a>
                                    <form action="{{ route('stores.follow', $store) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">إلغاء المتابعة</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">{{ $stores->links() }}</div>
        @endif
    </div>
@endsection
