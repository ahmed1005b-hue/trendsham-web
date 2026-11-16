@csrf

<div class="row g-3">
    <div class="col-md-8">
        <label for="product-name" class="form-label">اسم المنتج</label>
        <input id="product-name" type="text" name="name" autocomplete="name" class="form-control" value="{{ old('name', $product->name ?? '') }}" required>
        @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="product-category" class="form-label">القسم</label>
        <select id="product-category" name="category_id" class="form-select" required translate="no">
            <option value="">اختر القسم</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ __('categories.'.$category->name) }}</option>
            @endforeach
        </select>
        @error('category_id') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="product-description" class="form-label mb-0">الوصف</label>
            <button type="button" id="ts-describe-btn" class="btn btn-sm btn-outline-success"
                    data-url="{{ route('seller.assistant.describe') }}">
                <i class="bi bi-stars"></i> اكتب الوصف تلقائياً
            </button>
        </div>
        <textarea id="product-description" name="description" autocomplete="off" class="form-control" rows="6">{{ old('description', $product->description ?? '') }}</textarea>
        <div id="ts-describe-hint" class="form-text">مسودة من الاسم والقسم والألوان والمقاسات — راجعها وعدّلها قبل الحفظ.</div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const btn = document.getElementById('ts-describe-btn');
        const box = document.getElementById('product-description');
        const hint = document.getElementById('ts-describe-hint');
        if (!btn || !box) return;

        btn.addEventListener('click', async () => {
            const name = (document.getElementById('product-name')?.value || '').trim();
            if (!name) { hint.textContent = @json(__('اكتب اسم المنتج أولاً.')); return; }
            if (box.value.trim() && !(await window.tsConfirm(@json(__('سيُستبدل الوصف الحالي بالمسودة. متابعة؟'))))) return;

            const values = (sel) => [...new Set([...document.querySelectorAll(sel)].map((i) => i.value.trim()).filter(Boolean))];
            btn.disabled = true;
            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        name,
                        category_id: document.getElementById('product-category')?.value || null,
                        colors: values('input[name="variant_color_name[]"]'),
                        sizes: values('input[name="variant_size[]"]'),
                    }),
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || '');
                box.value = data.description;
                hint.textContent = @json(__('تمت كتابة المسودة ✅ راجعها وعدّلها قبل الحفظ.'));
            } catch (e) {
                hint.textContent = e.message || @json(__('تعذّر كتابة الوصف، حاول مرة ثانية.'));
            } finally {
                btn.disabled = false;
            }
        });
    })();
    </script>
    @endpush

    <div class="col-md-3">
        <label for="product-sku" class="form-label">رمز المنتج (SKU)</label>
        <input id="product-sku" type="text" name="sku" autocomplete="off" class="form-control" value="{{ old('sku', $product->sku ?? '') }}" required>
        @error('sku') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label for="product-price" class="form-label">السعر (ل.س)</label>
        <input id="product-price" type="number" step="0.01" name="price" autocomplete="off" class="form-control" value="{{ old('price', isset($product) ? $product->price / 100 : '') }}" required>
        @error('price') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label for="product-compare-at-price" class="form-label">السعر قبل الخصم (اختياري)</label>
        <input id="product-compare-at-price" type="number" step="0.01" name="compare_at_price" autocomplete="off" class="form-control" value="{{ old('compare_at_price', isset($product) && $product->compare_at_price ? $product->compare_at_price / 100 : '') }}">
    </div>
    <div class="col-md-3">
        <label for="product-status" class="form-label">الحالة</label>
        <select id="product-status" name="status" class="form-select">
            @foreach (['draft' => 'مسودة', 'active' => 'نشط', 'inactive' => 'غير نشط'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $product->status ?? 'draft') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label for="product-stock" class="form-label">الكمية بالمخزون</label>
        <input id="product-stock" type="number" name="stock_quantity" autocomplete="off" class="form-control" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required>
        <div id="product-stock-hint" class="form-text" hidden>يُحسب تلقائياً من الألوان والمقاسات</div>
        @error('stock_quantity') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3">
        <label for="product-low-stock-threshold" class="form-label">حد التنبيه لانخفاض المخزون</label>
        <input id="product-low-stock-threshold" type="number" name="low_stock_threshold" autocomplete="off" class="form-control" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}">
    </div>

    @include('seller.products._options')

    <div class="col-12">
        <label class="form-label fw-semibold">{{ isset($product) && $product->images->count() ? 'إضافة صور جديدة' : 'صور المنتج' }}</label>
        <div class="border rounded-3 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <div class="form-text mb-0">يمكن اختيار عدة صور دفعة واحدة (JPG, PNG, WEBP — حتى 4MB للصورة). ستُعرض الصور المصغّرة هنا قبل الحفظ — يمكنك ربط كل صورة بلون (اختياري) لتبديل صورة المنتج حسب اللون.</div>
                <div class="d-flex gap-2">
                    <label for="product-images-input" class="btn btn-outline-primary btn-sm mb-0"><i class="bi bi-images"></i> اختيار صور</label>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearImageSelection" style="display:none;">مسح الاختيار</button>
                </div>
            </div>
            <input id="product-images-input" type="file" name="images[]" class="d-none" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <div id="imagePreviewGrid" class="d-flex flex-wrap gap-2"></div>
            <div id="imagePreviewEmpty" class="text-muted small py-3 text-center border rounded-2" style="border-style:dashed !important;">لم تُختر صور بعد — اضغط «اختيار صور» لإضافة عدة صور.</div>
        </div>
    </div>
