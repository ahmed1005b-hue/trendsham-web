@extends('layouts.app')

@section('title', 'استعادة كلمة السر - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>
                <h1 class="text-muted small fw-normal mt-2 mb-0" style="font-family: inherit;">استعادة كلمة السر</h1>
            </div>

            @if (session('status'))
                <div class="alert alert-success py-2 small">{{ session('status') }}</div>
            @endif
            @error('email')
                <div class="alert alert-danger py-2 small">{{ $message }}</div>
            @enderror

            <p class="small text-muted mb-3">أدخل بريدك الإلكتروني، رح نرسلّك رابط لإعادة تعيين كلمة السر.</p>

            <form action="{{ route('password.email') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="f-email" class="form-label small">البريد الإلكتروني</label>
                    <input id="f-email" type="email" name="email" class="form-control form-control-lg" value="{{ old('email') }}" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">إرسال رابط الاستعادة</button>
            </form>

            <p class="text-center small text-muted mt-4 mb-0">
                <a href="{{ route('password.phone.request') }}">الاستعادة عبر الهاتف</a> |
                <a href="{{ route('login') }}">رجوع لتسجيل الدخول</a>
            </p>
        </div>
    </div>
</div>
@endsection
