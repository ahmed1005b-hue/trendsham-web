@extends('layouts.app')

@section('title', 'إنشاء حساب - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 460px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>
                <h1 class="text-muted small fw-normal mt-2 mb-0" style="font-family: inherit;" id="step-subtitle">أنشئ حسابك للبدء بالتسوّق أو البيع</h1>
            </div>

            <div id="alert-box" class="alert d-none py-2 small" role="alert"></div>
            @if (session('info'))
                <div class="alert alert-success py-2 small" role="status"><i class="bi bi-gift" aria-hidden="true"></i> {{ session('info') }}</div>
            @endif

            {{-- ============ الخطوة 1: بيانات الحساب ============ --}}
            <form id="details-form">
                <div class="mb-3">
                    <label for="account_type" class="form-label small">نوع الحساب</label>
                    <select name="account_type" id="account_type" class="form-select form-select-lg">
                        <option value="customer" selected>عميل - أريد التسوّق</option>
                        <option value="seller">بائع - أريد فتح متجر</option>
                        <option value="employee">موظف - أطلب الانضمام لفريق العمل</option>
                    </select>
                </div>

                <div id="employee-notice" class="alert alert-info d-none small">
                    لن تتمكن من تسجيل الدخول إلى لوحة الموظف إلا بعد مراجعة المدير والموافقة على طلبك.
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="f-first_name" class="form-label small">الاسم</label>
                        <input id="f-first_name" type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label for="f-last_name" class="form-label small">اللقب</label>
                        <input id="f-last_name" type="text" name="last_name" class="form-control" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="reg-phone" class="form-label small">رقم الهاتف</label>
                    <input type="tel" name="phone" id="reg-phone" class="form-control" placeholder="09xxxxxxxx" inputmode="numeric" required>
                    <div class="form-text">رح نرسلّك رمز تأكيد على هذا الرقم بالخطوة الجاية.</div>
                </div>

                <div class="mb-3">
                    <label for="f-email" class="form-label small">البريد الإلكتروني</label>
                    <input id="f-email" type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="f-address" class="form-label small">العنوان (اختياري)</label>
                    <input id="f-address" type="text" name="address" class="form-control">
                </div>

                <div id="seller-fields" class="d-none border rounded p-3 mb-3" style="background: var(--ts-cream);">
                    <div class="mb-2">
                        <label for="f-store_name" class="form-label small">اسم المتجر</label>
                        <input id="f-store_name" type="text" name="store_name" class="form-control">
                    </div>
                    <div class="mb-2">
                        <label for="f-store_description" class="form-label small">وصف المتجر (اختياري)</label>
                        <textarea id="f-store_description" name="store_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <label for="f-commercial_register" class="form-label small">السجل التجاري (اختياري)</label>
                        <input id="f-commercial_register" type="text" name="commercial_register" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label small">كلمة السر</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" required>
                        <button type="button" class="btn btn-outline-secondary" id="toggle-password" tabindex="-1" aria-label="إظهار كلمة السر" aria-pressed="false">
                            <i class="bi bi-eye" id="toggle-password-icon"></i>
                        </button>
                    </div>
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

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="agree-terms" required>
                    <label class="form-check-label small" for="agree-terms">
                        أوافق على <a href="{{ route('legal.terms') }}" target="_blank">الشروط والأحكام</a>
                        و<a href="{{ route('legal.privacy') }}" target="_blank">سياسة الخصوصية</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100" id="details-btn">
                    <span class="btn-label">متابعة</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>
            </form>

            {{-- ============ الخطوة 2: تأكيد رقم الهاتف ============ --}}
            <div id="social-register">@include('auth._social_buttons')</div>

            <form id="verify-form" class="d-none">
                <p class="text-muted small text-center mb-3">
                    أرسلنا رمز تحقق مكوّن من 6 أرقام إلى <strong id="verify-phone-display"></strong>
                </p>

                <input type="text" id="verify-code" class="form-control form-control-lg text-center mb-3"
                       style="letter-spacing: .5em; font-size: 1.4rem;" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required>

                <button type="submit" class="btn btn-primary btn-lg w-100 mb-2" id="verify-btn">
                    <span class="btn-label">تأكيد وإنشاء الحساب</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                </button>

                <div class="text-center">
                    <button type="button" id="resend-btn" class="btn btn-link btn-sm" disabled>
                        إعادة إرسال الرمز (<span id="resend-timer">60</span>)
                    </button>
                    <br>
                    <button type="button" id="edit-details-btn" class="btn btn-link btn-sm text-muted">تعديل البيانات</button>
                </div>
            </form>

            <p class="text-center small text-muted mt-4 mb-0" id="login-link-wrap">
                عندك حساب؟ <a href="{{ route('login') }}" class="fw-bold">سجّل دخولك</a>
            </p>
        </div>
    </div>
</div>


<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

function showAlert(message, type = 'danger') {
    const box = document.getElementById('alert-box');
    box.textContent = message;
    box.className = `alert alert-${type} py-2 small`;
}
function hideAlert() {
    document.getElementById('alert-box').classList.add('d-none');
}

function setLoading(button, isLoading) {
    button.disabled = isLoading;
    button.querySelector('.btn-label').classList.toggle('d-none', isLoading);
    button.querySelector('.spinner-border').classList.toggle('d-none', !isLoading);
}

// إظهار حقول المتجر لو اختار "بائع"
document.getElementById('account_type').addEventListener('change', (e) => {
    const isSeller = e.target.value === 'seller';
    document.getElementById('seller-fields').classList.toggle('d-none', !isSeller);
    document.querySelector('input[name="store_name"]').required = isSeller;
    document.getElementById('employee-notice').classList.toggle('d-none', e.target.value !== 'employee');
});

// إظهار/إخفاء كلمة السر
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

// تحقق فوري من متطلبات كلمة السر
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

// ============ الخطوة 1: إرسال البيانات وبدء التحقق ============
document.getElementById('details-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    const form = e.target;
    const btn = document.getElementById('details-btn');
    setLoading(btn, true);

    const payload = Object.fromEntries(new FormData(form).entries());

    const res = await fetch('{{ route("register.start") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload),
    });
    const data = await res.json();
    setLoading(btn, false);

    if (!res.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0][0] : data.message;
        showAlert(firstError || @json(__('حدث خطأ، حاول مرة أخرى.')));
        return;
    }

    document.getElementById('verify-phone-display').textContent = payload.phone;
    document.getElementById('step-subtitle').textContent = @json(__('خطوة أخيرة - أكّد رقم هاتفك'));
    document.getElementById('details-form').classList.add('d-none');
    document.getElementById('verify-form').classList.remove('d-none');
    document.getElementById('social-register')?.classList.add('d-none');
    document.getElementById('login-link-wrap').classList.add('d-none');
    document.getElementById('verify-code').focus();
    startResendCooldown();
});

