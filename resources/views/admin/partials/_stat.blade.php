{{-- بطاقة مؤشر واحد: أيقونة + عنوان + قيمة + سطر فرعي اختياري + رابط اختياري --}}
<div class="{{ $col ?? 'col-6 col-md-4 col-xl-3' }}">
    @if (! empty($href))<a href="{{ $href }}" class="text-decoration-none d-block h-100">@endif
    <div class="an-card">
        <div class="an-title"><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
        <div class="an-value">{{ $value }} @if (! empty($unit))<small>{{ $unit }}</small>@endif</div>
        @if (array_key_exists('change', get_defined_vars()))
            @include('seller.partials._analytics_change', ['value' => $change])
        @endif
        @if (! empty($sub))<div class="an-sub">{{ $sub }}</div>@endif
    </div>
    @if (! empty($href))</a>@endif
</div>
