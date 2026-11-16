/*
 * محرر الألوان والمقاسات في نموذج منتج البائع (resources/views/seller/products/_options.blade.php).
 *
 * البائع يختار الألوان (لوحة + "لون آخر") والمقاسات (أنظمة مقاسات + مقاس يدوي) مرة واحدة، والمحرر يولّد
 * كل تركيبة لون × مقاس ليملأ كميتها وزيادة سعرها. عند كل تغيير تُكتب حقول variant_*[] المخفية في
 * #ts-options-inputs بالصيغة التي يقرؤها ProductVariantSync، ومعها variant_original_color: مفتاح اللون
 * القديم (كلمة مثل "اسود") حتى يُحدَّث الخيار نفسه في مكانه فتبقى سلال العملاء مرتبطة به.
 *
 * النصوص تأتي مترجمة في config.text (قاموس الترجمة لا يدخل <script>)، وكل نص كتبه البائع
 * يُعرض بـ textContent فقط.
 */
(function () {
    'use strict';

    const configNode = document.getElementById('ts-options-config');
    const root = document.getElementById('ts-options-root');
    if (!configNode || !root) {
        return;
    }

    const config = JSON.parse(configNode.textContent);
    const text = config.text;
    const form = root.closest('form');
    const switchInput = document.getElementById('ts-options-switch');
    const enabledInput = document.getElementById('ts-options-enabled');
    const hiddenInputs = document.getElementById('ts-options-inputs');
    const productStock = document.getElementById('product-stock');
    const productStockHint = document.getElementById('product-stock-hint');

    const MAX_COMBINATIONS = 200;
    const KEY_SEPARATOR = '\u0000';
    const NEUTRAL_HEX = '#888888';

    // ------------------------------------------------------------------ أدوات

    /** مطابقة لـ ProductOptions::normalizeName في PHP، بنفس الخطوات وترتيبها. */
    function normalizeName(name) {
        return String(name)
            .replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '')
            .replace(/\s+/gu, ' ')
            .replace(/[ـً-ْ]/g, '')
            .replace(/[أإآ]/g, 'ا')
            .replace(/ى/g, 'ي')
            .replace(/ة/g, 'ه')
            .toLowerCase();
    }

    const isHex = (value) => typeof value === 'string' && /^#?[0-9A-Fa-f]{6}$/.test(value);
    const canonicalHex = (value) => '#' + value.replace(/^#/, '').toUpperCase();
    /** المقاس يُقارن بعد حذف المسافات حوله وبغض النظر عن حالة الأحرف: "m" و"M " مقاس واحد. */
    const sizeKey = (value) => String(value).trim().toLowerCase();
    const comboKey = (colorKey, sizeKeyValue) => colorKey + KEY_SEPARATOR + sizeKeyValue;
    const fill = (template, values) => Object.keys(values).reduce((line, name) => line.split(':' + name).join(String(values[name])), template);

    /** القيمة كما تُرسل: الحقل الفارغ صفر، والعدد الصحيح بلا كسور (15 لا 15.00). */
    function wireNumber(value) {
        const trimmed = String(value).trim();
        if (trimmed === '') {
            return '0';
        }
        const number = Number(trimmed);

        return Number.isFinite(number) ? String(number) : trimmed;
    }

    const stockCount = (value) => Math.max(0, parseInt(value, 10) || 0);

    /** لون علامة الصح فوق الدرجة: داكن على الفاتح وأبيض على الداكن. */
    function inkOn(hex) {
        const [red, green, blue] = [1, 3, 5].map((start) => {
            const channel = parseInt(hex.slice(start, start + 2), 16) / 255;

            return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
        });

        return 0.2126 * red + 0.7152 * green + 0.0722 * blue > 0.179 ? '#1A1A1A' : '#FFFFFF';
    }

    /** ينشئ عنصراً بخصائصه وأبنائه؛ النصوص تُضاف كعُقد نصية (لا HTML أبداً). */
    function h(tag, attributes, ...children) {
        const node = document.createElement(tag);
        Object.entries(attributes || {}).forEach(([name, value]) => {
            if (value === null || value === undefined || value === false) {
                return;
            }
            if (name === 'class') {
                node.className = value;
            } else if (name === 'style') {
                Object.assign(node.style, value);
            } else if (name.startsWith('on')) {
                node.addEventListener(name.slice(2), value);
            } else {
                node.setAttribute(name, value === true ? '' : String(value));
            }
        });
        children.flat().forEach((child) => {
            if (child !== null && child !== undefined && child !== false) {
                node.append(child);
            }
        });

        return node;
    }

    function numberInput(attributes) {
        return h('input', { type: 'number', min: '0', class: 'form-control form-control-sm', autocomplete: 'off', placeholder: '0', ...attributes });
    }

    function onEnter(input, action) {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.isComposing) {
                event.preventDefault();
                action();
            }
        });
    }

    const confirmDialog = (message) => (window.tsConfirm ? window.tsConfirm(message) : Promise.resolve(window.confirm(message)));
    const alertDialog = (message) => (window.tsAlert ? window.tsAlert(message) : Promise.resolve(window.alert(message)));

    // ------------------------------------------------------------------ الحالة

    const palette = config.colors.map((color) => ({ name: color.name, label: color.label, hex: canonicalHex(color.hex) }));
    const paletteByHex = new Map(palette.map((color) => [color.hex, color]));
    const paletteByName = new Map(palette.map((color) => [normalizeName(color.name), color]));
    const systemSizeKeys = new Set(config.sizeSystems.flatMap((system) => system.sizes.map(sizeKey)));

    const state = {
        enabled: Boolean(config.state.enabled),
        /** الألوان المختارة بترتيب اختيارها: {key, name, label, hex, originalKey} */
        colors: [],
        /** المقاسات المختارة بترتيب اختيارها: {key, value} */
        sizes: [],
        /** قيم كل تركيبة؛ لا تُحذف أبداً، فإلغاء لون أو مقاس ثم إعادته يُرجع ما كتبه البائع */
        combos: new Map(),
        showSku: false,
        activeSystem: config.sizeSystems.length ? config.sizeSystems[0].key : null,
    };
    /** ألوان أُلغي اختيارها: إن عادت تعود بمفتاحها القديم (originalKey) */
    const removedColors = new Map();
    const colorListeners = [];

    const findColor = (key) => state.colors.find((color) => color.key === key) || null;
    const findSize = (value) => state.sizes.find((size) => size.key === sizeKey(value)) || null;
    const isPaletteColor = (color) => paletteByHex.has(color.key);
    const activeSystem = () => config.sizeSystems.find((system) => system.key === state.activeSystem) || null;

    function paletteColor(entry) {
        return { key: entry.hex, name: entry.name, label: entry.label, hex: entry.hex, originalKey: null };
    }

    function customColor(key, name, hex) {
        return { key, name, label: name, hex, originalKey: null };
    }

    function selectColor(color) {
        const removed = removedColors.get(color.key);
        if (removed && !color.originalKey) {
            color.originalKey = removed.originalKey;
        }
        removedColors.delete(color.key);
        state.colors.push(color);
    }

    function unselectColor(color) {
        state.colors = state.colors.filter((selected) => selected !== color);
        removedColors.set(color.key, color);
    }

    /** مقاس مكتوب باليد يطابق مقاساً في نظام المقاسات يُحفظ بكتابة النظام (xl تصير XL). */
    function systemSpelling(value) {
        const key = sizeKey(value);
        for (const system of config.sizeSystems) {
            const match = system.sizes.find((size) => sizeKey(size) === key);
            if (match) {
                return match;
            }
        }

        return null;
    }

    function addSize(value, useSystemSpelling = false) {
        const trimmed = String(value).trim();
        if (trimmed === '') {
            return null;
        }
        const existing = findSize(trimmed);
        if (existing) {
            return existing;
        }
        const size = { key: sizeKey(trimmed), value: (useSystemSpelling && systemSpelling(trimmed)) || trimmed };
        state.sizes.push(size);

        return size;
    }

    function toggleSize(value) {
        const existing = findSize(value);
        if (existing) {
            state.sizes = state.sizes.filter((size) => size !== existing);
        } else {
            addSize(value);
        }
    }

    /** كل تركيبات الاختيار الحالي: ألوان × مقاسات، أو الألوان وحدها، أو المقاسات وحدها. */
    function grid() {
        if (!state.colors.length && !state.sizes.length) {
            return [];
        }
        const colors = state.colors.length ? state.colors : [null];
        const sizes = state.sizes.length ? state.sizes : [null];

        return colors.flatMap((color) => sizes.map((size) => ({
            color,
            size,
            key: comboKey(color ? color.key : '', size ? size.key : ''),
        })));
    }

    function comboFor(key) {
        if (!state.combos.has(key)) {
            state.combos.set(key, { available: true, stock: '', price: '', sku: '', originalColor: null });
        }

        return state.combos.get(key);
    }

    const availableRows = () => grid().filter((row) => comboFor(row.key).available);

    /**
     * لون محفوظ: درجة hex كما هي (من اللوحة إن طابقتها)، وكلمة قديمة تطابق اسماً في اللوحة تصير
     * درجة اللوحة مع الاحتفاظ بالكلمة مفتاحاً أصلياً، وغير ذلك لون خاص بلا درجة (يختارها البائع).
     */
    function loadColor(row) {
        const raw = String(row.color).trim();
        let color;
        if (isHex(raw)) {
            const hex = canonicalHex(raw);
            color = paletteByHex.has(hex) ? paletteColor(paletteByHex.get(hex)) : customColor(hex, row.color_name || hex, hex);
        } else {
            const match = paletteByName.get(normalizeName(row.color_name || '')) || paletteByName.get(normalizeName(raw));
            color = match ? paletteColor(match) : customColor(raw, row.color_name || raw, null);
        }

        let selected = findColor(color.key);
        if (!selected) {
            state.colors.push(color);
            selected = color;
        }
        const original = row.original_color || raw;
        if (!selected.originalKey && original !== selected.key) {
            selected.originalKey = original;
        }

        return selected;
    }

    function load(rows) {
        const loadedKeys = new Set();
        rows.forEach((row) => {
            const color = row.color ? loadColor(row) : null;
            const size = row.size ? addSize(row.size) : null;
            const key = comboKey(color ? color.key : '', size ? size.key : '');
            const combo = comboFor(key);
            if (loadedKeys.has(key)) {
                // صفان محفوظان لنفس التركيبة: يبقى الأول بهويته ويُجمع مخزون الثاني
                combo.stock = String(stockCount(combo.stock) + stockCount(row.stock_quantity));
                return;
            }
            loadedKeys.add(key);
            combo.available = true;
            combo.stock = String(row.stock_quantity ?? '');
            combo.price = String(row.price_adjustment ?? '');
            combo.sku = row.sku || '';
            combo.originalColor = row.original_color || null;
        });

        // تركيبة لم تكن محفوظة تبدأ غير متوفرة، فلا يُضاف للمنتج خيار لم يبعه البائع
        grid().forEach((row) => {
            if (!state.combos.has(row.key)) {
                state.combos.set(row.key, { available: false, stock: '', price: '', sku: '', originalColor: null });
            }
        });

        state.showSku = rows.some((row) => row.sku);
        let bestCount = 0;
        config.sizeSystems.forEach((system) => {
            const count = system.sizes.filter((size) => findSize(size)).length;
            if (count > bestCount) {
                bestCount = count;
                state.activeSystem = system.key;
            }
        });
    }

    // ------------------------------------------------------------------ أفعال البائع

    function togglePaletteColor(entry) {
        const selected = findColor(entry.hex);
        if (selected) {
            unselectColor(selected);
        } else {
            selectColor(paletteColor(entry));
        }
        update(true);
    }

    function addCustomColor() {
        const name = colorNameInput.value.trim().replace(/\s+/g, ' ');
        if (name === '') {
            colorError.textContent = text.colorNameRequired;
            colorNameInput.focus();

            return;
        }
        const hex = canonicalHex(colorHexInput.value);
        if (findColor(hex)) {
            colorError.textContent = text.duplicateColor;

            return;
        }
        selectColor(paletteByHex.has(hex) ? paletteColor(paletteByHex.get(hex)) : customColor(hex, name, hex));
        closeColorForm();
        update(true);
    }

    /** لون قديم بلا درجة: البائع يختار درجته، والتركيبات تنتقل للمفتاح الجديد بقيمها. */
    function recolorLegacy(color, value) {
        const hex = canonicalHex(value);
        if (findColor(hex)) {
            alertDialog(text.duplicateColor);
            update(false);

            return;
        }
        const previousKey = color.key;
        [...state.combos.entries()].forEach(([key, combo]) => {
            const [colorKey, sizePart] = key.split(KEY_SEPARATOR);
            if (colorKey === previousKey) {
                state.combos.delete(key);
                state.combos.set(comboKey(hex, sizePart), combo);
            }
        });
        color.originalKey = color.originalKey || previousKey;
        color.key = hex;
        color.hex = hex;
        if (paletteByHex.has(hex)) {
            color.name = paletteByHex.get(hex).name;
            color.label = paletteByHex.get(hex).label;
        }
        update(true);
    }

    function addCustomSize() {
        addSize(customSizeInput.value, true);
        customSizeInput.value = '';
        update(false);
    }

    function selectAllSizes() {
        const system = activeSystem();
        if (system) {
            system.sizes.forEach((size) => addSize(size));
            update(false);
        }
    }

    function clearSizes() {
        const system = activeSystem();
        if (system) {
            const keys = new Set(system.sizes.map(sizeKey));
            state.sizes = state.sizes.filter((size) => !keys.has(size.key));
            update(false);
        }
    }

    /** يكتب القيمة في كل تركيبة متوفرة (أو تركيبات لون واحد). */
    function applyToAvailable(field, input, color) {
        if (!input.reportValidity() || input.value.trim() === '') {
            return;
        }
        grid().forEach((row) => {
            const combo = comboFor(row.key);
            if (combo.available && (!color || row.color === color)) {
                combo[field] = input.value.trim();
            }
        });
        update(false);
    }

    function openColorForm() {
        colorForm.hidden = false;
        otherColorButton.setAttribute('aria-expanded', 'true');
        colorError.textContent = '';
        colorNameInput.focus();
    }

    function closeColorForm() {
        colorForm.hidden = true;
        otherColorButton.setAttribute('aria-expanded', 'false');
        colorNameInput.value = '';
        colorHexInput.value = NEUTRAL_HEX;
        colorError.textContent = '';
        otherColorButton.focus();
    }

    function setEnabled(enabled) {
        state.enabled = enabled;
        root.hidden = !enabled;
        root.disabled = !enabled;
        sync();
        notifyColors();
    }

    // ------------------------------------------------------------------ الهيكل الثابت

    const paletteBox = h('div', { class: 'ts-opt-swatches', role: 'group', 'aria-label': text.colors });
    const customColorsBox = h('div', { class: 'ts-opt-chips' });
    const otherColorButton = h('button', {
        type: 'button', class: 'btn btn-outline-secondary btn-sm', 'aria-expanded': 'false', 'aria-controls': 'ts-opt-color-form',
        onclick: () => (colorForm.hidden ? openColorForm() : closeColorForm()),
    }, h('i', { class: 'bi bi-plus-lg', 'aria-hidden': 'true' }), ' ', text.otherColor);
    const colorNameInput = h('input', { type: 'text', id: 'ts-opt-color-name', class: 'form-control form-control-sm', maxlength: '50', autocomplete: 'off', placeholder: text.colorName });
    const colorHexInput = h('input', { type: 'color', id: 'ts-opt-color-hex', class: 'form-control form-control-sm form-control-color', value: NEUTRAL_HEX });
    const colorError = h('div', { class: 'text-danger small w-100', role: 'alert' });
    const colorForm = h('div', { id: 'ts-opt-color-form', class: 'ts-opt-color-form', hidden: true },
        h('label', { class: 'visually-hidden', for: 'ts-opt-color-name' }, text.colorName),
        colorNameInput,
        h('label', { class: 'visually-hidden', for: 'ts-opt-color-hex' }, text.color),
        colorHexInput,
        h('button', { type: 'button', class: 'btn btn-primary btn-sm', onclick: addCustomColor }, text.add),
        h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', onclick: closeColorForm }, text.cancel),
        colorError);
    onEnter(colorNameInput, addCustomColor);

    const systemsBox = h('div', { class: 'ts-opt-systems', role: 'group', 'aria-label': text.sizes });
    const systemActions = h('div', { class: 'ts-opt-system-actions' },
        h('button', { type: 'button', class: 'btn btn-link btn-sm p-0', onclick: selectAllSizes }, text.selectAll),
        h('button', { type: 'button', class: 'btn btn-link btn-sm p-0', onclick: clearSizes }, text.clear));
    const systemChipsBox = h('div', { class: 'ts-opt-chips' });
    const customSizeInput = h('input', { type: 'text', class: 'form-control form-control-sm', maxlength: '50', autocomplete: 'off', placeholder: text.otherSize, 'aria-label': text.otherSize });
    const customSizesBox = h('div', { class: 'ts-opt-chips' });
    onEnter(customSizeInput, addCustomSize);

    const bulkStockInput = numberInput({ id: 'ts-opt-bulk-stock', step: '1', inputmode: 'numeric' });
    const bulkPriceInput = numberInput({ id: 'ts-opt-bulk-price', step: '0.01', inputmode: 'decimal' });
    const bulkStockButton = h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', onclick: () => applyToAvailable('stock', bulkStockInput, null) }, text.apply);
    const bulkPriceButton = h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', onclick: () => applyToAvailable('price', bulkPriceInput, null) }, text.apply);
    onEnter(bulkStockInput, () => applyToAvailable('stock', bulkStockInput, null));
    onEnter(bulkPriceInput, () => applyToAvailable('price', bulkPriceInput, null));
    const showSkuInput = h('input', { type: 'checkbox', role: 'switch', id: 'ts-opt-show-sku', class: 'form-check-input' });
    showSkuInput.addEventListener('change', () => {
        state.showSku = showSkuInput.checked;
        update(false);
    });
    const matrixBox = h('div', { class: 'ts-opt-matrix' });
    const totalsLine = h('p', { class: 'ts-opt-totals', 'aria-live': 'polite' });

    function bulkItem(id, label, input, button) {
        return h('div', { class: 'ts-opt-bulk__item' },
            h('label', { class: 'form-label small mb-0', for: id }, label),
            h('div', { class: 'input-group input-group-sm' }, input, button));
    }

    root.replaceChildren(
        h('section', { class: 'ts-opt-section' },
            h('h4', { class: 'ts-opt-heading' }, text.colors),
            paletteBox,
            h('div', { class: 'ts-opt-custom' }, customColorsBox, otherColorButton, colorForm)),
        h('section', { class: 'ts-opt-section' },
            h('h4', { class: 'ts-opt-heading' }, text.sizes),
            systemsBox,
            systemActions,
            systemChipsBox,
            h('div', { class: 'ts-opt-custom-size' },
                customSizeInput,
                h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm text-nowrap', onclick: addCustomSize }, text.addSize)),
            customSizesBox),
        h('section', { class: 'ts-opt-section' },
            h('h4', { class: 'ts-opt-heading' }, text.matrix),
            h('div', { class: 'ts-opt-bulk' },
                bulkItem('ts-opt-bulk-stock', text.bulkStock, bulkStockInput, bulkStockButton),
                bulkItem('ts-opt-bulk-price', text.bulkPrice, bulkPriceInput, bulkPriceButton),
                h('div', { class: 'form-check form-switch mb-1' },
                    showSkuInput,
                    h('label', { class: 'form-check-label small', for: 'ts-opt-show-sku' }, text.showSku))),
            matrixBox,
            totalsLine),
    );

    // ------------------------------------------------------------------ الأجزاء المتغيرة

    function swatchDot(hex, checked) {
        if (!hex) {
            return h('span', { class: 'ts-opt-dot ts-opt-dot--legacy', 'aria-hidden': 'true' });
        }

        return h('span', { class: 'ts-opt-dot', style: { background: hex }, 'aria-hidden': 'true' },
            checked ? h('i', { class: 'bi bi-check-lg', style: { color: inkOn(hex) } }) : null);
    }

    function removeButton(label, focusId, action) {
        return h('button', { type: 'button', class: 'ts-opt-chip__remove', title: text.remove, 'aria-label': text.remove + ': ' + label, 'data-focus': focusId, onclick: action },
            h('i', { class: 'bi bi-x-lg', 'aria-hidden': 'true' }));
    }

    function customColorChip(color) {
        let dot = swatchDot(color.hex, false);
        if (!color.hex) {
            // كلمة قديمة بلا درجة: حقل اختيار اللون شفاف فوق الدائرة المخططة، فالضغط عليها يفتحه
            const picker = h('input', { type: 'color', class: 'ts-opt-legacy-picker', value: NEUTRAL_HEX, title: text.legacyColor, 'aria-label': text.legacyColor + ': ' + color.label, 'data-focus': 'recolor:' + color.key });
            picker.addEventListener('change', () => recolorLegacy(color, picker.value));
            dot = h('span', { class: 'ts-opt-dot ts-opt-dot--legacy', title: text.legacyColor }, picker);
        }

        return h('span', { class: 'ts-opt-chip ts-opt-chip--color' },
            dot,
            h('span', {}, color.label),
            removeButton(color.label, 'remove-color:' + color.key, () => {
                unselectColor(color);
                update(true);
            }));
    }

    function renderColors() {
        paletteBox.replaceChildren(...palette.map((entry) => {
            const selected = Boolean(findColor(entry.hex));

            return h('button', { type: 'button', class: 'ts-opt-swatch', 'aria-pressed': String(selected), 'data-focus': 'palette:' + entry.hex, onclick: () => togglePaletteColor(entry) },
                swatchDot(entry.hex, selected),
                h('span', {}, entry.label));
        }));
        customColorsBox.replaceChildren(...state.colors.filter((color) => !isPaletteColor(color)).map(customColorChip));
    }

    function renderSizes() {
        const system = activeSystem();
        systemsBox.replaceChildren(...config.sizeSystems.map((entry) => h('button', {
            type: 'button', class: 'ts-opt-system', 'aria-pressed': String(entry === system), 'data-focus': 'system:' + entry.key,
            onclick: () => {
                state.activeSystem = entry.key;
                update(false);
            },
        }, entry.label)));
        systemActions.hidden = !system;
        systemChipsBox.replaceChildren(...(system ? system.sizes : []).map((size) => h('button', {
            type: 'button', class: 'ts-opt-chip', 'aria-pressed': String(Boolean(findSize(size))), 'data-focus': 'size:' + system.key + ':' + size,
            onclick: () => {
                toggleSize(size);
                update(false);
            },
        }, size)));
        customSizesBox.replaceChildren(...state.sizes.filter((size) => !systemSizeKeys.has(size.key)).map((size) => h('span', { class: 'ts-opt-chip is-selected' },
            h('span', {}, size.value),
            removeButton(size.value, 'remove-size:' + size.key, () => {
                toggleSize(size.value);
                update(false);
            }))));
    }

    function field(label, input) {
        return h('label', { class: 'ts-opt-field' }, h('span', { class: 'ts-opt-field__label' }, label), input);
    }

    function matrixRow(row, grouped) {
        const combo = comboFor(row.key);
        const name = [row.color ? row.color.label : null, row.size ? row.size.value : null].filter(Boolean).join(' / ');
        const off = !combo.available;
        const describe = (label) => label + ' — ' + name;

        const toggle = h('input', { type: 'checkbox', role: 'switch', class: 'form-check-input', checked: combo.available, 'aria-label': describe(text.available), 'data-focus': 'available:' + row.key });
        toggle.addEventListener('change', () => {
            combo.available = toggle.checked;
            update(false);
        });

        const input = (key, attributes) => {
            const node = key === 'sku'
                ? h('input', { type: 'text', class: 'form-control form-control-sm', maxlength: '100', autocomplete: 'off', ...attributes })
                : numberInput(attributes);
            node.value = combo[key];
            node.disabled = off;
            node.addEventListener('input', () => {
                combo[key] = node.value;
                sync();
            });

            return node;
        };

        return h('div', { class: 'ts-opt-row' + (off ? ' is-off' : '') },
            h('div', { class: 'ts-opt-row__main' },
                h('div', { class: 'form-check form-switch mb-0' }, toggle),
                !grouped && row.color ? swatchDot(row.color.hex, false) : null,
                h('span', { class: 'ts-opt-row__label' }, grouped ? row.size.value : name)),
            h('div', { class: 'ts-opt-row__fields' },
                field(text.quantity, input('stock', { step: '1', inputmode: 'numeric', 'aria-label': describe(text.quantity), 'data-focus': 'stock:' + row.key })),
                field(text.priceIncrease, input('price', { step: '0.01', inputmode: 'decimal', 'aria-label': describe(text.priceIncrease), 'data-focus': 'price:' + row.key })),
                state.showSku ? field(text.sku, input('sku', { placeholder: text.sku, 'aria-label': describe(text.sku), 'data-focus': 'sku:' + row.key })) : null));
    }

    function colorGroup(color) {
        const stockInput = numberInput({ step: '1', inputmode: 'numeric', 'aria-label': text.quantity + ' — ' + color.label, 'data-focus': 'color-stock:' + color.key });
        const apply = () => applyToAvailable('stock', stockInput, color);
        onEnter(stockInput, apply);

        return h('div', { class: 'ts-opt-group', role: 'group', 'aria-label': color.label },
            h('div', { class: 'ts-opt-group__head' },
                swatchDot(color.hex, false),
                h('span', { class: 'ts-opt-group__name' }, color.label),
                h('div', { class: 'ts-opt-group__apply' },
                    stockInput,
                    h('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm text-nowrap', 'data-focus': 'color-apply:' + color.key, onclick: apply }, text.applyToColor))),
            state.sizes.map((size) => matrixRow({ color, size, key: comboKey(color.key, size.key) }, true)));
    }

    function renderMatrix() {
        const rows = grid();
        totalsLine.hidden = !rows.length;
        if (!rows.length) {
            matrixBox.replaceChildren(h('p', { class: 'ts-opt-empty' }, text.empty));

            return;
        }

        const grouped = state.colors.length > 0 && state.sizes.length > 0;
        const head = h('div', { class: 'ts-opt-row ts-opt-row--head', 'aria-hidden': 'true' },
            h('div', { class: 'ts-opt-row__main' }, h('span', {}, text.available), h('span', {}, grouped || !state.colors.length ? text.size : text.color)),
            h('div', { class: 'ts-opt-row__fields' },
                h('span', {}, text.quantity),
                h('span', {}, text.priceIncrease),
                state.showSku ? h('span', {}, text.sku) : null));

        matrixBox.replaceChildren(head, ...(grouped ? state.colors.map(colorGroup) : rows.map((row) => matrixRow(row, false))));
    }

    /** يعيد الرسم ويُرجع التركيز للعنصر نفسه (نفس data-focus) حتى لا يضيع مكان من يستخدم لوحة المفاتيح. */
    function update(colorsChanged) {
        const active = document.activeElement;
        const focusId = active && root.contains(active) ? active.getAttribute('data-focus') : null;

        renderColors();
        renderSizes();
        renderMatrix();

        if (focusId !== null) {
            const target = [...root.querySelectorAll('[data-focus]')].find((node) => node.getAttribute('data-focus') === focusId);
            if (target) {
                target.focus();
            }
        }
        sync();
        if (colorsChanged) {
            notifyColors();
        }
    }

    // ------------------------------------------------------------------ الحقول المرسلة

    function hiddenInput(name, value) {
        return h('input', { type: 'hidden', name, value });
    }

    function sync() {
        const rows = state.enabled ? availableRows() : [];
        hiddenInputs.replaceChildren(...rows.flatMap((row) => {
            const combo = comboFor(row.key);

            return [
                hiddenInput('variant_color[]', row.color ? row.color.key : ''),
                hiddenInput('variant_color_name[]', row.color ? row.color.name : ''),
                hiddenInput('variant_size[]', row.size ? row.size.value : ''),
                hiddenInput('variant_sku[]', combo.sku.trim()),
                hiddenInput('variant_price_adjustment[]', wireNumber(combo.price)),
                hiddenInput('variant_stock_quantity[]', wireNumber(combo.stock)),
                hiddenInput('variant_original_color[]', row.color ? (combo.originalColor || row.color.originalKey || '') : ''),
            ];
        }));
        enabledInput.value = state.enabled ? '1' : '0';

        const total = rows.reduce((sum, row) => sum + stockCount(comboFor(row.key).stock), 0);
        totalsLine.textContent = fill(text.totals, { stock: total, count: rows.length });
        if (productStock) {
            productStock.readOnly = state.enabled;
            if (state.enabled) {
                productStock.value = String(total);
            }
        }
        if (productStockHint) {
            productStockHint.hidden = !state.enabled;
            if (productStock) {
                if (state.enabled) {
                    productStock.setAttribute('aria-describedby', 'product-stock-hint');
                } else {
                    productStock.removeAttribute('aria-describedby');
                }
            }
        }
    }

    // ------------------------------------------------------------------ الواجهة العامة (ألوان الصور)

    const publicColor = ({ key, name, label, hex, originalKey }) => ({ key, name, label, hex, originalKey });

    window.tsProductOptions = {
        /** الألوان المختارة (فارغة والمفتاح مطفأ): name هو الاسم العربي المحفوظ، وlabel ما يُعرض. */
        colors: () => (state.enabled ? state.colors.map(publicColor) : []),
        onColorsChange: (callback) => {
            if (typeof callback === 'function') {
                colorListeners.push(callback);
            }
        },
        /** يملأ كل قائمة لون صورة (select[data-ts-color-select]) بألوان المحرر ويحدّث حقل الاسم المخفي المرتبط بها. */
        refreshColorSelects: () => {
            document.querySelectorAll('select[data-ts-color-select]').forEach(fillColorSelect);
        },
    };

    // ------------------------------------------------------------------ قوائم لون الصور

    function colorNameInputFor(select) {
        return [...document.querySelectorAll('input[type="hidden"][data-ts-color-name-for]')]
            .find((input) => input.getAttribute('data-ts-color-name-for') === select.name) || null;
    }

    function fillColorSelect(select) {
        const colors = window.tsProductOptions.colors();
        const current = select.value || select.dataset.selected || '';
        const match = current === '' ? null : colors.find((color) => color.key === current || color.originalKey === current) || null;
        const chosen = match ? match.key : current;
        const previous = [...select.options].find((option) => option.value === current && option.value !== '');

        const options = [new Option(text.noColor, '')];
        colors.forEach((color) => options.push(new Option(color.label, color.key)));
        if (chosen !== '' && !match) {
            options.push(new Option(previous ? previous.textContent : chosen, chosen));
        }
        select.replaceChildren(...options);
        select.value = chosen;
        select.dataset.selected = chosen;

        const nameInput = colorNameInputFor(select);
        if (nameInput && (match || chosen === '')) {
            nameInput.value = match ? match.name : '';
        }
    }

    document.addEventListener('change', (event) => {
        const select = event.target;
        if (!(select instanceof HTMLSelectElement) || !select.hasAttribute('data-ts-color-select')) {
            return;
        }
        select.dataset.selected = select.value;
        const nameInput = colorNameInputFor(select);
        if (nameInput) {
            const color = window.tsProductOptions.colors().find((entry) => entry.key === select.value);
            nameInput.value = color ? color.name : (select.value === '' ? '' : (nameInput.value || select.value));
        }
    });

    function notifyColors() {
        const colors = window.tsProductOptions.colors();
        colorListeners.forEach((callback) => callback(colors));
        window.tsProductOptions.refreshColorSelects();
    }

    // ------------------------------------------------------------------ التشغيل

    if (switchInput) {
        switchInput.addEventListener('change', async () => {
            if (switchInput.checked) {
                setEnabled(true);

                return;
            }
            if (grid().length) {
                switchInput.checked = true;
                if (!(await confirmDialog(text.confirmOff))) {
                    return;
                }
                switchInput.checked = false;
            }
            setEnabled(false);
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            if (!state.enabled) {
                return;
            }
            const count = availableRows().length;
            const message = count === 0 ? text.noneAvailable : (count > MAX_COMBINATIONS ? text.tooMany : null);
            if (message) {
                event.preventDefault();
                alertDialog(message);

                return;
            }
            sync();
        });
    }

    load(config.state.rows || []);
    showSkuInput.checked = state.showSku;
    root.hidden = !state.enabled;
    root.disabled = !state.enabled;
    update(true);
})();
