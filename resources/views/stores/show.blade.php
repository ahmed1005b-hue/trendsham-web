@extends('layouts.app')

@section('title', $store->store_name.' - تريندشام')

@section('content')
@php
    $phone = preg_replace('/[^0-9+]/', '', (string) $store->store_contact_phone);
    $whatsappUrl = $store->store_whatsapp_url;
    $instagramUrl = $store->store_instagram_url;
@endphp
@push('styles')
<style>
    .ts-product-zoom { display:block; width:100%; background:transparent; }
    .ts-market-card a:focus-visible, .ts-market-card button:focus-visible, .ts-market-card input:focus-visible { outline:3px solid var(--ts-gold); outline-offset:2px; }
    .ts-filter-panel { background:#fff; border:1px solid #ece4d3; border-radius:12px; }
    .ts-open-state { display:inline-flex; align-items:center; gap:.4rem; border-radius:30px; padding:.35rem .7rem; font-weight:700; }
    .ts-open-state.open { color:#0b5f4a; background:#e2f2e9; } .ts-open-state.closed { color:#9c3128; background:#fbe9e6; }
    .ts-open-state.unknown { color:#59665f; background:#eef0ed; }
</style>
@endpush
@php $storeQuestions = App\Models\ProductQuestion::whereHas('product', fn ($q) => $q->where('vendor_id', $store->id))
    ->with(['user', 'answers.user', 'product'])->latest()->limit(6)->get(); @endphp
<section class="card mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3"><i class="bi bi-question-circle"></i> الأسئلة والأجوبة</h2>
        @forelse ($storeQuestions as $question)
            <div class="border-bottom pb-2 mb-2">
                <p class="mb-1 fw-semibold">{{ $question->question }}</p>
                @foreach ($question->answers as $answer)
                    <p class="mb-1 small text-muted">{{ $answer->answer }}</p>
                @endforeach
            </div>
        @empty
            <p class="text-muted mb-0">لا توجد أسئلة بعد.</p>
        @endforelse
    </div>
</section>
<header class="ts-header py-3">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="{{ route('home') }}" class="ts-logo">تريند<span>شام</span></a>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">متابعة التسوق</a>
    </div>
</header>

<div class="container py-4">
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <section class="card overflow-hidden mb-4">
        @if ($store->store_banner)
            <img src="{{ asset('storage/'.$store->store_banner) }}" alt="{{ __('غلاف :store', ['store' => $store->store_name]) }}" class="w-100"
                 style="height: clamp(150px, 28vw, 280px); object-fit: cover;">
        @else
            <div class="d-flex align-items-center justify-content-center bg-light text-success"
                 role="img" aria-label="{{ __('غلاف :store', ['store' => $store->store_name]) }}"
                 style="height: clamp(150px, 28vw, 280px);">
                <i class="bi bi-shop-window" style="font-size:4rem;"></i>
            </div>
        @endif
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            @if ($store->store_logo)
                <img src="{{ asset('storage/'.$store->store_logo) }}" alt="{{ $store->store_name }}" translate="no"
                     class="rounded-circle" width="80" height="80" style="object-fit: cover;">
            @else
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                     style="width:80px;height:80px;">
                    <i class="bi bi-shop fs-2 text-success"></i>
                </div>
            @endif
            <div class="flex-grow-1" style="flex-basis: 12rem; min-width: 0;">
                <h1 class="h3 mb-1 d-flex align-items-center gap-2 flex-wrap"><span translate="no">{{ $store->store_name }}</span>@if($store->status === 'approved')<span class="badge bg-success" style="font-size:.7rem;font-weight:700"><i class="bi bi-patch-check-fill"></i> بائع موثّق</span>@endif</h1>
                @if ($store->store_description)
                    <p class="text-muted mb-1" translate="no">{{ $store->store_description }}</p>
                @endif
                <span class="small text-muted">{{ $products->total() }} منتج متاح</span>
                <div class="mt-2">
                    <span class="ts-open-state {{ $businessHoursStatus['state'] }}">
                        <i class="bi {{ $businessHoursStatus['state'] === 'open' ? 'bi-clock' : 'bi-clock-history' }}"></i>
                        {{ $businessHoursStatus['label'] }}
                    </span>
                    @if ($businessHoursStatus['next_change_at'])
                        <small class="text-muted ms-1" data-hours-change="{{ $businessHoursStatus['next_change_at'] }}"
                               data-hours-timezone="{{ $store->business_hours_schedule['timezone'] ?? config('app.timezone') }}"></small>
                    @endif
                </div>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-2">
                @auth
                    @if (auth()->user()->role === 'customer')
                        <form method="POST" action="{{ route('stores.follow', $store) }}">
                            @csrf
                            <button class="btn {{ $isFollowed ? 'btn-outline-success' : 'btn-primary' }}">
                                <i class="bi {{ $isFollowed ? 'bi-bell-fill' : 'bi-bell' }}"></i>
                                {{ $isFollowed ? 'تتابع المتجر' : 'تابع المتجر' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('stores.message', $store) }}">
                            @csrf
                            <button class="btn btn-outline-secondary"><i class="bi bi-chat-dots"></i> راسل البائع</button>
                        </form>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-success"><i class="bi bi-bell"></i> سجّل الدخول للمتابعة</a>
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary"><i class="bi bi-chat-dots"></i> راسل البائع</a>
                @endauth
            </div>
        </div>
        @if ($store->store_contact_phone || $whatsappUrl || $instagramUrl || $store->business_hours || $store->shipping_policy || $store->return_policy)
            <div class="card-body border-top">
                <h2 class="h5">معلومات التواصل والخدمة</h2>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @if ($phone)
                        <a class="btn btn-outline-success btn-sm" href="tel:{{ $phone }}"><i class="bi bi-telephone"></i> اتصال</a>
                    @endif
                    @if ($whatsappUrl)
                        <a class="btn btn-sm ts-social-btn ts-social--whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp"></i> واتساب</a>
                    @endif
                    @if ($instagramUrl)
                        <a class="btn btn-sm ts-social-btn ts-social--instagram" href="{{ $instagramUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram"></i> إنستغرام</a>
                    @endif
                </div>
                @if ($store->business_hours)
                    <h3 class="h6">أوقات العمل</h3>
                    <p class="mb-3" style="white-space:pre-line">{{ $store->business_hours }}</p>
                    @if (! $store->business_hours_schedule)
                        <p class="small text-muted">الحالة الحالية غير محددة لأن الأوقات المحفوظة وصف نصي فقط.</p>
                    @endif
                @endif
                <div class="row g-3">
                    @if ($store->shipping_policy)
                        <div class="col-md-6"><h3 class="h6">سياسة الشحن</h3><div style="white-space:pre-line">{{ $store->shipping_policy }}</div></div>
                    @endif
                    @if ($store->return_policy)
                        <div class="col-md-6"><h3 class="h6">الإرجاع والاستبدال</h3><div style="white-space:pre-line">{{ $store->return_policy }}</div></div>
                    @endif
                </div>
            </div>
        @endif
    </section>

    @include('partials.store-coupons')

    @foreach ([
        ['العروض الأكثر مبيعاً', $bestSelling],
        ['وصل حديثاً', $recentArrivals],
    ] as [$sectionTitle, $featuredProducts])
        <section class="mb-4" aria-label="{{ $sectionTitle }}">
            <h2 class="h4 mb-3">{{ $sectionTitle }}</h2>
            @if ($featuredProducts->isEmpty())
                <div class="alert alert-light border">لا توجد منتجات مميزة متاحة حالياً.</div>
            @else
                <div class="row g-3">
                    @foreach ($featuredProducts as $product)
                        <div class="col-6 col-md-4 col-lg-3">@include('stores._product-card', ['product' => $product])</div>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    @if ($stories->isNotEmpty())
        <section id="stories" class="mb-4">
            <h2 class="h4 mb-3">العروض والقصص الحديثة</h2>
            @include('partials.stories', ['stories' => $stories])
        </section>
    @endif

    <section class="ts-filter-panel p-3 mb-3" aria-label="تصفية منتجات المتجر">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2"><label for="filter-price-min" class="form-label small">السعر من</label><input id="filter-price-min" class="form-control form-control-sm" type="number" min="0" step="1" value="{{ request('price_min') }}"></div>
            <div class="col-6 col-md-2"><label for="filter-price-max" class="form-label small">السعر إلى</label><input id="filter-price-max" class="form-control form-control-sm" type="number" min="0" step="1" value="{{ request('price_max') }}"></div>
            <div class="col-6 col-md-2"><label for="filter-rating" class="form-label small">التقييم الأدنى</label><select id="filter-rating" class="form-select form-select-sm"><option value="">أي تقييم</option><option value="3" @selected(request('rating_min') == '3')>٣ نجوم فأكثر</option><option value="4" @selected(request('rating_min') == '4')>٤ نجوم فأكثر</option><option value="4.5" @selected(request('rating_min') == '4.5')>٤.٥ فأكثر</option></select></div>
            <div class="col-6 col-md-3 form-check ms-2"><input id="filter-stock" class="form-check-input" type="checkbox" @checked(request('in_stock'))><label for="filter-stock" class="form-check-label small">المتوفر فقط</label></div>
            <div class="col-12 col-md-2"><button id="clear-filters" class="btn btn-outline-secondary btn-sm w-100">مسح الفلاتر</button></div>
        </div>
    </section>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h4 mb-0">منتجات المتجر</h2><button id="compare-open" class="btn btn-outline-success btn-sm" disabled>{{ __('مقارنة') }} (<span id="compare-count">0</span>)</button>
    </div>
    <div id="store-filter-status" class="small text-muted mb-2" role="status" aria-live="polite"></div>
    <div id="store-product-grid" class="row g-3">
        @forelse ($products as $product)
            <div class="col-6 col-md-4 col-lg-3">@include('stores._product-card', ['product' => $product])</div>
        @empty
            <div class="col-12"><div class="alert alert-light border text-center">لا توجد منتجات متاحة في هذا المتجر حالياً.</div></div>
        @endforelse
    </div>
    <nav id="store-product-pagination" class="mt-4">{{ $products->links('pagination::bootstrap-5') }}</nav>

    @if ($similarProducts->isNotEmpty())
        <section class="mt-5">
            <h2 class="h4 mb-3">منتجات مشابهة في هذا المتجر</h2>
            <div class="row g-3">@foreach ($similarProducts as $product)<div class="col-6 col-md-4 col-lg-3">@include('stores._product-card', ['product' => $product])</div>@endforeach</div>
        </section>
    @endif
</div>

<div class="modal fade" id="storeImageModal" tabindex="-1" aria-labelledby="storeImageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content bg-dark">
        <div class="modal-header border-0"><h2 class="modal-title fs-6 text-white" id="storeImageModalTitle">صورة المنتج</h2><button class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
        <div class="modal-body text-center"><img id="store-modal-image" alt="" class="img-fluid" style="max-height:75vh;object-fit:contain"></div>
    </div></div>
</div>
<div class="modal fade" id="storeCompareModal" tabindex="-1" aria-labelledby="storeCompareTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="storeCompareTitle">مقارنة المنتجات</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div><div class="modal-body" id="store-compare-content"></div></div></div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const storeId = @json($store->id);
    const currency = ' ' + @json(__('ل.س'));
    const grid = document.getElementById('store-product-grid');
    const pagination = document.getElementById('store-product-pagination');
    const status = document.getElementById('store-filter-status');
    const compare = new Map();
    let activePage = 1;
    let requestId = 0;
    const filterValues = () => ({
        price_min: document.getElementById('filter-price-min').value,
        price_max: document.getElementById('filter-price-max').value,
        rating_min: document.getElementById('filter-rating').value,
        in_stock: document.getElementById('filter-stock').checked ? '1' : '',
    });
    const card = product => {
        const url = `/product/${encodeURIComponent(product.slug)}`;
        const col = document.createElement('div'); col.className = 'col-6 col-md-4 col-lg-3';
        const article = document.createElement('article'); article.className = 'ts-market-card ts-store-product h-100';
        Object.assign(article.dataset, { productId: product.id, name: product.name, price: product.price, rating: product.average_rating ?? '', stock: product.stock_quantity, category: product.category?.name ?? @json(__('غير مصنف')), url });
        const wrap = document.createElement('div'); wrap.className = 'ts-market-card__media-wrap';
        const zoom = document.createElement('button'); zoom.type = 'button'; zoom.className = 'ts-product-zoom ts-market-card__media-link border-0 p-0 w-100 bg-transparent';
        const imgs = (product.images && product.images.length ? product.images : (product.primary_image ? [product.primary_image] : [])).slice(0,5);
        zoom.dataset.bsToggle = 'modal'; zoom.dataset.bsTarget = '#storeImageModal'; zoom.dataset.image = imgs[0]?.url || ''; zoom.setAttribute('aria-label', `تكبير صورة ${product.name}`);
        const media = document.createElement('span'); media.className = 'ts-market-card__media ts-card-slider' + (imgs.length > 1 ? ' js-card-slider' : ''); if (imgs.length > 1) media.dataset.interval = '2200';
        if (imgs.length) imgs.forEach((im, idx) => { const img = document.createElement('img'); img.src = im.url; img.alt = product.name; img.loading = 'lazy'; img.decoding = 'async'; img.className = 'ts-card-slide' + (idx === 0 ? ' is-active' : ''); media.append(img); });
        else media.innerHTML = '<span class="ts-market-card__placeholder"><i class="bi bi-image" aria-hidden="true"></i></span>';
        zoom.append(media); wrap.append(zoom);
        if (imgs.length > 1) {
            const prev = document.createElement('button'); prev.type='button'; prev.className='ts-card-nav ts-card-nav--prev'; prev.dataset.dir='-1'; prev.setAttribute('aria-label',@json(__('الصورة السابقة'))); prev.innerHTML='<i class="bi bi-chevron-right"></i>';
            const next = document.createElement('button'); next.type='button'; next.className='ts-card-nav ts-card-nav--next'; next.dataset.dir='1'; next.setAttribute('aria-label',@json(__('الصورة التالية'))); next.innerHTML='<i class="bi bi-chevron-left"></i>';
            const dots = document.createElement('div'); dots.className='ts-card-dots'; dots.setAttribute('aria-hidden','true'); imgs.forEach((_, idx)=>{ const d=document.createElement('span'); if(!idx) d.className='is-active'; dots.append(d); });
            wrap.append(prev, next, dots);
        }
        @auth
        @if (auth()->user()->role === 'customer')
        const form = document.createElement('form'); form.method = 'POST'; form.action = `/customer/favorites/${encodeURIComponent(product.slug)}`; form.className = 'ts-market-card__fav'; form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}">`;
        const favorite = document.createElement('button'); favorite.type = 'submit'; favorite.className = 'ts-market-icon-btn'; favorite.setAttribute('aria-label', @json(__('إضافة :name إلى المفضلة')).replace(':name', product.name)); favorite.innerHTML = '<i class="bi bi-heart"></i>'; form.append(favorite); wrap.append(form);
        @endif
        @endauth
        const compareLabel = document.createElement('label'); compareLabel.className = 'ts-market-card__compare';
        const check = document.createElement('input'); check.type = 'checkbox'; check.className = 'form-check-input ts-compare-check'; check.setAttribute('aria-label', `قارن ${product.name}`);
        const compareText = document.createElement('span'); compareText.textContent = @json(__('قارن')); compareLabel.append(check, compareText); wrap.append(compareLabel); article.append(wrap);
        const body = document.createElement('div'); body.className = 'ts-market-card__body';
        const brand = document.createElement('div'); brand.className = 'ts-market-card__brand'; brand.textContent = @json($store->store_name); body.append(brand);
        const link = document.createElement('a'); link.href = url; link.className = 'ts-market-card__title-link'; const h = document.createElement('h3'); h.className = 'ts-market-card__title'; h.textContent = product.name; link.append(h); body.append(link);
        const ratingRow = document.createElement('div'); ratingRow.className = 'ts-market-card__rating-row';
        ratingRow.innerHTML = product.average_rating == null ? '<span class="ts-market-card__reviews">' + @json(__('لا توجد مراجعات')) + '</span>' : `<span class="ts-market-card__stars"><i class="bi bi-star-fill" aria-hidden="true"></i> ${Number(product.average_rating).toFixed(1)}</span><span class="ts-market-card__reviews">(${Number(product.reviews_count || 0).toLocaleString(document.documentElement.lang || 'ar')})</span>`;
        body.append(ratingRow);
        const meta = document.createElement('div'); meta.className = 'ts-market-card__meta'; meta.innerHTML = `<span class="${product.stock_quantity > 0 ? 'text-success' : 'text-danger'}">${product.stock_quantity > 0 ? @json(__('متوفر')) : @json(__('نفد'))}</span>`; body.append(meta);
        const buy = document.createElement('div'); buy.className = 'ts-market-card__buy';
        const row = document.createElement('div'); row.className = 'ts-market-card__price-row'; const price = document.createElement('span'); price.className = 'ts-market-card__price'; price.textContent = `${Number(product.price).toLocaleString(document.documentElement.lang || 'ar')} ${currency}`; row.append(price); buy.append(row);
        const actions = document.createElement('div'); actions.className = 'ts-market-card__actions';
        const cartForm = document.createElement('form'); cartForm.method = 'POST'; cartForm.action = @json(route('customer.cart.store')); cartForm.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="product_id" value="${product.id}"><input type="hidden" name="quantity" value="1">`;
        const soldOut = Number(product.stock_quantity) < 1;
        const cartBtn = document.createElement('button'); cartBtn.type = 'submit'; cartBtn.className = 'ts-market-cart-btn'; cartBtn.disabled = soldOut; cartBtn.title = soldOut ? @json(__('نفد')) : @json(__('أضف للسلة'));
        cartBtn.innerHTML = `<i class="bi ${soldOut ? 'bi-x-lg' : 'bi-plus-lg'}" aria-hidden="true"></i>`;
        const cartLabel = document.createElement('span'); cartLabel.className = 'visually-hidden'; cartLabel.textContent = soldOut ? @json(__('نفد')) : @json(__('أضف :name إلى السلة')).replace(':name', product.name); cartBtn.append(cartLabel);
        cartForm.append(cartBtn); actions.append(cartForm); buy.append(actions); body.append(buy);
        article.append(body); col.append(article); return col;
    };
    const refresh = async page => {
        const id = ++requestId; activePage = page;
        status.textContent = @json(__('جارٍ تحديث النتائج…')); grid.setAttribute('aria-busy', 'true');
        const params = new URLSearchParams({...filterValues(), page: String(page)});
        for (const [key, value] of [...params]) if (!value) params.delete(key);
        try {
            const response = await fetch(`/store/${storeId}/products?${params}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error(@json(__('تعذّر تحميل المنتجات، حاول مجدداً.')));
            const data = await response.json(); if (id !== requestId) return;
            grid.replaceChildren();
            if (!data.data.length) { const empty = document.createElement('div'); empty.className = 'col-12'; empty.innerHTML = '<div class="alert alert-light border text-center">' + @json(__('لا توجد منتجات تطابق الفلاتر.')) + '</div>'; grid.append(empty); }
            else data.data.forEach(product => grid.append(card(product)));
            pagination.replaceChildren();
            if (data.meta.last_page > 1) {
                for (let p = 1; p <= data.meta.last_page; p++) {
                    const button = document.createElement('button'); button.className = `btn btn-sm ${p === page ? 'btn-success' : 'btn-outline-secondary'} me-1`;
                    button.textContent = String(p); button.setAttribute('aria-label', `الصفحة ${p}`); button.onclick = () => refresh(p); pagination.append(button);
                }
            }
            status.textContent = `${data.meta.total} منتج`;
        } catch (error) { if (id === requestId) status.textContent = error.message; }
        finally { if (id === requestId) grid.removeAttribute('aria-busy'); }
    };
    let filterTimer;
    document.querySelectorAll('#filter-price-min,#filter-price-max').forEach(input => input.addEventListener('input', () => { clearTimeout(filterTimer); filterTimer = setTimeout(() => refresh(1), 350); }));
    document.querySelectorAll('#filter-rating,#filter-stock').forEach(input => input.addEventListener('change', () => refresh(1)));
    pagination.addEventListener('click', event => {
        const link = event.target.closest('a[href]'); if (!link) return;
        const page = Number(new URL(link.href).searchParams.get('page') || 1);
        if (page > 0) { event.preventDefault(); refresh(page); }
    });
    document.getElementById('clear-filters').addEventListener('click', () => { document.getElementById('filter-price-min').value = ''; document.getElementById('filter-price-max').value = ''; document.getElementById('filter-rating').value = ''; document.getElementById('filter-stock').checked = false; refresh(1); });
    document.addEventListener('click', event => {
        const zoom = event.target.closest('.ts-product-zoom');
        if (zoom) { const image = document.getElementById('store-modal-image'); image.src = zoom.dataset.image || ''; image.alt = zoom.getAttribute('aria-label') || @json(__('صورة المنتج')); }
    });
    document.addEventListener('change', event => {
        const check = event.target.closest('.ts-compare-check'); if (!check) return;
        const product = check.closest('.ts-store-product');
        if (check.checked && compare.size >= 4) { check.checked = false; window.tsAlert(@json(__('يمكنك مقارنة أربعة منتجات كحد أقصى.'))); return; }
        if (check.checked) compare.set(product.dataset.productId, product.dataset); else compare.delete(product.dataset.productId);
        document.getElementById('compare-count').textContent = String(compare.size); document.getElementById('compare-open').disabled = compare.size < 2;
    });
    document.getElementById('compare-open').addEventListener('click', () => {
            const table = document.createElement('table'); table.className = 'table table-bordered align-middle'; table.innerHTML = '<thead><tr><th>' + @json(__('الخاصية')) + '</th></tr></thead><tbody></tbody>';
        const head = table.querySelector('thead tr'); [...compare.values()].forEach(item => { const th = document.createElement('th'); th.textContent = item.name; head.append(th); });
        [[@json(__('السعر')), p => `${Number(p.price).toLocaleString(document.documentElement.lang || 'ar')}${currency}`], [@json(__('التقييم')), p => p.rating ? `${p.rating} / 5` : @json(__('لا توجد تقييمات'))], [@json(__('المخزون')), p => Number(p.stock) > 0 ? @json(__('متوفر')) : @json(__('غير متوفر'))], [@json(__('القسم')), p => p.category], [@json(__('التفاصيل')), p => p.url]].forEach(([title, value]) => {
            const tr = document.createElement('tr'); const label = document.createElement('th'); label.textContent = title; tr.append(label);
            [...compare.values()].forEach(item => { const td = document.createElement('td'); if (title === @json(__('التفاصيل'))) { const a = document.createElement('a'); a.href = item.url; a.textContent = @json(__('عرض المنتج')); td.append(a); } else td.textContent = value(item); tr.append(td); }); table.querySelector('tbody').append(tr);
        });
        const responsive = document.createElement('div'); responsive.className = 'table-responsive'; responsive.append(table);
        document.getElementById('store-compare-content').replaceChildren(responsive); bootstrap.Modal.getOrCreateInstance(document.getElementById('storeCompareModal')).show();
    });
    const changeAt = document.querySelector('[data-hours-change]');
    if (changeAt) { const date = new Date(changeAt.dataset.hoursChange); if (!Number.isNaN(date.valueOf())) changeAt.textContent = @json(__('يتغير عند :time')).replace(':time', date.toLocaleTimeString(document.documentElement.lang || 'ar', {hour:'2-digit', minute:'2-digit', timeZone: changeAt.dataset.hoursTimezone})); }
})();
</script>
@endpush
