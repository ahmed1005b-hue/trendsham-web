@extends('layouts.app')

@section('title', __('لوحة الموظف'))

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - {{ __('الموظف') }}</span>
        <div class="d-flex gap-2">
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bell" aria-hidden="true"></i> {{ __('الإشعارات') }}</a>
            <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-circle" aria-hidden="true"></i> {{ __('ملفي الشخصي') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-secondary btn-sm">{{ __('تسجيل الخروج') }}</button></form>
        </div>
    </div>
</nav>
<div class="container mb-5">
    <h1 class="h4 mb-1">{{ __('مرحبًا، :name', ['name' => auth()->user()->name]) }}</h1>
    <p class="text-muted mb-4">{{ __('شغل اليوم: ما ينتظر تصرّفك الآن.') }}</p>

    @php
        $tiles = [
            ['route' => 'employee.orders.index', 'icon' => 'bi-bag-check', 'label' => __('طلبات جديدة'), 'hint' => __('طلبات وصلت للبائعين ولم تُجهَّز بعد.'), 'key' => 'new_orders', 'count' => $counts['new_orders'] ?? 0],
            ['route' => 'employee.orders.index', 'icon' => 'bi-hourglass-split', 'label' => __('طلبات متأخرة'), 'hint' => __('لم تُشحن بعد أكثر من :days أيام.', ['days' => \App\Services\EmployeeWorkload::LATE_AFTER_DAYS]), 'key' => 'late_orders', 'count' => $counts['late_orders'] ?? 0, 'alert' => true],
            ['route' => 'employee.products.index', 'params' => ['filter' => 'pending'], 'icon' => 'bi-clipboard-check', 'label' => __('منتجات تنتظر المراجعة'), 'hint' => __('اقبلها أو ارفضها مع ذكر السبب.'), 'key' => 'pending_products', 'count' => $counts['pending_products'] ?? 0],
            ['route' => 'employee.assistant-chats.index', 'icon' => 'bi-chat-dots', 'label' => __('محادثات العملاء'), 'hint' => __('عملاء طلبوا التحدث مع موظف.'), 'key' => 'customer_chats', 'count' => $counts['customer_chats'] ?? 0, 'alert' => true],
            ['route' => 'employee.support.index', 'icon' => 'bi-headset', 'label' => __('دعم البائعين'), 'hint' => __('رسائل بائعين لم تُقرأ بعد.'), 'key' => 'seller_support', 'count' => $counts['seller_support'] ?? 0],
            ['route' => 'employee.returns.index', 'icon' => 'bi-arrow-return-left', 'label' => __('المرتجعات'), 'hint' => __('نزاعات إرجاع تحتاج قراراً. إرجاع المال يسجّله المدير.'), 'key' => 'returns', 'count' => $counts['returns'] ?? 0],
        ];
    @endphp

    @php
        $tiles = array_filter($tiles, fn ($tile) => array_key_exists($tile['key'], $counts));
    @endphp
    <div class="row g-3">
        @foreach ($tiles as $tile)
            <div class="col-sm-6 col-lg-4">
                <a href="{{ route($tile['route'], $tile['params'] ?? []) }}" class="card card-body h-100 text-decoration-none" data-employee-tile="{{ $tile['route'] }}">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="h6 mb-0"><i class="bi {{ $tile['icon'] }} me-1" aria-hidden="true"></i> {{ $tile['label'] }}</h2>
                        <span class="badge fs-6 {{ $tile['count'] > 0 ? (($tile['alert'] ?? false) ? 'bg-danger' : 'bg-primary') : 'bg-secondary-subtle text-secondary' }}">{{ $tile['count'] }}</span>
                    </div>
                    <p class="text-muted small mb-0">{{ $tile['hint'] }}</p>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-1">
        @if (auth()->user()->canWorkOn('orders'))
        <div class="col-md-6">
            <a href="{{ route('employee.orders.index') }}" class="card card-body h-100 text-decoration-none">
                <h2 class="h6">{{ __('كل الطلبات') }}</h2>
                <p class="text-muted small mb-0">{{ __('عرض الطلبات وتحديث حالتها وأرشفتها مع الاحتفاظ بسجلها.') }}</p>
            </a>
        </div>
        @endif
        @if (auth()->user()->canWorkOn('products'))
        <div class="col-md-6">
            <a href="{{ route('employee.products.index') }}" class="card card-body h-100 text-decoration-none">
                <h2 class="h6">{{ __('كل المنتجات') }}</h2>
                <p class="text-muted small mb-0">{{ __('مراجعة المنتجات وتعديل بياناتها أو إخفاؤها من المتجر.') }}</p>
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
