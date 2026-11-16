{{--
    الألوان والمقاسات في نموذج منتج البائع: يختار البائع الألوان والمقاسات مرة واحدة، ويولّد المحرر
    (public/js/ts-product-options.js) كل تركيبة لون × مقاس ليملأ كميتها، ثم يكتب حقول variant_*[] المخفية
    في #ts-options-inputs عند كل تغيير. نصوص المحرر تُترجم هنا بـ __() لأن القاموس لا يترجم داخل <script>.
--}}
@php
    $optionsState = \App\Support\ProductOptionsEditorState::make($product ?? null, (array) old());
    $optionsConfig = [
        'colors' => array_map(
            fn (array $color): array => ['name' => $color['name'], 'label' => __($color['name']), 'hex' => $color['hex']],
            \App\Support\ProductOptions::colors(),
        ),
        'sizeSystems' => array_map(
            fn (array $system): array => ['key' => $system['key'], 'label' => __($system['name']), 'sizes' => $system['sizes']],
            \App\Support\ProductOptions::sizeSystems(),
        ),
        'state' => $optionsState,
        'text' => [
            'colors' => __('الألوان'),
            'sizes' => __('المقاسات'),
            'otherColor' => __('لون آخر'),
            'colorName' => __('اسم اللون'),
            'color' => __('اللون'),
            'add' => __('إضافة'),
            'cancel' => __('إلغاء'),
            'remove' => __('إزالة'),
            'addSize' => __('إضافة مقاس'),
            'otherSize' => __('مقاس آخر (مثال: 42 عريض)'),
            'selectAll' => __('تحديد الكل'),
            'clear' => __('مسح'),
            'matrix' => __('الكميات والأسعار'),
            'available' => __('متوفر'),
            'size' => __('المقاس'),
            'quantity' => __('الكمية'),
            'priceIncrease' => __('زيادة السعر (ل.س)'),
            'sku' => __('رمز SKU'),
            'showSku' => __('إظهار رمز SKU لكل خيار'),
            'bulkStock' => __('الكمية لكل الخيارات'),
            'bulkPrice' => __('زيادة السعر لكل الخيارات'),
            'apply' => __('تطبيق'),
            'applyToColor' => __('تطبيق على هذا اللون'),
            'totals' => __('المجموع: :stock قطعة في :count خيار'),
            'empty' => __('اختر لوناً أو مقاساً واحداً على الأقل لتظهر الخيارات هنا.'),
            'noneAvailable' => __('فعّل خياراً واحداً على الأقل، أو أوقف الألوان والمقاسات.'),
            'tooMany' => __('عدد الخيارات كبير جداً (الحد 200). قلّل الألوان أو المقاسات.'),
            'duplicateColor' => __('هذا اللون مضاف مسبقاً.'),
            'colorNameRequired' => __('اكتب اسم اللون.'),
            'legacyColor' => __('لون مكتوب سابقاً — اضغط لاختيار درجته'),
            'noColor' => __('بدون لون'),
            'confirmOff' => __('سيتم حذف كل الألوان والمقاسات عند الحفظ. متابعة؟'),
        ],
    ];
@endphp
<div class="col-12">
    <div class="card p-3 mb-3 border ts-opt" id="ts-options">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="fw-bold mb-0">الألوان والمقاسات</h6>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="ts-options-switch" @checked($optionsState['enabled'])>
                <label class="form-check-label" for="ts-options-switch">هذا المنتج له ألوان أو مقاسات</label>
            </div>
        </div>
        <p class="text-muted small mt-2 mb-0">اختر الألوان والمقاسات مرة واحدة، وتُنشأ كل التركيبات تلقائياً لتملأ كمياتها.</p>
        @if ($errors->has('variant_*'))
            <div class="text-danger small mt-2">{{ $errors->first('variant_*') }}</div>
        @endif

        <fieldset id="ts-options-root" class="ts-opt__editor mt-3" @disabled(! $optionsState['enabled']) @if (! $optionsState['enabled']) hidden @endif></fieldset>

        <input type="hidden" name="variant_options_enabled" id="ts-options-enabled" value="{{ $optionsState['enabled'] ? '1' : '0' }}">
        <input type="hidden" name="replace_variants" value="1">
        {{-- نسخة الخادم من الخيارات: إن لم يعمل السكربت يُحفظ المنتج بخياراته كما هي بدل أن تُحذف --}}
        <div id="ts-options-inputs" hidden>
            @if ($optionsState['enabled'])
                @foreach ($optionsState['rows'] as $row)
                    <input type="hidden" name="variant_color[]" value="{{ $row['color'] }}">
                    <input type="hidden" name="variant_color_name[]" value="{{ $row['color_name'] }}">
                    <input type="hidden" name="variant_size[]" value="{{ $row['size'] }}">
                    <input type="hidden" name="variant_sku[]" value="{{ $row['sku'] }}">
                    <input type="hidden" name="variant_price_adjustment[]" value="{{ $row['price_adjustment'] }}">
                    <input type="hidden" name="variant_stock_quantity[]" value="{{ $row['stock_quantity'] }}">
                    <input type="hidden" name="variant_original_color[]" value="{{ $row['original_color'] }}">
                @endforeach
            @endif
        </div>
        <script type="application/json" id="ts-options-config">@json($optionsConfig)</script>
    </div>
