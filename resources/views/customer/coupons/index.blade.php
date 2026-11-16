@extends('layouts.customer-account')
@php($accountTitle = 'كوبوناتي')

@push('styles')
<style>
    .ts-coupon-tabs { display: flex; gap: .4rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .ts-coupon-tabs a { padding: .4rem .9rem; border-radius: 999px; border: 1px solid var(--ts-border, #E8DFCC); color: inherit; text-decoration: none; font-size: .88rem; font-weight: 700; }
    .ts-coupon-tabs a.active { background: var(--ts-emerald, #0B5F4A); border-color: var(--ts-emerald, #0B5F4A); color: #fff; }
    .ts-coupon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: .75rem; }
</style>
@endpush

@section('account_content')
<div class="ts-account-card">
    <div class="ts-account-head">
        <h1>كوبوناتي</h1>
    </div>
    <p class="text-muted small mb-3">كوبوناتك جاهزة هنا، ونطبّق لك أفضلها تلقائياً عند إتمام الطلب. لا حاجة لأي رمز.</p>

    <nav class="ts-coupon-tabs" aria-label="تصنيف الكوبونات">
        <a href="{{ route('customer.coupons.index') }}" class="{{ $tab === 'available' ? 'active' : '' }}">{{ __('متاحة (:n)', ['n' => $counts['available']]) }}</a>
        <a href="{{ route('customer.coupons.index', ['tab' => 'used']) }}" class="{{ $tab === 'used' ? 'active' : '' }}">{{ __('مستخدمة (:n)', ['n' => $counts['used']]) }}</a>
        <a href="{{ route('customer.coupons.index', ['tab' => 'expired']) }}" class="{{ $tab === 'expired' ? 'active' : '' }}">منتهية</a>
    </nav>

    @if ($coupons->isEmpty())
        <div class="text-center text-muted py-5">
            <i class="bi bi-ticket-perforated fs-1 d-block mb-2" aria-hidden="true"></i>
            {{ $tab === 'available' ? __('لا توجد كوبونات متاحة حالياً. سنبلغك فور وصول كوبون جديد.') : __('لا شيء هنا.') }}
        </div>
    @else
        <div class="ts-coupon-grid">
            @foreach ($coupons as $coupon)
                @include('customer.coupons._card', [
                    'coupon' => $coupon,
                    'muted' => $tab !== 'available',
                    'note' => $tab === 'used' ? __('استُخدم :date', ['date' => $coupon->used_at?->format('Y-m-d') ?? '']) : null,
                ])
            @endforeach
        </div>
    @endif
</div>
@endsection
