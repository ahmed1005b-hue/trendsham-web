@extends('layouts.app')

@section('title', __('دعوة للانضمام').' - تريندشام')

@section('content')
<div class="d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0" style="width: 100%; max-width: 460px;">
        <div class="card-body p-4 p-md-5 text-center">
            <span class="ts-logo h3" style="color: var(--ts-emerald);">تريند<span style="color: var(--ts-gold);">شام</span></span>

            @if (session('error'))
                <div class="alert alert-danger small mt-3 text-start">{{ session('error') }}</div>
            @endif

            @if ($problem)
                <div class="mt-4">
                    <i class="bi bi-envelope-x display-5 text-danger" aria-hidden="true"></i>
                    <p class="mt-3 mb-4">{{ $problem }}</p>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">{{ __('الصفحة الرئيسية') }}</a>
                </div>
            @else
                <div class="mt-4">
                    <i class="bi bi-envelope-paper-heart display-5" style="color: var(--ts-gold);" aria-hidden="true"></i>
                    <h1 class="h5 mt-3">{{ $invitation->role === 'seller' ? __('دعوة لفتح متجر على تريندشام') : __('دعوة للانضمام لفريق تريندشام') }}</h1>
                    <p class="text-muted small mb-4">{{ __('أنت مسجّل الدخول باسم :name. يمكنك قبول الدعوة بحسابك هذا.', ['name' => auth()->user()->name]) }}</p>

                    <form action="{{ route('invitations.accept', $token) }}" method="POST" class="text-start">
                        @csrf
                        @if ($invitation->role === 'seller')
                            <label for="f-store_name" class="form-label small">{{ __('اسم المتجر') }}</label>
                            <input id="f-store_name" type="text" name="store_name" class="form-control mb-1 @error('store_name') is-invalid @enderror" required maxlength="255"
                                   value="{{ old('store_name', $invitation->store_name) }}">
                            @error('store_name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                        @endif
                        <button class="btn btn-primary w-100 mt-3">{{ __('قبول الدعوة') }}</button>
                    </form>

                    <form action="{{ route('logout') }}" method="POST" class="mt-3">
                        @csrf
                        <button class="btn btn-link btn-sm text-muted">{{ __('ليس حسابك؟ سجّل الخروج وأنشئ حساباً جديداً') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
