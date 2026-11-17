@php
    $fmt = fn ($minor) => number_format((int) $minor / 100, 0, '.', ',').' ل.س';
@endphp

<!-- Quick View Modal -->
<div class="modal fade" id="tsQuickViewModal" tabindex="-1" aria-hidden="true" aria-labelledby="tsQuickViewModalLabel">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-3">
                <h5 class="modal-title fw-bold" id="tsQuickViewModalLabel" translate="no">{{ __('معاينة المنتج') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- الصور -->
                    <div class="col-md-5 border-end bg-white">
                        <div id="qvMedia" class="position-relative h-100 w-100" style="min-height:360px;">
                            <div id="qvMainImage" class="d-flex align-items-center justify-content-center h-100 w-100 bg-light">
                                <i class="bi bi-box-seam text-muted" style="font-size:4rem;"></i>
                            </div>
                            <div id="qvThumbs" class="d-flex flex-wrap gap-2 p-3 position-absolute bottom-0 start-0 end-0 z-3" style="max-height:120px; overflow-y:auto; background:rgba(255,255,255,.85); border-top:1px solid #eee;">
                                <!-- تُملأ عبر JS -->
                            </div>
                        </div>
                    </div>
                    <!-- التفاصيل -->
                    <div class="col-md-7 p-4">
                        <div id="qvContent">
                            <div class="text-center py-5 text-muted">
                                <div class="spinner-border text-secondary" role="status"></div>
                                <p class="mt-2 small">جاري تحميل تفاصيل المنتج...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const modalEl = document.getElementById('tsQuickViewModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl);

    const qvMedia   = document.getElementById('qvMedia');
    const qvMain    = document.getElementById('qvMainImage');
    const qvThumbs  = document.getElementById('qvThumbs');
    const qvContent = document.getElementById('qvContent');

    let currentProduct = null;
    let currentImgIdx  = 0;

    function render(product){
        currentProduct = product;
        const deal = product.active_daily_deal;
        const basePrice  = deal ? deal.discounted_price : product.price;
        const oldPrice   = deal ? product.price : product.compare_at_price;
        const hasDisc    = oldPrice && oldPrice > basePrice;
        const discPct    = deal ? deal.discount_percentage : product.discount_percentage;
        const inStock    = product.in_stock !== false && product.stock_quantity > 0;
        const fmt = (amount) => Math.round(Number(amount) || 0).toLocaleString('en-US')+' '+@json(__('ل.س'));
        const hasVariants = (product.variants || []).length > 0;

        // الصور
        const images = (product.images || []).slice(0, 5);
        const primaryUrl = product.primary_image ? product.primary_image.url : null;
        const firstUrl = images.length ? images[0].url : (primaryUrl || '');

        let mainHtml = '';
        if (firstUrl) {
            mainHtml = `<img src="${firstUrl}" class="w-100 h-100" style="object-fit:contain;" alt="${escapeHtml(product.name)}">`;
        } else {
            mainHtml = `<i class="bi bi-box-seam text-muted" style="font-size:4rem;"></i>`;
        }
        qvMain.innerHTML = mainHtml;
        qvMain.className = 'd-flex align-items-center justify-content-center h-100 w-100 bg-light position-relative';

        // الثumbnails
        let thumbsHtml = '';
        images.forEach((img, idx)=>{
            const active = idx === 0 ? 'border border-2 border-ts-emerald rounded' : '';
            thumbsHtml += `
                <img src="${img.url}" class="qv-thumb ${active}" style="width:56px;height:56px;object-fit:cover;border-radius:8px;cursor:pointer;opacity:${idx===0?1:.65};" alt="" onclick="tsQuickViewSetImg(${idx})">
            `;
        });
        qvThumbs.innerHTML = thumbsHtml;

        window.tsQuickViewSetImg = (idx)=>{
            currentImgIdx = idx;
            const img = images[idx];
            if (!img) return;
            qvMain.innerHTML = `<img src="${img.url}" class="w-100 h-100" style="object-fit:contain;" alt="${escapeHtml(product.name)}">`;
            [...qvThumbs.children].forEach((el, i)=>{
                el.style.opacity = i===idx ? '1' : '.65';
                el.classList.toggle('border', i===idx);
                el.classList.toggle('border-2', i===idx);
                el.classList.toggle('border-ts-emerald', i===idx);
                el.classList.toggle('rounded', i===idx);
            });
        };

        // المحتوى
        const dealBadge = deal ?
            `<span class="ts-badge-discount d-inline-block mb-2">{{ __('تخفيض اليوم') }} · -${Math.round(discPct)}%</span>` : '';
        const priceHtml = `
            <span class="ts-price d-block fw-900">${fmt(basePrice)}</span>
            ${hasDisc ? `<del class="ts-price-old d-block">${fmt(oldPrice)}</del>` : ''}
            ${discPct ? `<span class="ts-badge-discount">-${Math.round(discPct)}%</span>` : ''}
        `;

        const colorsHtml = (product.images || [])
            .filter(img => img.color_hex)
            .slice(0, 6)
            .map(img => {
                return `<button type="button" class="qv-color" style="background:${escapeHtml(img.color_hex)};" title="${escapeHtml(img.color_name)}" aria-label="${escapeHtml(img.color_name || {{ Js::from(__('اللون')) }})}"></button>`;
            }).join('');

        qvContent.innerHTML = `
            <div>
                ${dealBadge}
                <h2 class="h4 mb-1 fw-bold" translate="no">${escapeHtml(product.name)}</h2>
                <p class="text-muted small mb-3">
                    {{ __('يبيعه:') }} <strong>${escapeHtml(product.vendor?.store_name||@json(__('تريندشام')))}</strong>
                </p>
                <div class="mb-3">${priceHtml}</div>
                <div class="mb-3">
                    <span class="${inStock?'text-success':'text-danger'} fw-bold small">
                        ${inStock ? @json(__('متوفر بالمخزون (:n)')).replace(':n', product.stock_quantity) : @json(__('غير متوفر حالياً'))}
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold d-block mb-1">{{ __('اللون') }}</label>
                    <div class="d-flex gap-2">${colorsHtml || '<span class="text-muted small">' + @json(__('لا توجد ألوان متوفرة')) + '</span>'}</div>
                </div>
                <div class="mb-3">
                    <label for="qvQty" class="form-label small fw-bold d-block mb-1">{{ __('الكمية') }}</label>
                    <div class="d-flex align-items-center gap-2" style="width:110px;">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle qv-qty-minus" ${!inStock?'disabled':''}>−</button>
                        <input type="text" class="form-control form-control-sm text-center border-0 bg-transparent fw-bold" id="qvQty" value="1" readonly>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle qv-qty-plus" ${!inStock?'disabled':''}>＋</button>
                    </div>
                </div>
                ${hasVariants ? `<a href="{{ route('products.show', '__SLUG__') }}" class="btn btn-buy w-100 mb-2"><i class="bi bi-palette"></i> ${@json(__('اختر اللون/المقاس'))}</a>` : `
                <form action="{{ route('customer.cart.store') }}" method="POST" id="qvAddForm">
                    @csrf
                    <input type="hidden" name="product_id" value="${product.id}">
                    <input type="hidden" name="quantity" id="qvQtyInput" value="1">
                    <button type="submit" class="btn btn-buy w-100 mb-2" ${!inStock?'disabled':''}>
                        <i class="bi bi-bag-plus"></i> ${inStock ? @json(__('أضف إلى السلة')) : @json(__('نفد'))}
                    </button>
                </form>`}
                <a href="{{ route('products.show', '__SLUG__') }}" class="btn btn-outline-secondary w-100">{{ __('عرض التفاصيل الكاملة') }}</a>
            </div>
        `.replaceAll('__SLUG__', encodeURIComponent(product.slug));

        // الكمية
        const minus = qvContent.querySelector('.qv-qty-minus');
        const plus  = qvContent.querySelector('.qv-qty-plus');
        const qtyIn = document.getElementById('qvQty');
        const qtyH  = document.getElementById('qvQtyInput');
        let qty = 1;
        if (minus && plus && qtyH) {
            minus.onclick = ()=>{ if(qty>1){qty--; qtyIn.value=qty; qtyH.value=qty;} };
            plus.onclick  = ()=>{ if(qty<product.stock_quantity){qty++; qtyIn.value=qty; qtyH.value=qty;} };
        }
    }

    function escapeHtml(t){ return String(t||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])); }

    // فتح النافذة عند النقر على زر المعاينة السريعة
    document.addEventListener('click', async (e)=>{
        const btn = e.target.closest('[data-ts-quick-view]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const id = btn.dataset.tsQuickView;
        if (!id) return;

        qvContent.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div><p class="mt-2 small">{{ __('جاري التحميل...') }}</p></div>`;
        modal.show();

        try {
            const res = await fetch(@json(url('api/v1/products')) + '/' + encodeURIComponent(id), { headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} });
            if (!res.ok) throw new Error(@json(__('فشل التحميل')));
            const json = await res.json();
            render(json.data || json);
        } catch (err){
            qvContent.innerHTML = `<div class="text-center py-5 text-danger"><p class="small">{{ __('تعذّر تحميل المنتج. حاول مجدداً.') }}</p></div>`;
        }
    });

    modalEl.addEventListener('hidden.bs.modal', ()=>{
        qvContent.innerHTML = `<div class="text-center py-5 text-muted"><div class="spinner-border text-secondary" role="status"></div><p class="mt-2 small">{{ __('جاري تحميل تفاصيل المنتج...') }}</p></div>`;
    });
})();
</script>
<style>
    .ts-badge-discount{background:#B23A2B;color:#fff;font-weight:700;border-radius:6px;padding:.2rem .6rem;font-size:.8rem;}
    .ts-price{color:#073D30;font-weight:900;}
    .ts-price-old{color:#6B7A72;text-decoration:line-through;font-size:.85rem;}
    .qv-thumb{transition:transform .12s ease,opacity .12s ease;}
    .qv-thumb:hover{opacity:1 !important;transform:scale(1.05);}
    .qv-color{width:26px;height:26px;border-radius:50%;border:1px solid #ddd;cursor:pointer;padding:0;display:inline-flex;align-items:center;justify-content:center;}
    .qv-color::after{content:'';display:block;width:10px;height:10px;border-radius:50%;background:#fff;opacity:0;transition:opacity .12s;}
    .qv-color:active::after{opacity:1;}
    #tsQuickViewModal .rounded-circle{border-radius:50% !important;width:32px;height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;line-height:1;}
</style>
@endpush
