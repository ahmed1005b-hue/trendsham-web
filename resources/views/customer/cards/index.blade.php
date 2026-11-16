@extends('layouts.customer-account')
@php($accountTitle = 'بطاقاتي البنكية')

@push('styles')
<style>
    .ts-bank-card { position: relative; border-radius: 16px; padding: 1.1rem 1.2rem; color: #fff; min-height: 150px; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; }
    .ts-bank-card.visa { background: linear-gradient(135deg, #1a1f71, #2d5bd3); }
    .ts-bank-card.mastercard { background: linear-gradient(135deg, #222, #5a5a5a); }
    .ts-bank-card.other { background: linear-gradient(135deg, #073D30, #0B5F4A); }
    .ts-bank-card::after { content: ''; position: absolute; width: 180px; height: 180px; border-radius: 50%; background: rgba(255,255,255,.07); top: -70px; left: -50px; }
    .ts-bank-card .num { font-family: ui-monospace, Consolas, monospace; font-size: 1.05rem; letter-spacing: .08em; direction: ltr; text-align: left; white-space: nowrap; }
    .ts-bank-card .brand { font-weight: 800; font-size: 1.1rem; font-style: italic; }
    .ts-bank-card .meta { font-size: .78rem; opacity: .85; direction: ltr; display: flex; justify-content: space-between; gap: 1rem; }
    .ts-add-card { border: 2px dashed var(--bs-border-color); border-radius: 16px; min-height: 150px; display: grid; place-items: center; text-align: center; color: var(--bs-secondary-color); background: none; width: 100%; }
    .ts-add-card:hover:not(:disabled) { border-color: var(--ts-emerald); color: var(--ts-emerald); }
</style>
@endpush

@section('account_content')
    <div class="ts-account-card">
        <div class="ts-account-head">
            <h1>بطاقاتي البنكية</h1>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        @if ($sandbox)
            <div class="alert alert-warning small"><i class="bi bi-cone-striped"></i> <strong>وضع الاختبار:</strong> البنك الحالي تجريبي ولا تُستخدم فيه بطاقات حقيقية.</div>
        @endif

        <div class="row g-3 mb-3">
            @foreach ($cards as $card)
                <div class="col-sm-6 col-xl-4">
                    <div class="ts-bank-card {{ in_array($card->brand, ['visa', 'mastercard']) ? $card->brand : 'other' }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="brand">{{ $card->brand_label }}</span>
                            @if ($card->is_default)<span class="badge bg-light text-dark">افتراضية</span>@endif
                        </div>
                        <div class="num">•••• •••• •••• {{ $card->last4 }}</div>
                        <div class="meta">
                            <span>{{ $card->holder_name }}</span>
                            <span>{{ $card->expiry }} @if ($card->isExpired())<b class="text-warning">منتهية</b>@endif</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        @unless ($card->is_default)
                            <form action="{{ route('customer.cards.default', $card) }}" method="POST">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">اجعلها افتراضية</button>
                            </form>
                        @endunless
                        <form action="{{ route('customer.cards.destroy', $card) }}" method="POST" data-confirm="حذف هذه البطاقة؟">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">حذف</button>
                        </form>
                    </div>
                </div>
            @endforeach

            <div class="col-sm-6 col-xl-4">
                <form action="{{ route('customer.cards.store') }}" method="POST">
                    @csrf
                    <button class="ts-add-card" @disabled(! $enabled)>
                        <span>
                            <i class="bi bi-plus-circle fs-2 d-block mb-1"></i>
                            {{ $enabled ? 'إضافة بطاقة جديدة' : 'إضافة البطاقات ستتاح عند تفعيل الدفع الإلكتروني' }}
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <div class="border rounded-3 p-3 small text-muted">
            <i class="bi bi-shield-lock-fill text-success"></i>
            <strong>بطاقتك في أمان:</strong>
            تُدخل بيانات البطاقة في صفحة البنك الآمنة فقط، ولا تصل إلى خوادم تريندشام.
            نحن نحتفظ برمز مشفّر من البنك وآخر 4 أرقام فقط، لتدفع بضغطة واحدة في المرة القادمة.
        </div>
    </div>
@endsection
