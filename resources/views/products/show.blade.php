@extends('layouts.app')

@section('title', $product->name . ' - تريندشام')

@section('content')
<div class="container mb-3">
    <nav class="small text-muted">
        <a href="{{ route('home') }}" class="text-decoration-none">الرئيسية</a> /
        <a href="{{ route('categories.show', $product->category) }}" class="text-decoration-none" translate="no">{{ $product->category->name }}</a> /
        <span translate="no">{{ $product->name }}</span>
    </nav>
</div>

@php
    $variants = $product->variants ?? collect();
    $hasVariants = $variants->isNotEmpty();
    $colors = $variants->whereNotNull('color')->unique('color');
    $sizes = $variants->whereNotNull('size')->unique('size');
    $variantMap = $variants->map(fn($v) => [
        'id' => $v->id, 'color' => $v->color, 'color_name' => $v->color_name,
        'size' => $v->size, 'stock_quantity' => $v->stock_quantity,
        'price_adjustment' => $v->price_adjustment, 'effective_price' => $v->effective_price,
    ])->values();
@endphp
<div class="container mb-5">
    <div class="row g-4">
        <div class="col-md-6">
            @if ($product->images->isNotEmpty())
                <div class="ts-thumb mb-2">
                    <img id="product-main-image" src="{{ asset('storage/'.$product->images->first()->path) }}" alt="{{ $product->name }}" class="w-100 h-100" style="object-fit:cover;" data-base-src="{{ asset('storage/'.$product->images->first()->path) }}">
                </div>
                <div class="d-flex gap-2 flex-wrap" id="product-thumbnails">
                    @foreach ($product->images as $image)
                        <button type="button"
                                class="ts-thumb-sm product-image-thumbnail {{ $loop->first ? 'is-active' : '' }}"
                                style="width:64px;"
                                data-image-url="{{ asset('storage/'.$image->path) }}"
                                data-color="{{ $image->color ?? '' }}"
                                aria-label="عرض الصورة {{ $loop->iteration }}"
                                aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                            <img src="{{ $image->thumb_url }}" alt="" loading="lazy" class="w-100 h-100" style="object-fit:cover;">
                        </button>
                    @endforeach
                </div>
            @else
                <div class="ts-thumb"><i class="bi bi-box-seam"></i></div>
            @endif
        </div>

        <div class="col-md-6">
            <h1 class="h3 mb-2" translate="no">{{ $product->name }}</h1>
            <p class="text-muted small mb-3">
                يبيعه:
                <a href="{{ route('stores.show', $product->vendor) }}" class="fw-bold text-decoration-none">
                    <span translate="no">{{ $product->vendor->store_name }}</span>
                </a>
            </p>

            @if ($product->reviews_count > 0)
                <div class="mb-3">
                    @for ($i = 1; $i <= 5; $i++)
                        <i class="bi {{ $i <= round($product->average_rating) ? 'bi-star-fill' : 'bi-star' }}" style="color: var(--ts-gold);"></i>
                    @endfor
                    <span class="small text-muted">{{ $product->average_rating }} ({{ $product->reviews_count }} تقييم)</span>
                </div>
            @endif

            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="ts-price" id="product-display-price">{{ $product->formatted_price }}</span>
                @if ($product->discount_percentage)
                    <span class="ts-price-old">{{ $product->formatted_compare_at_price }}</span>
                    <span class="ts-badge-discount">-{{ $product->discount_percentage }}%</span>
                @endif
            </div>

            @if ($hasVariants)
                @if ($colors->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label small fw-bold d-block">اللون:</label>
                        <div class="d-flex flex-wrap gap-2" id="variant-colors">
                            @foreach ($colors as $v)
                                @php $hex = \App\Support\ProductOptions::hexFor($v->color, $v->color_name); @endphp
                                <button type="button" class="variant-color-btn btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 {{ $loop->first ? 'active' : '' }}"
                                        data-color="{{ $v->color }}" data-color-name="{{ $v->color_name ?? $v->color }}" aria-label="{{ $v->color_name ?? $v->color }}">
                                    @if ($hex)
                                        <span style="width:16px;height:16px;border-radius:50%;background:{{ $hex }};border:1px solid #ddd;display:inline-block;"></span>
                                    @endif
                                    <span>{{ $v->color_name ?? $v->color }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if ($sizes->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label small fw-bold d-block">المقاس:</label>
                        <div class="d-flex flex-wrap gap-2" id="variant-sizes">
                            @foreach ($sizes as $v)
                                <button type="button" class="variant-size-btn btn btn-outline-secondary btn-sm {{ $loop->first ? 'active' : '' }}" data-size="{{ $v->size }}">{{ $v->size }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
                <p id="variant-stock" class="small mb-3"></p>
            @elseif ($product->stock_quantity > 0)
                <p class="text-success small mb-3"><i class="bi bi-check-circle-fill"></i> متوفر بالمخزون ({{ $product->stock_quantity }})</p>
            @else
                <p class="text-danger small mb-3"><i class="bi bi-x-circle-fill"></i> غير متوفر حالياً</p>
            @endif

            @include('partials.loyalty-earn-hint', ['amount' => $product->effective_price])
            @include('partials.store-coupons')

            @auth
                @if (auth()->user()->role === 'customer')
                    <div class="d-flex gap-2 mb-2">
                        <form action="{{ route('customer.cart.store') }}" method="POST" class="flex-grow-1" id="add-to-cart-form">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="variant_id" id="selected-variant-id" value="">
                            <button id="add-to-cart-btn" class="btn btn-buy w-100" {{ !$hasVariants && $product->stock_quantity <= 0 ? 'disabled' : '' }}>
                                <i class="bi bi-bag-plus"></i> أضف إلى السلة
                            </button>
                        </form>
                        <form action="{{ route('customer.favorites.toggle', $product) }}" method="POST">
                            @csrf
                            <button class="btn btn-outline-danger" title="أضف للمفضلة">
                                <i class="bi {{ auth()->user()->hasFavorited($product->id) ? 'bi-heart-fill' : 'bi-heart' }}"></i>
                            </button>
                        </form>
                    </div>
                @else
                    <p class="text-muted small">الشراء متاح لحسابات العملاء فقط.</p>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-buy w-100 mb-2 d-block text-center text-decoration-none">
                    <i class="bi bi-bag-plus"></i> سجّل دخولك للشراء
                </a>
            @endauth

            @if ($product->description)
                <hr>
                <h2 class="h6">الوصف</h2>
                <p class="text-muted" translate="no">{{ $product->description }}</p>
            @endif
        </div>
    </div>
</div>

@if ($product->reviews->isNotEmpty())
<div class="container mb-5">
    <h2 class="h5 mb-3">آراء العملاء</h2>
    <div class="row g-3">
        @foreach ($product->reviews as $review)
            <div class="col-md-6">
                <div class="ts-product-card p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <strong class="small">{{ $review->user->name }}</strong>
                        <span>
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}" style="color: var(--ts-gold); font-size:.8rem;"></i>
                            @endfor
                        </span>
                    </div>
                    @if ($review->comment)
                        <p class="small text-muted mb-0">{{ $review->comment }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- الأسئلة والأجوبة (Soru-Cevap) --}}


{{-- الكوبونات صارت في محفظة العميل (كوبوناتي) وتُطبَّق تلقائياً عند الدفع --}}

<div id="product-questions" class="container mb-5">
    <h2 class="h5 mb-3">الأسئلة والأجوبة</h2>

    @if (session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
    @endif

    {{-- نموذج طرح سؤال جديد --}}
    @auth
        <div class="ts-product-card p-3 mb-3">
            <form action="{{ route('customer.questions.store', $product) }}" method="POST">
                @csrf
                <label for="f-question" class="form-label small fw-bold">اطرح سؤالاً عن هذا المنتج</label>
                <textarea id="f-question" name="question" rows="2" class="form-control form-control-sm @error('question') is-invalid @enderror"
                    placeholder="اكتب سؤالك هنا (5 أحرف على الأقل)..." maxlength="1000" required>{{ old('question') }}</textarea>
                <div class="text-end mt-2">
                    <button type="submit" class="btn btn-sm text-white" style="background:var(--ts-emerald);">
                        <i class="bi bi-send"></i> إرسال السؤال
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="ts-product-card p-3 mb-3 small text-muted">
            <i class="bi bi-lock"></i>
            <a href="{{ route('login') }}">سجّل الدخول</a> لطرح سؤال أو الإجابة على المنتج.
        </div>
    @endauth

    {{-- قائمة الأسئلة والأجوبة --}}
    @forelse ($product->questions as $question)
        <div class="ts-product-card p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex" style="gap:.6rem;">
                    <i class="bi bi-question-circle text-warning"></i>
                    <div>
                        <p class="small fw-bold mb-1">{{ $question->question }}</p>
                        <span class="text-muted" style="font-size:.72rem;">{{ $question->user->name }}</span>
                    </div>
                </div>
                @if (auth()->check() && (auth()->id() === $question->user_id || auth()->user()->isAdmin()))
                    <form action="{{ route('customer.questions.destroy', $question) }}" method="POST"
                        data-confirm="حذف هذا السؤال؟">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-link text-danger p-0" aria-label="حذف السؤال" title="حذف السؤال"><i class="bi bi-trash" aria-hidden="true"></i></button>
                    </form>
                @endif
            </div>

            {{-- الأجوبة --}}
            @foreach ($question->answers as $answer)
                <div class="p-2 mt-2 rounded"
                    style="background: {{ $answer->is_seller_answer ? 'var(--ts-gold-light)' : '#f8f9fa' }}; border-inline-start:3px solid {{ $answer->is_seller_answer ? 'var(--ts-gold)' : '#dee2e6' }};">
                    <div class="d-flex align-items-center mb-1" style="gap:.4rem;">
                        <i class="bi {{ $answer->is_seller_answer ? 'bi-shop' : 'bi-person' }}"
                            style="font-size:.8rem; color:{{ $answer->is_seller_answer ? 'var(--ts-emerald)' : 'var(--ts-muted)' }};"></i>
                        <strong style="font-size:.75rem; color:{{ $answer->is_seller_answer ? 'var(--ts-emerald)' : 'var(--ts-muted)' }};">
                            {{ $answer->is_seller_answer ? 'البائع' : $answer->user->name }}
                        </strong>
                    </div>
                    <p class="small mb-0">{{ $answer->answer }}</p>
                </div>
            @endforeach

            {{-- نموذج الإجابة --}}
            @auth
                <form action="{{ route('customer.questions.answer', $question) }}" method="POST" class="mt-2">
                    @csrf
                    <div class="input-group input-group-sm">
                        <input type="text" name="answer" class="form-control" placeholder="أجب على هذا السؤال..." maxlength="1000" required>
                        <button class="btn btn-outline-secondary" type="submit">أجب</button>
                    </div>
                </form>
            @endauth
        </div>
    @empty
        <div class="ts-product-card p-4 text-center text-muted small">
            <i class="bi bi-chat-square-dots fs-4 d-block mb-2"></i>
            لا توجد أسئلة بعد. كن أول من يسأل عن هذا المنتج!
        </div>
    @endforelse
</div>

@if ($similar->isNotEmpty())
<div class="container mb-5">
    <h2 class="h5 mb-3">منتجات مشابهة</h2>
    <div class="row g-3">
        @foreach ($similar as $related)
            <div class="col-6 col-md-2">
                <a href="{{ route('products.show', $related) }}" class="ts-product-card d-block text-decoration-none text-reset">
                    <div class="ts-product-thumb">
                        @if ($related->primaryImage)
                            <img src="{{ $related->primaryImage->thumb_url }}" alt="{{ $related->name }}" loading="lazy" class="w-100 h-100" style="object-fit:cover;">
                        @else
                            <i class="bi bi-box-seam"></i>
                        @endif
                    </div>
                    <div class="p-2">
                        <p class="small mb-1 text-truncate" translate="no">{{ $related->name }}</p>
                        <span class="ts-price" style="font-size:1rem;">{{ $related->formatted_price }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endif

@if ($popular->isNotEmpty())
<div class="container mb-5">
    <h2 class="h5 mb-3">الأكثر مبيعاً</h2>
    <div class="row g-3">
        @foreach ($popular as $suggested)
            <div class="col-6 col-md-2">
                <a href="{{ route('products.show', $suggested) }}" class="ts-product-card d-block text-decoration-none text-reset">
                    <div class="ts-product-thumb">
                        @if ($suggested->primaryImage)
                            <img src="{{ $suggested->primaryImage->thumb_url }}" alt="{{ $suggested->name }}" loading="lazy" class="w-100 h-100" style="object-fit:cover;">
                        @else
                            <i class="bi bi-box-seam"></i>
                        @endif
                    </div>
                    <div class="p-2">
                        <p class="small mb-1 text-truncate" translate="no">{{ $suggested->name }}</p>
                        <span class="ts-price" style="font-size:1rem;">{{ $suggested->formatted_price }}</span>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endif

<script>
    const productMainImage = document.getElementById('product-main-image');
    const thumbnails = document.querySelectorAll('.product-image-thumbnail');
    const variants = @json($variantMap ?? []);
    const basePrice = {{ (int) $product->price }};
    const dealPrice = {{ $product->activeDailyDeal ? (int) round($product->activeDailyDeal->discounted_price) : 'null' }};
    const fmt = (minor) => new Intl.NumberFormat('en-US').format(minor / 100) + ' ' + @json(__('ل.س'));

    function setMainImage(url) {
        if (productMainImage && url) productMainImage.src = url;
        thumbnails.forEach((item) => {
            const isActive = item.dataset.imageUrl === url;
            item.classList.toggle('is-active', isActive);
            item.setAttribute('aria-pressed', String(isActive));
        });
    }

    thumbnails.forEach((thumbnail) => {
        thumbnail.addEventListener('click', () => setMainImage(thumbnail.dataset.imageUrl));
    });

    const colorBtns = document.querySelectorAll('.variant-color-btn');
    const sizeBtns = document.querySelectorAll('.variant-size-btn');
    const variantIdInput = document.getElementById('selected-variant-id');
    const stockEl = document.getElementById('variant-stock');
    const priceEl = document.getElementById('product-display-price');
    const cartBtn = document.getElementById('add-to-cart-btn');

    function selectedColor() {
        const active = document.querySelector('.variant-color-btn.active');
        return active ? active.dataset.color : null;
    }
    function selectedSize() {
        const active = document.querySelector('.variant-size-btn.active');
        return active ? active.dataset.size : null;
    }

    function updateVariant() {
        if (!variants.length) return;
        const color = selectedColor();
        const size = selectedSize();
        let match = variants.find(v => (!color || v.color === color) && (!size || v.size === size));
        if (!match && color) match = variants.find(v => v.color === color);
        if (!match) match = variants[0];

        // Filter images by color
        if (color) {
            const colorThumb = [...thumbnails].find(t => t.dataset.color === color);
            if (colorThumb) setMainImage(colorThumb.dataset.imageUrl);
        }

        // Availability per size (when color selected)
        if (color && sizeBtns.length) {
            sizeBtns.forEach(btn => {
                const v = variants.find(x => x.color === color && x.size === btn.dataset.size);
                const available = v && v.stock_quantity > 0;
                btn.disabled = !available;
                btn.classList.toggle('disabled', !available);
                btn.title = available ? '' : @json(__('غير متوفر'));
            });
        }

        if (variantIdInput) variantIdInput.value = match ? match.id : '';
        if (stockEl) {
            if (match && match.stock_quantity > 0) {
                stockEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + @json(__('متوفر بالمخزون (:n)')).replace(':n', match.stock_quantity);
                stockEl.className = 'text-success small mb-3';
            } else {
                stockEl.innerHTML = '<i class="bi bi-x-circle-fill"></i> ' + @json(__('غير متوفر حالياً'));
                stockEl.className = 'text-danger small mb-3';
            }
        }
        if (priceEl && match) {
            const effective = dealPrice !== null ? (dealPrice + (match.price_adjustment || 0)) : (basePrice + (match.price_adjustment || 0));
            priceEl.textContent = fmt(effective);
        }
        if (cartBtn) cartBtn.disabled = !match || match.stock_quantity <= 0;
    }

    colorBtns.forEach(btn => btn.addEventListener('click', () => {
        colorBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        updateVariant();
    }));
    sizeBtns.forEach(btn => btn.addEventListener('click', () => {
        if (btn.disabled) return;
        sizeBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        updateVariant();
    }));

    if (variants.length) updateVariant();
    const form = document.getElementById('add-to-cart-form');
    if (form) form.addEventListener('submit', (e) => {
        if (variants.length && !variantIdInput.value) {
            e.preventDefault();
            window.tsAlert(@json(__('يرجى اختيار اللون/المقاس قبل الإضافة إلى السلة.')));
        }
    });
</script>
@endsection