</div>

<button type="submit" class="btn btn-primary mt-4">حفظ المنتج</button>

<script>
(function(){
    // image picker preview (accumulate multiple selections, show small thumbs in frame)
    const imgInput=document.getElementById('product-images-input'), grid=document.getElementById('imagePreviewGrid'), empty=document.getElementById('imagePreviewEmpty'), clearBtn=document.getElementById('clearImageSelection');
    let picked=[];
    function syncInput(){ if(!imgInput) return; const dt=new DataTransfer(); picked.forEach(f=>dt.items.add(f)); imgInput.files=dt.files; }
    function renderPreviews(){
        if(!grid||!empty) return; grid.innerHTML='';
        if(!picked.length){ empty.style.display='block'; if(clearBtn) clearBtn.style.display='none'; syncInput(); return; }
        empty.style.display='none'; if(clearBtn) clearBtn.style.display='inline-block';
        picked.forEach((file,idx)=>{
            const card=document.createElement('div'); card.className='border rounded-2 p-1 bg-light'; card.style.width='110px';
            const wrap=document.createElement('div'); wrap.className='position-relative rounded-1 overflow-hidden'; wrap.style.width='100%'; wrap.style.height='78px';
            const img=document.createElement('img'); img.style.width='100%'; img.style.height='100%'; img.style.objectFit='cover'; img.alt=file.name; img.src=URL.createObjectURL(file); img.onload=()=>URL.revokeObjectURL(img.src);
            const btn=document.createElement('button'); btn.type='button'; btn.className='btn btn-danger btn-sm position-absolute top-0 start-0 p-0 d-flex align-items-center justify-content-center'; btn.style.width='20px'; btn.style.height='20px'; btn.style.lineHeight='1'; btn.innerHTML='&times;'; btn.title=@json(__('إزالة')); btn.onclick=()=>{picked.splice(idx,1); renderPreviews();};
            wrap.appendChild(img); wrap.appendChild(btn);
            const c=document.createElement('select'); c.name='image_color['+idx+']'; c.className='form-select form-select-sm mt-1'; c.setAttribute('data-ts-color-select',''); c.setAttribute('aria-label',@json(__('لون الصورة'))); c.dataset.selected=file.__color||''; c.appendChild(new Option(@json(__('بدون لون')),'')); c.addEventListener('change',()=>{file.__color=c.value;});
            const cn=document.createElement('input'); cn.type='hidden'; cn.name='image_color_name['+idx+']'; cn.setAttribute('data-ts-color-name-for',c.name);
            card.appendChild(wrap); card.appendChild(c); card.appendChild(cn); grid.appendChild(card);
        });
        syncInput();
        window.tsProductOptions?.refreshColorSelects();
    }
    if(imgInput) imgInput.addEventListener('change', (e)=>{ const files=Array.from(e.target.files||[]); files.forEach(f=>{ if(!picked.some(p=>p.name===f.name && p.size===f.size && p.lastModified===f.lastModified)) picked.push(f); }); renderPreviews(); });
    if(clearBtn) clearBtn.addEventListener('click', ()=>{ picked=[]; renderPreviews(); });

})();
</script>
