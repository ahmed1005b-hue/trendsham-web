@extends('layouts.app')

@section('title', ($campaign->exists ? 'تعديل كوبون' : 'إضافة كوبون').' - لوحة البائع')

@push('styles')
<style>
    .ts-cp-sec { border-bottom: 1px solid #f0f0f0; padding: 1rem 0; }
    .ts-cp-types { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 140px), 1fr)); gap: .5rem; }
    .ts-cp-type input { position: absolute; opacity: 0; }
    .ts-cp-type span { display: block; border: 1.5px solid #e2e2e2; border-radius: 10px; padding: .7rem; text-align: center; cursor: pointer; font-weight: 600; }
    .ts-cp-type input:checked + span { border-color: #0B5F4A; background: #e9f6f0; color: #0B5F4A; }
    .ts-cp-type input:focus-visible + span { outline: 2px solid #C9962C; }
    .ts-cp-products { max-height: 240px; overflow: auto; border: 1px solid #eee; border-radius: 8px; padding: .5rem .75rem; }
    .ts-cp-preview { background: #FBF6EA; border: 1px dashed #C9962C; border-radius: 10px; padding: .75rem 1rem; font-size: .9rem; margin: 1rem 0; }
    .ts-cp-preview:empty { display: none; }
    html.dark .ts-cp-sec { border-color: var(--tsd-border); }
    html.dark .ts-cp-type span { border-color: var(--tsd-border); }
    html.dark .ts-cp-type input:checked + span { background: rgba(111, 211, 168, .12); color: #6fd3a8; border-color: #6fd3a8; }
    html.dark .ts-cp-products { border-color: var(--tsd-border); }
    html.dark .ts-cp-preview { background: rgba(201, 150, 44, .1); color: var(--tsd-text); }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ $campaign->exists ? 'تعديل كوبون' : 'إضافة كوبون' }}</span>
        <a href="{{ route('seller.coupons.index') }}" class="btn btn-outline-secondary btn-sm">كل الكوبونات</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 760px">
    @if ($blockReason)
        <div class="alert alert-warning">
            <i class="bi bi-lock" aria-hidden="true"></i> {{ $blockReason }}
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $campaign->exists ? route('seller.coupons.update', $campaign) : route('seller.coupons.store') }}" novalidate>
                    @csrf
                    @if ($campaign->exists)
                        @method('PUT')
                    @endif
                    @include('seller.coupons._form')
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-success">{{ $campaign->exists ? 'حفظ التعديلات' : 'إنشاء الكوبون' }}</button>
                        <a href="{{ route('seller.coupons.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
