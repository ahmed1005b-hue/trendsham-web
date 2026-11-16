@extends('layouts.app')

@section('title', 'تعيين كلمة مرور جديدة - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>
                <h1 class="text-muted small fw-normal mt-2 mb-0" style="font-family: inherit;">تعيين كلمة مرور جديدة</h1>
            </div>

            @if (session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.phone.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label class="form-label small">رقم الهاتف</label>
                    <input type="text" class="form-control" value="{{ $phone }}" disabled>
                </div>

                <div class="mb-3">
                    <label for="f-password" class="form-label small">كلمة المرور الجديدة</label>
                    <input id="f-password" type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="f-password_confirmation" class="form-label small">تأكيد كلمة المرور</label>
                    <input id="f-password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">تغيير كلمة المرور</button>
            </form>

            <p class="text-center small text-muted mt-4 mb-0">
                <a href="{{ route('password.phone.request') }}">البدء من جديد</a> |
                <a href="{{ route('login') }}">رجوع لتسجيل الدخول</a>
            </p>
        </div>
    </div>
</div>

@endsection
