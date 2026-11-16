{{-- شريط معلومات علوي اختياري: تعرّفه الصفحة عبر @section('topbar') (الرئيسية مثلاً) --}}
@hasSection('topbar')
    @yield('topbar')
@endif

<header class="ts-header ts-masthead">
    <div class="container ts-masthead__row">
        <a href="{{ route('home') }}" class="ts-logo ts-masthead__logo text-decoration-none" aria-label="تريندشام - الصفحة الرئيسية">
            تريند<span>شام</span>
        </a>

        <form action="{{ route('search') }}" method="GET" class="ts-search ts-masthead__search" role="search">
            <i class="bi bi-search ts-masthead__search-icon" aria-hidden="true"></i>
            <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                   placeholder="ابحث عن منتج..." aria-label="ابحث عن منتج">
            <button class="btn" aria-label="بحث"><i class="bi bi-search" aria-hidden="true"></i><span class="d-none d-md-inline">بحث</span></button>
        </form>

        <div class="ts-masthead__actions">
            {{-- أيقونة اللغة (قائمة منسدلة صغيرة) --}}
            <div class="ts-hover-menu">
                <button type="button" class="ts-header-icon" title="اللغة" aria-label="اللغة">
                    <i class="bi bi-translate" aria-hidden="true"></i><span class="ts-hicon__label">اللغة</span>
                </button>
                <div class="ts-hover-panel ts-lang-panel">
                    <form method="POST" action="{{ route('preferences.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="ar">
                        <button type="submit" class="ts-lang-option {{ app()->getLocale()==='ar' ? 'active' : '' }}">
                            <i class="bi bi-check2"></i> العربية
                        </button>
                    </form>
                    <form method="POST" action="{{ route('preferences.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="en">
                        <button type="submit" class="ts-lang-option {{ app()->getLocale()==='en' ? 'active' : '' }}">
                            <i class="bi bi-check2"></i> English
                        </button>
                    </form>
                    <form method="POST" action="{{ route('preferences.language') }}">
                        @csrf
                        <input type="hidden" name="locale" value="tr">
                        <button type="submit" class="ts-lang-option {{ app()->getLocale()==='tr' ? 'active' : '' }}">
                            <i class="bi bi-check2"></i> Türkçe
                        </button>
                    </form>
                </div>
            </div>

            {{-- أيقونة الوضع الليلي/النهاري (يربطها layouts/app عبر #ts-theme-toggle؛ أول <i> هي الأيقونة المتبدّلة) --}}
            <button type="button" id="ts-theme-toggle" class="ts-header-icon" title="الوضع الليلي" aria-label="تبديل الوضع الليلي">
                <i class="bi bi-moon-stars-fill" aria-hidden="true"></i><span class="ts-hicon__label">المظهر</span>
            </button>
            @auth
                @if(auth()->user()->role === 'seller')
                    <div class="ts-hover-menu">
                        <a href="{{ route('seller.dashboard') }}" class="ts-header-icon" title="لوحة البائع" aria-label="لوحة البائع">
                            <i class="bi bi-person" aria-hidden="true"></i><span class="ts-hicon__label">لوحتي</span>
                        </a>
                        <div class="ts-hover-panel">
                            <div class="ts-hover-title">مرحباً، {{ auth()->user()->name }}</div>
                            <div class="ts-hover-links">
                                <a href="{{ route('seller.dashboard') }}">لوحة البائع</a>
                                <a href="{{ route('seller.orders.index') }}">طلباتي</a>
                                <a href="{{ route('seller.wallet.index') }}">محفظتي</a>
                                <a href="{{ route('profile.edit') }}">إعدادات الحساب</a>
                            </div>
                            <form action="{{ route('logout') }}" method="POST" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">تسجيل الخروج</button>
                            </form>
                        </div>
                    </div>
                @elseif(auth()->user()->role === 'customer')
                    @php
                        $hoverCartItems = auth()->user()->cartItems()->with('product')->get();
                        $hoverCartSubtotal = $hoverCartItems->sum('subtotal');
                        $hoverCartCount = $hoverCartItems->sum('quantity');
                        $hoverFavorites = auth()->user()->favorites()->with('product.primaryImage')->latest()->take(6)->get();
                        $hoverFavoritesCount = auth()->user()->favorites()->count();
                    @endphp

                    <div class="ts-hover-menu">
                        <a href="{{ route('customer.dashboard') }}" class="ts-header-icon" title="حسابي" aria-label="حسابي">
                            <i class="bi bi-person" aria-hidden="true"></i><span class="ts-hicon__label">حسابي</span>
                        </a>
                        <div class="ts-hover-panel">
                            <div class="ts-hover-title">مرحباً، {{ auth()->user()->name }}</div>
                            <div class="ts-hover-links">
                                <a href="{{ route('customer.dashboard') }}">حسابي</a>
                                <a href="{{ route('customer.orders.index') }}">طلباتي</a>
                                <a href="{{ route('customer.favorites.index') }}">المفضلة ({{ $hoverFavoritesCount }})</a>
                                <a href="{{ route('customer.followed-stores.index') }}">المتاجر المتابَعة</a>
                                <a href="{{ route('profile.edit') }}">إعدادات الحساب</a>
                            </div>
                            <form action="{{ route('logout') }}" method="POST" class="mt-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">تسجيل الخروج</button>
                            </form>
                        </div>
                    </div>

                    <div class="ts-hover-menu">
                        <a href="{{ route('customer.favorites.index') }}" class="ts-header-icon" title="المفضلة" aria-label="المفضلة">
                            <i class="bi bi-heart" aria-hidden="true"></i><span class="ts-hicon__label">المفضلة</span>
                            @if($hoverFavoritesCount > 0)
                                <span class="badge rounded-pill ts-hicon__badge">{{ $hoverFavoritesCount }}</span>
                            @endif
                        </a>
                        <div class="ts-hover-panel">
                            <div class="ts-hover-title">المفضلة ({{ $hoverFavoritesCount }})</div>
                            @if($hoverFavorites->isEmpty())
                                <div class="ts-hover-empty">لا توجد منتجات في المفضلة بعد.</div>
                            @else
                                <div class="ts-hover-list">
                                    @foreach($hoverFavorites as $fav)
                                        @if($fav->product)
                                            <a href="{{ route('products.show', $fav->product) }}" class="ts-hover-item">
                                                <div class="ts-hover-thumb">
                                                    @if($fav->product->primaryImage)
                                                        <img src="{{ $fav->product->primaryImage->thumb_url }}" alt="{{ $fav->product->name }}">
                                                    @else
                                                        <i class="bi bi-box-seam"></i>
                                                    @endif
                                                </div>
                                                <div class="ts-hover-item-body">
                                                    <div class="ts-hover-item-name">{{ $fav->product->name }}</div>
                                                    <div class="ts-hover-item-price">{{ $fav->product->formatted_price }}</div>
                                                </div>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                                <a href="{{ route('customer.favorites.index') }}" class="ts-hover-more">عرض كل المفضلة</a>
                            @endif
                        </div>
                    </div>

                    <div class="ts-hover-menu" id="cart-hover-menu">
                        <a href="{{ route('customer.cart.index') }}" class="ts-header-icon position-relative" title="السلة" aria-label="السلة" id="cart-icon">
                            <i class="bi bi-bag" aria-hidden="true"></i><span class="ts-hicon__label">السلة</span>
                            <span id="cart-badge" class="badge rounded-pill ts-hicon__badge" style="{{ $hoverCartCount > 0 ? '' : 'display:none;' }}">
                                {{ $hoverCartCount }}
                            </span>
                        </a>
                        <div class="ts-hover-panel ts-hover-cart">
                            <div class="ts-hover-title">السلة ({{ $hoverCartCount }})</div>
                            @if($hoverCartItems->isEmpty())
                                <div class="ts-hover-empty">سلتك فارغة.</div>
                            @else
                                <div class="ts-hover-list">
                                    @foreach($hoverCartItems as $ci)
                                        @if($ci->product)
                                            <div class="ts-hover-item">
                                                <div class="ts-hover-thumb">
                                                    @if($ci->product->primaryImage)
                                                        <img src="{{ $ci->product->primaryImage->thumb_url }}" alt="{{ $ci->product->name }}">
                                                    @else
                                                        <i class="bi bi-box-seam"></i>
                                                    @endif
                                                </div>
                                                <div class="ts-hover-item-body">
                                                    <div class="ts-hover-item-name">{{ $ci->product->name }}</div>
                                                    <div class="ts-hover-item-meta">{{ $ci->quantity }} × {{ number_format($ci->unit_price / 100) }} ل.س</div>
                                                </div>
                                                <div class="ts-hover-item-price">{{ number_format($ci->subtotal / 100) }} ل.س</div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                                <div class="ts-hover-total">
                                    <span>المجموع الفرعي</span>
                                    <span>{{ number_format($hoverCartSubtotal / 100) }} ل.س</span>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <a href="{{ route('customer.cart.index') }}" class="btn btn-sm btn-outline-primary flex-grow-1">عرض السلة</a>
                                    <a href="{{ route('customer.checkout.show') }}" class="btn btn-sm btn-primary flex-grow-1">إتمام الشراء</a>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="ts-header-icon" title="لوحة الإدارة"><i class="bi bi-person" aria-hidden="true"></i><span class="ts-hicon__label">الإدارة</span></a>
                    <a href="{{ route('admin.orders.index') }}" class="ts-header-icon" title="الطلبات"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span class="ts-hicon__label">الطلبات</span></a>
                    <a href="{{ route('admin.products.index') }}" class="ts-header-icon" title="المنتجات"><i class="bi bi-box" aria-hidden="true"></i><span class="ts-hicon__label">المنتجات</span></a>
                    <a href="{{ route('admin.sellers.index') }}" class="ts-header-icon" title="البائعون"><i class="bi bi-people" aria-hidden="true"></i><span class="ts-hicon__label">البائعون</span></a>
                @else
                    <a href="{{ route('login') }}" class="ts-header-icon" title="تسجيل الدخول"><i class="bi bi-person" aria-hidden="true"></i><span class="ts-hicon__label">دخول</span></a>
                @endif
            @else
                <a href="{{ route('login') }}" class="ts-header-icon" title="تسجيل الدخول"><i class="bi bi-person" aria-hidden="true"></i><span class="ts-hicon__label">حسابي</span></a>
                <a href="{{ route('login') }}" class="ts-header-icon" title="المفضلة" aria-label="المفضلة - سجّل الدخول"><i class="bi bi-heart" aria-hidden="true"></i><span class="ts-hicon__label">المفضلة</span></a>
                <a href="{{ route('login') }}" class="ts-header-icon" title="السلة" aria-label="السلة - سجّل الدخول"><i class="bi bi-bag" aria-hidden="true"></i><span class="ts-hicon__label">السلة</span></a>
            @endauth
        </div>
    </div>
</header>

{{-- شريط فقاعات الأقسام الرئيسية تحت الهيدر — يظهر أينما ظهر الهيدر (استعلام مباشر كما في سلة الهيدر) --}}
@php
    $tsNavCategories = \App\Support\AppCache::navCategories();
    $tsRouteCategory = request()->route('category');
    $tsActiveCategoryId = $tsRouteCategory instanceof \App\Models\Category
        ? ($tsRouteCategory->parent_id ?: $tsRouteCategory->id)
        : null;
    $tsCategoryIcon = function (string $name): ?string {
        $map = [
            'إلكترون' => 'bi-phone', 'رجالية' => 'bi-person-standing', 'نسائية' => 'bi-person-standing-dress',
            'المطبخ' => 'bi-cup-hot', 'الجمال' => 'bi-stars', 'الأطفال' => 'bi-balloon', 'الغذائية' => 'bi-basket2',
            'الرياضة' => 'bi-bicycle', 'الكتب' => 'bi-book', 'السيارات' => 'bi-car-front', 'الأحذية' => 'bi-handbag',
            'الساعات' => 'bi-smartwatch', 'العطور' => 'bi-droplet-half', 'الأثاث' => 'bi-lamp', 'الحرف' => 'bi-palette',
            'الهدايا' => 'bi-gift', 'الحيوانات' => 'bi-heart', 'الحدائق' => 'bi-flower1', 'الصحة' => 'bi-capsule',
            'الأدوات' => 'bi-tools',
        ];
        foreach ($map as $needle => $icon) {
            if (str_contains($name, $needle)) {
                return $icon;
            }
        }
        return null;
    };
@endphp
@if ($tsNavCategories->isNotEmpty())
<nav id="browse-categories" class="ts-catstrip" aria-label="الأقسام الرئيسية">
    <div class="container">
        <div class="ts-catstrip__wrap" data-ts-catstrip-wrap>
            <button type="button" class="ts-catstrip__nav ts-catstrip__nav--prev" data-dir="-1" aria-label="الأقسام السابقة" hidden><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            <div class="ts-catstrip__track" data-ts-catstrip>
                @foreach ($tsNavCategories as $cat)
                    @php
                        $tsIcon = $tsCategoryIcon($cat->name);
                        $tsLabel = \Illuminate\Support\Facades\Lang::has('categories.'.$cat->name) ? __('categories.'.$cat->name) : $cat->name;
                        $tsActive = $tsActiveCategoryId === $cat->id;
                    @endphp
                    <a href="{{ route('categories.show', $cat) }}" class="ts-catbub ts-catbub--{{ $loop->index % 3 }} {{ $tsActive ? 'is-active' : '' }}" title="{{ $tsLabel }}" draggable="false" @if($tsActive) aria-current="page" @endif>
                        <span class="ts-catbub__disc" aria-hidden="true">
                            @if ($tsIcon)<i class="bi {{ $tsIcon }}"></i>@else<span>{{ mb_substr($cat->name, 0, 1) }}</span>@endif
                        </span>
                        <span class="ts-catbub__label" translate="no">{{ $tsLabel }}</span>
                    </a>
                @endforeach
            </div>
            <button type="button" class="ts-catstrip__nav ts-catstrip__nav--next" data-dir="1" aria-label="الأقسام التالية" hidden><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        </div>
    </div>
</nav>
<script>
(() => {
    // شريط الأقسام: أسهم تمرير + سحب بالفأرة + حواف متلاشية. في RTL قيمة scrollLeft صفر عند البداية وسالبة باتجاه النهاية.
    const el = document.querySelector('[data-ts-catstrip]');
    if (!el) return;
    const wrap = el.closest('[data-ts-catstrip-wrap]');
    const prev = wrap.querySelector('.ts-catstrip__nav--prev');
    const next = wrap.querySelector('.ts-catstrip__nav--next');
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // اتجاه الصفحة يتغير مع اللغة (عربي RTL، إنجليزي/تركي LTR)
    const isRtl = () => getComputedStyle(el).direction === 'rtl';

    const sync = () => {
        const rtl = isRtl();
        const max = el.scrollWidth - el.clientWidth, pos = Math.abs(el.scrollLeft);
        const overflow = max > 2, atStart = pos <= 2, atEnd = pos >= max - 2;
        // أقنعة التلاشي مكتوبة بالجهة الفعلية: fade-start = الحافة اليمنى، fade-end = اليسرى
        el.classList.toggle(rtl ? 'fade-start' : 'fade-end', overflow && !atStart);
        el.classList.toggle(rtl ? 'fade-end' : 'fade-start', overflow && !atEnd);
        prev.hidden = !overflow || atStart;
        next.hidden = !overflow || atEnd;
        // الأسهم تشير لجهة الحركة الفعلية
        prev.querySelector('i').className = 'bi ' + (rtl ? 'bi-chevron-right' : 'bi-chevron-left');
        next.querySelector('i').className = 'bi ' + (rtl ? 'bi-chevron-left' : 'bi-chevron-right');
    };
    [prev, next].forEach((btn) => btn.addEventListener('click', () => {
        // نحو النهاية: قيم سالبة في RTL وموجبة في LTR
        const sign = isRtl() ? -1 : 1;
        el.scrollBy({ left: sign * Number(btn.dataset.dir) * Math.max(160, el.clientWidth * 0.75), behavior: reduce ? 'auto' : 'smooth' });
    }));

    // السحب بالفأرة على سطح المكتب، دون كسر النقر على الروابط
    let down = false, dragged = false, startX = 0, startScroll = 0;
    el.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse' || e.button !== 0) return;
        down = true; dragged = false; startX = e.clientX; startScroll = el.scrollLeft;
    });
    el.addEventListener('pointermove', (e) => {
        if (!down) return;
        const dx = e.clientX - startX;
        if (!dragged && Math.abs(dx) > 5) { dragged = true; el.classList.add('is-dragging'); el.setPointerCapture(e.pointerId); }
        if (dragged) el.scrollLeft = startScroll - dx;
    });
    const end = () => { if (!down) return; down = false; el.classList.remove('is-dragging'); };
    el.addEventListener('pointerup', end);
    el.addEventListener('pointercancel', end);
    el.addEventListener('click', (e) => { if (dragged) { e.preventDefault(); e.stopPropagation(); dragged = false; } }, true);
    el.addEventListener('dragstart', (e) => e.preventDefault());

    el.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);
    if (window.ResizeObserver) new ResizeObserver(sync).observe(el);
    // التنسيقات تُعرَّف بعد هذا السكربت في الملف، لذا نبدأ بعد تحليل الصفحة
    const init = () => {
        const active = el.querySelector('.ts-catbub.is-active');
        if (active) { const a = active.getBoundingClientRect(), b = el.getBoundingClientRect(); el.scrollLeft += (a.left + a.width / 2) - (b.left + b.width / 2); }
        sync();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
@endif

<link rel="stylesheet" href="{{ asset('css/ts-header.css') }}?v={{ @filemtime(public_path('css/ts-header.css')) }}">
