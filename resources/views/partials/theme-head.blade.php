{{--
    المصدر الوحيد لوضع المظهر في الموقع (فاتح / داكن / حسب الجهاز).
    يُطبَّق داخل <head> قبل رسم الصفحة لتجنب وميض الأبيض، ويفعّل:
    - الصنف html.dark لتنسيقات الموقع (public/css/ts-dark.css)
    - data-bs-theme ليتلوّن Bootstrap 5.3 تلقائياً (الحقول، الجداول، القوائم، النوافذ...)
    الواجهة: window.tsTheme.get() / set('light'|'dark'|'system') / toggle() / isDark()
    وحدث 'ts:themechange' عند كل تغيير.
--}}
<script>
(function () {
    var KEY = 'ts_theme', LEGACY = 'trendsham-theme', root = document.documentElement;
    var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function read() {
        try {
            var v = localStorage.getItem(KEY);
            if (!v && localStorage.getItem(LEGACY)) {
                // خيار "المظهر" القديم في لوحة العميل كان يحفظ بمفتاح آخر
                v = localStorage.getItem(LEGACY);
                localStorage.setItem(KEY, v);
                localStorage.removeItem(LEGACY);
            }
            return ['light', 'dark', 'system'].indexOf(v) > -1 ? v : 'light';
        } catch (e) { return 'light'; }
    }

    function apply(pref) {
        var dark = pref === 'dark' || (pref === 'system' && !!mq && mq.matches);
        root.classList.toggle('dark', dark);
        root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
        root.style.colorScheme = dark ? 'dark' : 'light';
        try { document.dispatchEvent(new CustomEvent('ts:themechange', { detail: { pref: pref, dark: dark } })); } catch (e) {}
    }

    window.tsTheme = {
        get: read,
        isDark: function () { return root.classList.contains('dark'); },
        set: function (pref) {
            try { localStorage.setItem(KEY, pref); } catch (e) {}
            apply(pref);
        },
        toggle: function () { this.set(this.isDark() ? 'light' : 'dark'); }
    };

    apply(read());
    if (mq) {
        var onSystem = function () { if (read() === 'system') apply('system'); };
        if (mq.addEventListener) mq.addEventListener('change', onSystem); else mq.addListener(onSystem);
    }
    // مزامنة التبويبات المفتوحة الأخرى
    window.addEventListener('storage', function (e) { if (e.key === KEY) apply(read()); });
})();
</script>
<link rel="stylesheet" href="{{ asset('css/ts-dark.css') }}?v={{ @filemtime(public_path('css/ts-dark.css')) }}">
