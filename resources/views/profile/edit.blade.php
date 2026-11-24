@extends('layouts.app')

@section('title', 'الملف الشخصي - تريندشام')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - الملف الشخصي</span>
        <a href="{{ route(match ($user->role) { 'admin' => 'admin.dashboard', 'seller' => 'seller.dashboard', 'employee' => 'employee.dashboard', default => 'customer.dashboard' }) }}"
           class="btn btn-outline-secondary btn-sm">رجوع للوحتي</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 640px;">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">البيانات الشخصية</h2>

            <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label small">الصورة الشخصية</label>
                    <div class="d-flex align-items-center gap-3">
                        @if ($user->avatar)
                            <img src="{{ asset('storage/'.$user->avatar) }}" alt="الصورة الشخصية"
                                 class="rounded-circle" width="72" height="72" style="object-fit: cover;">
                        @endif
                        <input type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror"
                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="form-text">JPG, PNG أو WEBP، بحد أقصى 4 ميغابايت.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="f-first_name" class="form-label small">الاسم</label>
                        <input id="f-first_name" type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                               value="{{ old('first_name', $user->first_name) }}" required>
                        @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label for="f-last_name" class="form-label small">اللقب</label>
                        <input id="f-last_name" type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                               value="{{ old('last_name', $user->last_name) }}" required>
                        @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">رقم الهاتف</label>
                    <div class="row g-2">
                        <div class="col-4 col-md-3">
                            <input type="text" name="country_code" class="form-control @error('country_code') is-invalid @enderror"
                                   placeholder="+90" value="{{ old('country_code', $user->country_code) }}">
                            <div class="form-text">رمز الدولة</div>
                        </div>
                        <div class="col-8 col-md-9">
                            <input type="text" name="national_number" class="form-control @error('national_number') is-invalid @enderror"
                                   placeholder="5xxxxxxxxx" value="{{ old('national_number', $user->national_number) }}">
                            <div class="form-text">الرقم بدون رمز الدولة</div>
                        </div>
                    </div>
                    <div class="form-text">{{ __('الرقم الموثّق في الحساب: :phone — يمكن تحديث رمز الدولة والرقم التفصيلي من هنا.', ['phone' => $user->phone]) }}</div>
                    @error('country_code') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @error('national_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="f-email" class="form-label small">البريد الإلكتروني</label>
                    <input id="f-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required>
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="f-address" class="form-label small">العنوان</label>
                    <textarea id="f-address" name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label for="f-birth_date" class="form-label small">تاريخ الميلاد</label>
                        <input id="f-birth_date" type="date" name="birth_date" max="{{ now()->toDateString() }}"
                               class="form-control @error('birth_date') is-invalid @enderror"
                               value="{{ old('birth_date', $user->birth_date?->toDateString()) }}">
                        @error('birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label for="f-city" class="form-label small">المدينة</label>
                        <input id="f-city" type="text" name="city" class="form-control @error('city') is-invalid @enderror"
                               value="{{ old('city', $user->city) }}">
                        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small">الجنس</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" id="gender-male" value="male"
                                   @checked(old('gender', $user->gender) === 'male')>
                            <label class="form-check-label" for="gender-male">ذكر</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gender" id="gender-female" value="female"
                                   @checked(old('gender', $user->gender) === 'female')>
                            <label class="form-check-label" for="gender-female">أنثى</label>
                        </div>
                    </div>
                    @error('gender') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            </form>
        </div>
    </div>

    {{-- عناوين الشحن للمشتري فقط؛ البائع والمدير والموظف لا يشترون --}}
    @if ($user->role === 'customer')
        @include('profile._addresses')
    @endif

    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-3">تغيير كلمة السر</h2>

            @error('current_password')
                <div class="alert alert-danger py-2 small">{{ $message }}</div>
            @enderror

            <form action="{{ route('profile.password.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="f-current_password" class="form-label small">كلمة السر الحالية</label>
                    <input id="f-current_password" type="password" name="current_password" class="form-control">
                </div>

                <div class="mb-3">
                    <label for="new-password" class="form-label small">كلمة السر الجديدة</label>
                    <input type="password" name="password" id="new-password" class="form-control @error('password') is-invalid @enderror">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label for="f-password_confirmation" class="form-label small">تأكيد كلمة السر الجديدة</label>
                    <input id="f-password_confirmation" type="password" name="password_confirmation" class="form-control">
                </div>

                <ul class="small text-muted mb-3 ps-3" id="password-rules">
                    <li id="rule-length">8 أحرف على الأقل</li>
                    <li id="rule-case">حرف كبير وحرف صغير</li>
                    <li id="rule-number">رقم واحد على الأقل</li>
                    <li id="rule-symbol">رمز واحد على الأقل (!@#$...)</li>
                </ul>

                <button type="submit" class="btn btn-primary">تغيير كلمة السر</button>
            </form>
        </div>
    </div>

    @include('profile._account-closure')
</div>

<script>
const passwordInput = document.getElementById('new-password');
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