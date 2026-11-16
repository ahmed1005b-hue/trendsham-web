@php
    $tsLocale = app()->getLocale();
    $tsIsRtl = $tsLocale === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ $tsLocale }}" dir="{{ $tsIsRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'تريندشام')</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230B5F4A'/%3E%3Cpath d='M32 8 56 32 32 56 8 32Z' fill='none' stroke='%23C9962C' stroke-width='3'/%3E%3Cpath d='M15 15h34v34H15z' fill='none' stroke='%23F1D9A0' stroke-width='3'/%3E%3C/svg%3E">
    @if($tsIsRtl)
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet" integrity="sha384-dpuaG1suU0eT09tx5plTaGMLBsfDLzUCCUXOY2j/LSvXYuG6Bqs43ALlhIqAJVRb" crossorigin="anonymous">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Reem+Kufi:wght@400..700&family=Tajawal:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/ts-app.css') }}?v={{ @filemtime(public_path('css/ts-app.css')) }}">
    {{-- بعض الصفحات تستخدم @section('styles') وأخرى @push('styles') --}}
    @yield('styles')
    @stack('styles')
    @include('partials.theme-head')
</head>
<body>
    @include('partials.header')


    <main class="@yield('main_class', 'py-4')">
        @include('partials.offers-opt-in')
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.consent-banner')
    @include('components.chat-widget')
    @include('partials.quick-view')

    <script>
    (() => {
        const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const init = (root=document) => root.querySelectorAll('.js-card-slider').forEach((slider) => {
            if (slider.dataset.ready) return;
            const slides = [...slider.querySelectorAll('.ts-card-slide')];
            if (slides.length < 2) return;
            slider.dataset.ready = '1';
            let i = 0, timer;
            const wrap = slider.closest('.ts-market-card__media-wrap') || slider.closest('.ts-market-card') || slider;
            const dots = [...wrap.querySelectorAll('.ts-card-dots span')];
            const show = (n, manual) => {
                if (!slides.length) return;
                slides[i].classList.remove('is-active'); if (dots[i]) dots[i].classList.remove('is-active');
                i = (n + slides.length) % slides.length;
                slides[i].classList.add('is-active'); if (dots[i]) dots[i].classList.add('is-active');
                const zoom = wrap.querySelector('.ts-product-zoom'); if (zoom && slides[i].src) zoom.dataset.image = slides[i].src;
                if (manual) restart();
            };
            const start = () => { if (!reduce && !timer) timer = setInterval(() => show(i + 1), Number(slider.dataset.interval) || 2200); };
            const stop = () => { clearInterval(timer); timer = null; };
            const restart = () => { stop(); start(); };
            slider._show = show;
            const card = slider.closest('.ts-market-card, article');
            (card || slider).addEventListener('mouseenter', stop);
            (card || slider).addEventListener('mouseleave', start);
            (card || slider).addEventListener('focusin', stop);
            (card || slider).addEventListener('focusout', start);
            start();
        });
        document.addEventListener('click', (e) => {
            const nav = e.target.closest('.ts-card-nav'); if (!nav) return;
            e.preventDefault(); e.stopPropagation();
            const wrap = nav.closest('.ts-market-card__media-wrap'); const slider = wrap && wrap.querySelector('.js-card-slider');
            if (slider && slider._show) slider._show([...slider.querySelectorAll('.ts-card-slide')].findIndex(s=>s.classList.contains('is-active')) + Number(nav.dataset.dir || 1), true);
        });
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
        else init();
        new MutationObserver((muts) => muts.forEach((m) => m.addedNodes.forEach((n) => n.nodeType === 1 && init(n)))).observe(document.body, {childList:true, subtree:true});
    })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    @include('partials.confirm-dialog')
    <script>
    (function () {
        const btn = document.getElementById('ts-theme-toggle');
        if (!btn || !window.tsTheme) return;
        function syncBtn() {
            const dark = window.tsTheme.isDark();
            const text = dark ? @json(__('الوضع النهاري')) : @json(__('الوضع الليلي'));
            btn.title = text;
            btn.setAttribute('aria-label', text);
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            const icon = btn.querySelector('i');
            if (icon) icon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
        syncBtn();
        btn.addEventListener('click', () => window.tsTheme.toggle());
        document.addEventListener('ts:themechange', syncBtn);
    })();
    </script>
    @stack('scripts')
</body>
</html>
