@extends('layouts.app')

@section('title', 'التحقق من رمز الاستعادة - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>
                <h1 class="text-muted small fw-normal mt-2 mb-0" style="font-family: inherit;">أدخل رمز التحقق</h1>
            </div>

            @if (session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <form action="{{ route('password.phone.verify.submit') }}" method="POST">
                @csrf
                <input type="hidden" name="phone" value="{{ old('phone', $phone) }}">

                <div class="mb-3">
                    <label class="form-label small">رقم الهاتف</label>
                    <input type="text" class="form-control" value="{{ old('phone', $phone) }}" disabled>
                </div>

                <div class="mb-3">
                    <label for="f-code" class="form-label small">رمز التحقق (6 أرقام)</label>
                    <input id="f-code" type="text" name="code" class="form-control" maxlength="6" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">تحقق</button>
            </form>

            <p class="text-center small text-muted mt-4 mb-0">
                <a href="{{ route('password.phone.request') }}">إعادة إرسال الرمز</a> |
                <a href="{{ route('login') }}">رجوع لتسجيل الدخول</a>
            </p>
        </div>
    </div>
</div>

@endsection