</div>

@push('styles')
<style>
    .ts-opt { --ts-opt-accent: var(--ts-emerald); }
    html.dark .ts-opt { --ts-opt-accent: var(--tsd-accent, #5cc8a0); }
    .ts-opt__editor { min-inline-size: 0; }
    .ts-opt-section + .ts-opt-section { margin-block-start: 1.25rem; padding-block-start: 1rem; border-block-start: 1px solid var(--ts-border); }
    .ts-opt-heading { font-size: .95rem; font-weight: 700; color: var(--ts-ink); margin-block-end: .6rem; }

    .ts-opt-dot { position: relative; display: inline-grid; place-items: center; flex: none; inline-size: 2rem; block-size: 2rem; border-radius: 50%; border: 1px solid var(--ts-border); overflow: hidden; font-size: .9rem; }
    html.dark .ts-opt-dot { border-color: var(--ts-muted); }
    .ts-opt-dot--legacy { background: repeating-linear-gradient(45deg, var(--ts-surface-2) 0 4px, var(--ts-border) 4px 8px); }
    html.dark .ts-opt-dot--legacy { background: repeating-linear-gradient(45deg, var(--ts-surface-2) 0 4px, var(--ts-muted) 4px 5px); }
    .ts-opt-dot--legacy:focus-within { box-shadow: 0 0 0 2px var(--ts-surface), 0 0 0 4px var(--ts-opt-accent); }
    .ts-opt-legacy-picker { position: absolute; inset: 0; inline-size: 100%; block-size: 100%; padding: 0; border: 0; opacity: 0; cursor: pointer; }

    .ts-opt-swatches { display: grid; grid-template-columns: repeat(auto-fill, minmax(4.25rem, 1fr)); gap: .4rem; }
    .ts-opt-swatch { display: flex; flex-direction: column; align-items: center; gap: .3rem; min-block-size: 44px; padding: .35rem .2rem; border: 1px solid transparent; border-radius: var(--ts-radius); background: transparent; color: var(--ts-ink); font-size: .75rem; line-height: 1.2; text-align: center; overflow-wrap: anywhere; }
    .ts-opt-swatch:hover { background: var(--ts-surface-2); }
    .ts-opt-swatch[aria-pressed="true"] { border-color: var(--ts-opt-accent); background: var(--ts-surface-2); font-weight: 700; }
    .ts-opt-swatch[aria-pressed="true"] .ts-opt-dot { box-shadow: 0 0 0 2px var(--ts-surface), 0 0 0 4px var(--ts-opt-accent); }

    .ts-opt-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
    .ts-opt-chips:empty { display: none; }
    .ts-opt-chip { display: inline-flex; align-items: center; gap: .35rem; min-block-size: 36px; max-inline-size: 100%; padding: .25rem .75rem; border: 1px solid var(--ts-border); border-radius: 999px; background: var(--ts-surface); color: var(--ts-ink); font-size: .85rem; overflow-wrap: anywhere; }
    .ts-opt-chip[aria-pressed="true"], .ts-opt-chip.is-selected { border-color: var(--ts-opt-accent); background: var(--ts-opt-accent); color: var(--ts-surface); }
    .ts-opt-chip--color { padding-inline-start: .3rem; border-color: var(--ts-opt-accent); background: var(--ts-surface-2); }
    .ts-opt-chip--color .ts-opt-dot { inline-size: 1.6rem; block-size: 1.6rem; }
    .ts-opt-chip__remove { display: inline-grid; place-items: center; inline-size: 24px; block-size: 24px; margin-inline-start: .1rem; padding: 0; border: 0; border-radius: 50%; background: transparent; color: inherit; line-height: 1; }
    .ts-opt-chip__remove:hover { background: rgba(127, 127, 127, .2); }
    .ts-opt-custom { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-block-start: .6rem; }
    .ts-opt-color-form { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; inline-size: 100%; padding: .6rem; border: 1px dashed var(--ts-border); border-radius: var(--ts-radius); background: var(--ts-surface-2); }
    .ts-opt-color-form input[type="text"] { flex: 1 1 10rem; min-inline-size: 0; }

    .ts-opt-systems { display: flex; flex-wrap: wrap; gap: .35rem; margin-block-end: .5rem; }
    .ts-opt-system { min-block-size: 36px; padding: .3rem .75rem; border: 1px solid var(--ts-border); border-radius: 8px; background: var(--ts-surface); color: var(--ts-ink); font-size: .85rem; }
    .ts-opt-system[aria-pressed="true"] { border-color: var(--ts-opt-accent); background: var(--ts-surface-2); box-shadow: inset 0 -3px 0 var(--ts-opt-accent); font-weight: 700; }
    .ts-opt-system-actions { display: flex; gap: 1rem; margin-block-end: .4rem; }
    .ts-opt-custom-size { display: flex; gap: .4rem; max-inline-size: 28rem; margin-block: .6rem; }
    .ts-opt-custom-size input { flex: 1 1 auto; min-inline-size: 0; }

    .ts-opt-swatch:focus-visible, .ts-opt-chip:focus-visible, .ts-opt-system:focus-visible, .ts-opt-chip__remove:focus-visible { outline: 2px solid var(--ts-opt-accent); outline-offset: 2px; }

    .ts-opt .form-check-input:checked { background-color: var(--ts-opt-accent); border-color: var(--ts-opt-accent); }
    .ts-opt .form-check-input:focus { border-color: var(--ts-opt-accent); box-shadow: 0 0 0 .25rem color-mix(in srgb, var(--ts-opt-accent) 25%, transparent); }
    .ts-opt .form-switch .form-check-input:focus:not(:checked) { --bs-form-switch-bg: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280, 0, 0, 0.25%29'/%3e%3c/svg%3e"); }
    .ts-opt-bulk { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .75rem 1rem; margin-block-end: .75rem; }
    .ts-opt-bulk__item { display: flex; flex-direction: column; gap: .2rem; flex: 1 1 12rem; min-inline-size: 0; }
    .ts-opt-bulk__item .input-group { flex-wrap: nowrap; }
    .ts-opt-bulk__item .form-control { min-inline-size: 0; }

    .ts-opt-matrix { border: 1px solid var(--ts-border); border-radius: var(--ts-radius); overflow: hidden; background: var(--ts-surface); }
    .ts-opt-empty { padding: .9rem; margin: 0; color: var(--ts-muted); font-size: .875rem; }
    .ts-opt-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; padding: .45rem .75rem; border-block-start: 1px solid var(--ts-border); }
    .ts-opt-row--head { border-block-start: 0; background: var(--ts-surface-2); color: var(--ts-muted); font-size: .75rem; font-weight: 700; }
    .ts-opt-row__main { display: flex; align-items: center; gap: .5rem; flex: 1 1 8rem; min-inline-size: 0; }
    .ts-opt-row__main .form-check { min-block-size: 0; padding-block: 0; }
    .ts-opt-row__label { min-inline-size: 0; overflow-wrap: anywhere; }
    .ts-opt-row .ts-opt-dot { inline-size: 1.25rem; block-size: 1.25rem; }
    .ts-opt-row.is-off .ts-opt-row__label { color: var(--ts-muted); text-decoration: line-through; }
    .ts-opt-row__fields { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 7.5rem); align-items: end; gap: .5rem; flex: 0 1 auto; }
    .ts-opt-field { display: block; margin: 0; }
    .ts-opt-field__label { display: none; }
    .ts-opt-group__head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .5rem .75rem; border-block-start: 1px solid var(--ts-border); background: var(--ts-surface-2); }
    .ts-opt-group + .ts-opt-group .ts-opt-group__head { border-block-start-width: 2px; }
    .ts-opt-group__head .ts-opt-dot { inline-size: 1.5rem; block-size: 1.5rem; }
    .ts-opt-group__name { flex: 1 1 6rem; min-inline-size: 0; font-weight: 700; overflow-wrap: anywhere; }
    .ts-opt-group__apply { display: flex; align-items: center; gap: .35rem; min-inline-size: 0; }
    .ts-opt-group__apply .form-control { inline-size: 6rem; }
    .ts-opt-totals { margin: .6rem 0 0; color: var(--ts-ink); font-weight: 700; }
    #product-stock[readonly] { background-color: var(--ts-surface-2); }

    @media (max-width: 575.98px) {
        .ts-opt-row--head { display: none; }
        .ts-opt-row__fields { flex-basis: 100%; grid-auto-columns: minmax(0, 1fr); }
        .ts-opt-field__label { display: block; margin-block-end: .15rem; color: var(--ts-muted); font-size: .7rem; }
        .ts-opt-group__apply { flex-basis: 100%; }
        .ts-opt-group__apply .form-control { flex: 1 1 auto; inline-size: auto; min-inline-size: 0; }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/ts-product-options.js') }}?v={{ @filemtime(public_path('js/ts-product-options.js')) }}" defer></script>
@endpush
