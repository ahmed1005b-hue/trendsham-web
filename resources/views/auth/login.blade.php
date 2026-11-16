@extends('layouts.app')

@section('title', 'تسجيل الدخول - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>
                <h1 class="text-muted small fw-normal mt-2 mb-0" style="font-family: inherit;">سجّل دخولك للمتابعة</h1>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('login.email') }}" method="POST" id="login-form">
                @csrf

                <div class="mb-3">
                    <label for="f-identifier" class="form-label small">البريد الإلكتروني أو رقم الهاتف</label>
                    <input id="f-identifier" type="text" name="identifier" class="form-control form-control-lg @error('identifier') is-invalid @enderror"
                           value="{{ old('identifier') }}" placeholder="{{ __('example@mail.com أو 09xxxxxxxx') }}" autofocus required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small d-flex justify-content-between">
                        <span>كلمة السر</span>
                        <a href="{{ route('password.request') }}" class="small text-decoration-none">نسيت كلمة المرور؟</a>
                    </label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control form-control-lg" required>
                        <button type="button" class="btn btn-outline-secondary" id="toggle-password" tabindex="-1" aria-label="إظهار كلمة السر" aria-pressed="false">
                            <i class="bi bi-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                    <label class="form-check-label small" for="remember">تذكرني</label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100" id="login-btn">
                    <span class="btn-label">تسجيل الدخول</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </form>

            <p class="text-center small mt-3 mb-0">
                <a href="{{ route('password.request') }}" class="text-decoration-none">نسيت كلمة المرور؟</a>
            </p>

            <p class="text-center small text-muted mt-4 mb-0">
                ليس لديك حساب؟ <a href="{{ route('register') }}" class="fw-bold">أنشئ حساباً جديداً</a>
            </p>
        </div>
    </div>
</div>


<script>
document.getElementById('toggle-password').addEventListener('click', () => {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggle-password-icon');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    const btn = document.getElementById('toggle-password');
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? @json(__('إخفاء كلمة السر')) : @json(__('إظهار كلمة السر')));
});

document.getElementById('login-form').addEventListener('submit', () => {
    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.querySelector('.btn-label').classList.add('d-none');
    btn.querySelector('.spinner-border').classList.remove('d-none');
});
</script>
@endsection
