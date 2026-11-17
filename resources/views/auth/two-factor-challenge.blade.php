@extends('layouts.app')

@section('title', __('رمز التحقق').' - '.__('تريندشام'))

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: var(--ts-emerald);" aria-hidden="true"></i>
                <h1 class="h5 fw-bold mt-2 mb-1">{{ __('التحقق بخطوتين') }}</h1>
                <p class="text-muted small mb-0">{{ __('افتح تطبيق المصادقة على هاتفك واكتب الرمز المكوّن من 6 أرقام.') }}</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('two-factor.verify') }}" method="POST">
                @csrf
                <label for="f-code" class="form-label small">{{ __('رمز التحقق') }}</label>
                <input id="f-code" type="text" name="code" class="form-control form-control-lg text-center fw-bold @error('code') is-invalid @enderror"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="20" dir="ltr" style="letter-spacing: .4em;" required autofocus>
                <div class="form-text">{{ __('فقدت هاتفك؟ اكتب أحد رموز الاسترداد بدلاً من ذلك.') }}</div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">{{ __('تأكيد ودخول') }}</button>
            </form>

            <p class="text-center small mt-3 mb-0">
                <a href="{{ route('login') }}" class="text-decoration-none">{{ __('رجوع إلى تسجيل الدخول') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
