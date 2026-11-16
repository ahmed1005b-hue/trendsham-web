@extends('layouts.app')

@section('title', 'الدفع الآمن - تريندشام')

@push('styles')
<style>
    html[dir="rtl"] .ts-masked-card { text-align: right; }
    .ts-pay-option { display: flex; align-items: center; gap: .8rem; border: 1px solid var(--bs-border-color); border-radius: 12px; padding: .8rem 1rem; cursor: pointer; background: var(--bs-body-bg); margin-bottom: .6rem; }
    .ts-pay-option:has(input:checked) { border-color: var(--ts-emerald); box-shadow: 0 0 0 1px var(--ts-emerald) inset; }
    .ts-pay-option .ico { width: 42px; height: 30px; border-radius: 6px; display: grid; place-items: center; color: #fff; font-size: .7rem; font-weight: 800; font-style: italic; }
    .ts-pay-option .ico.visa { background: #1a1f71; } .ts-pay-option .ico.mastercard { background: #333; } .ts-pay-option .ico.other, .ts-pay-option .ico.new { background: #0B5F4A; }
</style>
@endpush

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الدفع</span>
        <a href="{{ route('customer.orders.show', $order) }}" class="btn btn-outline-light btn-sm">تفاصيل الطلب</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 560px;">
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="text-muted small">الطلب #{{ $order->order_number }}</div>
                <div class="small">المبلغ المطلوب</div>
            </div>
            <span class="h4 mb-0 fw-bold">{{ $order->formatted_total }}</span>
        </div>
    </div>

    @if (! $enabled)
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> الدفع الإلكتروني بالبطاقة غير مفعّل حالياً. سيتم تأكيد طلبك والدفع عند الاستلام.
        </div>
        <a href="{{ route('customer.orders.show', $order) }}" class="btn btn-primary w-100">عرض الطلب</a>
    @else
        <form action="{{ route('customer.checkout.pay.process', $order) }}" method="POST" class="card">
            @csrf
            <div class="card-body">
                <h2 class="h6 mb-3">اختر طريقة الدفع</h2>

                @foreach ($cards as $card)
                    <label class="ts-pay-option">
                        <input type="radio" name="card_id" value="{{ $card->id }}" class="form-check-input mt-0" @checked($card->is_default)>
                        <span class="ico {{ in_array($card->brand, ['visa', 'mastercard']) ? $card->brand : 'other' }}">{{ strtoupper(substr($card->brand_label, 0, 4)) }}</span>
                        <span class="flex-grow-1">
                            <span class="fw-bold d-block ts-masked-card" dir="ltr">{{ $card->masked }}</span>
                            <span class="small text-muted">{{ $card->brand_label }} · تنتهي {{ $card->expiry }}</span>
                        </span>
                    </label>
                @endforeach

                <label class="ts-pay-option">
                    <input type="radio" name="card_id" value="" class="form-check-input mt-0" @checked($cards->isEmpty() || ! $cards->contains('is_default', true))>
                    <span class="ico new"><i class="bi bi-plus-lg not-italic"></i></span>
                    <span class="flex-grow-1">
                        <span class="fw-bold d-block">بطاقة جديدة</span>
                        <span class="small text-muted">ستُنقل إلى صفحة البنك الآمنة لإدخال البطاقة</span>
                    </span>
                </label>

                <button class="btn btn-success w-100 btn-lg mt-2">
                    <i class="bi bi-lock-fill"></i> ادفع {{ $order->formatted_total }}
                </button>
                <p class="text-center text-muted small mt-3 mb-0">
                    <i class="bi bi-shield-check"></i> بيانات البطاقة تُدخل في صفحة البنك فقط ولا تمر عبر خوادم تريندشام.
                </p>
            </div>
        </form>
    @endif
</div>
@endsection
