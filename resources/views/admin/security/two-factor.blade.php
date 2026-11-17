@extends('layouts.app')

@section('title', __('أمان الحساب').' - '.__('لوحة المدير'))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('أمان الحساب') }}</span>
        @if ($user->hasTwoFactor())
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('لوحة المدير') }}</a>
        @endif
    </div>
</nav>

<div class="container mb-5" style="max-width: 720px;">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-warning">{{ session('error') }}</div>
    @endif

    @if ($recoveryCodes)
        <div class="card border-warning mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold"><i class="bi bi-key" aria-hidden="true"></i> {{ __('رموز الاسترداد') }}</h2>
                <p class="small text-muted">{{ __('إذا فقدت هاتفك، يمكنك الدخول بأحد هذه الرموز (كل رمز يعمل مرة واحدة). اكتبها أو صوّرها واحفظها في مكان آمن، فلن تظهر مرة أخرى.') }}</p>
                <div class="row g-2" dir="ltr">
                    @foreach ($recoveryCodes as $code)
                        <div class="col-6 col-md-3"><code class="d-block text-center p-2 bg-light rounded fw-bold">{{ $code }}</code></div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($user->hasTwoFactor())
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h6 fw-bold text-success"><i class="bi bi-shield-check" aria-hidden="true"></i> {{ __('التحقق بخطوتين مفعّل') }}</h2>
                <p class="small mb-1">{{ __('عند كل دخول سيُطلب منك الرمز من تطبيق المصادقة على هاتفك.') }}</p>
                <p class="small text-muted mb-0">{{ __('رموز الاسترداد المتبقية: :n', ['n' => $remaining]) }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h6 fw-bold">{{ __('رموز استرداد جديدة') }}</h2>
                <p class="small text-muted">{{ __('أنشئ رموزاً جديدة إذا استخدمت معظمها أو أضعتها. الرموز القديمة تتوقف فوراً.') }}</p>
                <form action="{{ route('admin.security.two-factor.recovery') }}" method="POST" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" style="max-width: 220px;" dir="ltr"
                           inputmode="numeric" autocomplete="one-time-code" placeholder="{{ __('الرمز من التطبيق') }}" aria-label="{{ __('الرمز من التطبيق') }}" required>
                    <button class="btn btn-outline-primary">{{ __('إنشاء رموز جديدة') }}</button>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body p-4">
                <h2 class="h5 fw-bold mb-1">{{ __('فعّل التحقق بخطوتين') }}</h2>
                <p class="small text-muted">{{ __('حتى لو عرف أحد كلمة سرك، لن يستطيع دخول لوحة الإدارة بدون هاتفك.') }}</p>

                <ol class="small ps-3">
                    <li class="mb-2">{{ __('ثبّت على هاتفك تطبيق Google Authenticator أو Microsoft Authenticator (مجاني).') }}</li>
                    <li class="mb-2">{{ __('في التطبيق اضغط «+» ثم «مسح رمز QR»، وامسح هذا الرمز:') }}</li>
                </ol>

                <div class="text-center my-3">
                    <div class="d-inline-block p-2 bg-white rounded border">{!! $qr !!}</div>
                    <p class="small text-muted mt-2 mb-0">{{ __('أو اكتب هذا المفتاح يدوياً في التطبيق:') }}</p>
                    <code class="fw-bold" dir="ltr" style="letter-spacing: .1em;">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
                </div>

                <ol class="small ps-3" start="3">
                    <li class="mb-2">{{ __('اكتب الرمز المكوّن من 6 أرقام الذي يظهر في التطبيق:') }}</li>
                </ol>
                <form action="{{ route('admin.security.two-factor.confirm') }}" method="POST" class="d-flex gap-2 flex-wrap">
                    @csrf
                    <input type="text" name="code" class="form-control form-control-lg text-center fw-bold @error('code') is-invalid @enderror" style="max-width: 200px; letter-spacing: .3em;" dir="ltr"
                           inputmode="numeric" autocomplete="one-time-code" maxlength="10" aria-label="{{ __('رمز التحقق') }}" required autofocus>
                    <button class="btn btn-primary btn-lg">{{ __('تفعيل') }}</button>
                    @error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