// ============ الخطوة 2: تأكيد الرمز ============
let resendCooldown = null;
function startResendCooldown() {
    const btn = document.getElementById('resend-btn');
    const timerEl = document.getElementById('resend-timer');
    let seconds = 60;
    btn.disabled = true;
    timerEl.textContent = seconds;

    clearInterval(resendCooldown);
    resendCooldown = setInterval(() => {
        seconds -= 1;
        timerEl.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(resendCooldown);
            btn.disabled = false;
            btn.textContent = @json(__('إعادة إرسال الرمز'));
        }
    }, 1000);
}

const codeInput = document.getElementById('verify-code');
codeInput.addEventListener('input', () => {
    codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
    if (codeInput.value.length === 6) document.getElementById('verify-form').requestSubmit();
});

document.getElementById('verify-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    const code = codeInput.value.trim();
    if (code.length !== 6) return showAlert(@json(__('أدخل رمز التحقق كاملاً (6 أرقام).')));

    const btn = document.getElementById('verify-btn');
    setLoading(btn, true);

    const res = await fetch('{{ route("register.verify") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ code }),
    });
    const data = await res.json();

    if (!res.ok) {
        setLoading(btn, false);
        showAlert(data.message || @json(__('حدث خطأ، حاول مرة أخرى.')));
        codeInput.value = '';
        codeInput.focus();
        return;
    }

    window.location.href = data.redirect;
});

document.getElementById('resend-btn').addEventListener('click', async () => {
    hideAlert();
    const res = await fetch('{{ route("register.resend") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });
    const data = await res.json();

    if (!res.ok) return showAlert(data.message);

    showAlert(@json(__('تم إرسال رمز جديد.')), 'success');
    startResendCooldown();
});

document.getElementById('edit-details-btn').addEventListener('click', () => {
    clearInterval(resendCooldown);
    hideAlert();
    document.getElementById('verify-form').classList.add('d-none');
    document.getElementById('details-form').classList.remove('d-none');
    document.getElementById('social-register')?.classList.remove('d-none');
    document.getElementById('login-link-wrap').classList.remove('d-none');
    document.getElementById('step-subtitle').textContent = @json(__('أنشئ حسابك للبدء بالتسوّق أو البيع'));
    codeInput.value = '';
});
</script>
@endsection
