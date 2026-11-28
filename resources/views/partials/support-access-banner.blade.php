{{-- شريط «وضع الدعم»: موظف داخل لوحة متجر بإذن البائع، وزر الخروج. ورسائل الدخول والخروج والمنع. --}}
@php
    $supportAccess = session(\App\Services\SellerSupportAccess::SESSION_KEY);
    $supportStore = is_array($supportAccess) ? \App\Models\SellerProfile::find($supportAccess['store_id'] ?? 0) : null;
@endphp

@if ($supportStore)
    <div class="ts-support-banner" role="status">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>
                <i class="bi bi-headset" aria-hidden="true"></i>
                {{ __('أنت في لوحة متجر') }} <strong translate="no">{{ $supportStore->store_name }}</strong> {{ __('بإذن البائع. كل تغيير يُسجَّل ويراه البائع.') }}
            </span>
            <form action="{{ route('support-access.leave') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn btn-sm btn-light fw-bold">{{ __('الخروج من لوحة المتجر') }}</button>
            </form>
        </div>
    </div>
@endif

@if (session('support_notice'))
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show mb-0" role="alert">
            {{ session('support_notice') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('إغلاق') }}"></button>
        </div>
    </div>
@endif
