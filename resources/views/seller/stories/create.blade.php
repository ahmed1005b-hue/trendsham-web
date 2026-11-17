@extends('layouts.app')

@section('title', 'ستوري جديدة - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - ستوري جديدة</span>
        <a href="{{ route('seller.stories.index') }}" class="btn btn-outline-secondary btn-sm">رجوع</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 480px;">
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="alert alert-light border small">
        هاي الميزة مدفوعة: رح يُخصم <strong>{{ number_format($fee / 100) }} ل.س</strong> من رصيد محفظتك
        ({{ $wallet->formatted_balance }} متوفر حالياً) مقابل نشر الستوري على الصفحة الرئيسية لمدة 24 ساعة.
    </div>

    @if ($wallet->balance < $fee)
        <div class="alert alert-danger small">
            رصيدك غير كافٍ. اربح من مبيعاتك أولاً أو تواصل مع الإدارة.
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('seller.stories.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="f-image" class="form-label">صورة الستوري (عمودية يُفضّل، مثل 9:16)</label>
                    <input id="f-image" type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                    <div class="form-text">JPG, PNG, WEBP فقط — حتى 4 ميغابايت.</div>
                </div>
                <div class="mb-3">
                    <label for="f-caption" class="form-label">نص مختصر (اختياري)</label>
                    <input id="f-caption" type="text" name="caption" class="form-control" maxlength="150" placeholder="مثلاً: عرض اليوم فقط!">
                </div>
                <div class="mb-3">
                    <label for="f-link_url" class="form-label">{{ __('رابط الزر داخل الستوري (اختياري)') }}</label>
                    <input id="f-link_url" type="url" name="link_url" value="{{ old('link_url') }}" class="form-control @error('link_url') is-invalid @enderror" maxlength="500" dir="ltr" placeholder="https://">
                    <div class="form-text">{{ __('انسخ رابط المنتج أو المتجر أو أي صفحة، وسيظهر للعميل زر «اكتشف الآن» يفتحه.') }}</div>
                    @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-primary w-100" {{ $wallet->balance < $fee ? 'disabled' : '' }}>
                    ادفع {{ number_format($fee / 100) }} ل.س وانشر
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
