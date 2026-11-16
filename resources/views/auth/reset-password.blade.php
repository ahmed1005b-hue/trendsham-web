@extends('layouts.app')

@section('title', 'تعيين كلمة سر جديدة - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>
                <h1 class="text-muted small fw-normal mt-2 mb-0" style="font-family: inherit;">تعيين كلمة سر جديدة</h1>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label for="f-email" class="form-label small">البريد الإلكتروني</label>
                    <input id="f-email" type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small">كلمة السر الجديدة</label>
                    <input type="password" name="password" id="password" class="form-control" required>
                </div>

                <div class="mb-2">
                    <label for="f-password_confirmation" class="form-label small">تأكيد كلمة السر</label>
                    <input id="f-password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                </div>

                <ul class="small text-muted mb-3 ps-3" id="password-rules">
                    <li id="rule-length">8 أحرف على الأقل</li>
                    <li id="rule-case">حرف كبير وحرف صغير</li>
                    <li id="rule-number">رقم واحد على الأقل</li>
                    <li id="rule-symbol">رمز واحد على الأقل (!@#$...)</li>
                </ul>

                <button type="submit" class="btn btn-primary btn-lg w-100">تغيير كلمة السر</button>
            </form>
        </div>
    </div>
</div>

<script>
const passwordInput = document.getElementById('password');
const rules = {
    length: v => v.length >= 8,
    case: v => /[a-z]/.test(v) && /[A-Z]/.test(v),
    number: v => /[0-9]/.test(v),
    symbol: v => /[^A-Za-z0-9]/.test(v),
};
passwordInput.addEventListener('input', () => {
    const value = passwordInput.value;
    for (const [key, test] of Object.entries(rules)) {
        const el = document.getElementById(`rule-${key}`);
        el.classList.toggle('text-success', test(value));
        el.classList.toggle('text-muted', !test(value));
    }
});
</script>
@endsection
