# Product colors & sizes editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the row-by-row variant entry with a palette + size-system picker that generates every color × size combination, on the seller web form and in the Flutter seller app.

**Architecture:** The server owns the color palette and size systems (`App\Support\ProductOptions`), serves them to the app over a new API endpoint and inline to the web editor. Both editors produce the existing `variant_*[]` wire format plus an optional `variant_original_color[]`, which `ProductVariantSync` uses to upgrade legacy word colors to hex in place. API resources expose `color_hex` so swatches render everywhere.

**Tech Stack:** Laravel 12 / PHP 8.2 / PHPUnit, Blade + vanilla JS + Bootstrap 5.3 (RTL/LTR), Flutter 3.41 / Dart.

**Spec:** `docs/superpowers/specs/2026-11-15-product-options-editor.md`

**Repositories (two worktrees, both on branch `feat/product-options`):**
- Laravel: `C:\xampp\wt\ts-opts` (Tasks 1–5). Tests: `php artisan test --compact <path>`. Format: `vendor/bin/pint --dirty --format agent`.
- Flutter: `C:\xampp\wt\app-opts` (Tasks 6–9). Tests: `flutter test <path>`. Lint: `flutter analyze` must print `No issues found!`.

## Global Constraints

- No new dependencies (composer, npm, pub). No database migrations.
- `color` values written by the new editors are canonical hex `#RRGGBB` (uppercase, with `#`). `color_name` for palette colors is the canonical **Arabic** palette name.
- Palette, size systems and name normalization are exactly as listed in the spec (§ Shared catalog); PHP, JS and Dart must produce identical normalization results.
- Wire format: `variant_color[i]`, `variant_color_name[i]`, `variant_size[i]`, `variant_sku[i]`, `variant_price_adjustment[i]` (SYP, ≥ 0), `variant_stock_quantity[i]` (≥ 0), optional `variant_original_color[i]`, plus `replace_variants=1`. Images: `image_color[i]`/`image_color_name[i]` index-aligned with `images[]`; `existing_image_color[id]`/`existing_image_color_name[id]`.
- Combinations limit: more than 200 available combinations blocks saving (client side).
- Web UI copy is Arabic in Blade/`__()`; every new phrase needs entries in `lang/en.json` and `lang/tr.json` (see "Copy table" below). JS strings come from `__()` server-side, never hardcoded in the JS file. Insert JSON keys at their `strcmp` position without reordering other keys; encode with `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` and a trailing newline; `git diff` must show only your keys. If a key already exists, keep its value unless it is wrong, and say so in your report.
- App UI copy uses `AppText(...)` / `context.tr(...)` with entries in `assets/l10n/en.json` and `assets/l10n/tr.json` (same Arabic keys and translations as the Copy table); `test/l10n_coverage_test.dart` must pass.
- Web styling uses site CSS variables (`--ts-surface`, `--ts-surface-2`, `--ts-border`, `--ts-ink`, `--ts-muted`, `--ts-emerald`, `--ts-gold`; `html.dark` redefines them) and logical properties (`margin-inline-*`, `inset-inline-*`), so dark mode and RTL/LTR both work.
- JS renders seller-typed text with `textContent`/`createElement`, never `innerHTML` (custom color names are user input).
- Commits: conventional style (`feat(products): …`), ending with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never commit `.env`, `vendor/`, `.superpowers/`.

## Review Focus

1. Editing a product whose variants use legacy word colors (`اسود`, size `M`) and saving must keep the same variant ids (cart items survive) and recolor images — Task 2 tests the server, Task 3 tests the initial editor state, Task 9 tests the app payload.
2. A validation error on the web form must bring the seller back to the same matrix (switch, colors, sizes, stock values) — Task 3 `ProductOptionsEditorState` test with `old()` input.
3. Turning a color or size off and on again must keep the stock/price typed for its combinations — Task 7 controller test (app); same rule in the web JS (verified in browser at final review).
4. Negative price increase or stock must be refused with a validation message, not a database error — Task 2 tests (web 302 with errors, API 422).
5. A custom color whose hex equals an already-selected color, and a size typed twice (`m` vs `M `), must not create duplicate combinations — Task 7 controller tests; web JS same rule.

## Copy table (web `lang/*.json` and app `assets/l10n/*.json`)

| Arabic (key) | English | Turkish |
|---|---|---|
| الألوان والمقاسات | Colors and sizes | Renkler ve bedenler |
| هذا المنتج له ألوان أو مقاسات | This product has colors or sizes | Bu ürünün renkleri veya bedenleri var |
| اختر الألوان والمقاسات مرة واحدة، وتُنشأ كل التركيبات تلقائياً لتملأ كمياتها. | Pick the colors and sizes once; every combination is created for you to fill in its stock. | Renkleri ve bedenleri bir kez seçin; her kombinasyon otomatik oluşturulur, siz stoğunu girersiniz. |
| الألوان | Colors | Renkler |
| المقاسات | Sizes | Bedenler |
| لون آخر | Other color | Başka renk |
| اسم اللون | Color name | Renk adı |
| إضافة | Add | Ekle |
| إلغاء | Cancel | İptal |
| إضافة مقاس | Add size | Beden ekle |
| مقاس آخر (مثال: 42 عريض) | Other size (e.g. 42 wide) | Başka beden (örn. 42 geniş) |
| تحديد الكل | Select all | Tümünü seç |
| مسح | Clear | Temizle |
| الكميات والأسعار | Stock and prices | Stok ve fiyatlar |
| متوفر | Available | Mevcut |
| اللون | Color | Renk |
| المقاس | Size | Beden |
| الكمية | Quantity | Miktar |
| زيادة السعر (ل.س) | Price increase (SYP) | Fiyat artışı (SYP) |
| رمز SKU | SKU code | SKU kodu |
| إظهار رمز SKU لكل خيار | Show an SKU code for each option | Her seçenek için SKU kodu göster |
| الكمية لكل الخيارات | Quantity for all options | Tüm seçenekler için miktar |
| زيادة السعر لكل الخيارات | Price increase for all options | Tüm seçenekler için fiyat artışı |
| تطبيق | Apply | Uygula |
| تطبيق على هذا اللون | Apply to this color | Bu renge uygula |
| المجموع: :stock قطعة في :count خيار | Total: :stock items in :count options | Toplam: :count seçenekte :stock ürün |
| اختر لوناً أو مقاساً واحداً على الأقل لتظهر الخيارات هنا. | Choose at least one color or size to see the options here. | Seçeneklerin burada görünmesi için en az bir renk veya beden seçin. |
| فعّل خياراً واحداً على الأقل، أو أوقف الألوان والمقاسات. | Turn on at least one option, or switch colors and sizes off. | En az bir seçeneği açın veya renk ve bedenleri kapatın. |
| عدد الخيارات كبير جداً (الحد 200). قلّل الألوان أو المقاسات. | Too many options (limit 200). Remove some colors or sizes. | Çok fazla seçenek var (sınır 200). Bazı renk veya bedenleri kaldırın. |
| هذا اللون مضاف مسبقاً. | This color is already added. | Bu renk zaten eklendi. |
| اكتب اسم اللون. | Enter the color name. | Renk adını yazın. |
| لون مكتوب سابقاً — اضغط لاختيار درجته | Color typed earlier — tap to pick its shade | Daha önce yazılmış renk — tonunu seçmek için dokunun |
| إزالة | Remove | Kaldır |
| بدون لون | No color | Renksiz |
| يُحسب تلقائياً من الألوان والمقاسات | Calculated automatically from colors and sizes | Renk ve bedenlerden otomatik hesaplanır |
| سيتم حذف كل الألوان والمقاسات عند الحفظ. متابعة؟ | All colors and sizes will be deleted when you save. Continue? | Kaydettiğinizde tüm renk ve bedenler silinecek. Devam edilsin mi? |
| لون الصورة | Image color | Görsel rengi |
| درجة اللون | Shade | Ton |
| التشبع | Saturation | Doygunluk |
| السطوع | Brightness | Parlaklık |
| تعذّر تحميل الألوان والمقاسات. | Couldn't load colors and sizes. | Renkler ve bedenler yüklenemedi. |

Palette names and size-system names (Task 1, web only — the app receives them translated from the API):

| Arabic | English | Turkish |
|---|---|---|
| أسود | Black | Siyah |
| أبيض | White | Beyaz |
| رمادي | Grey | Gri |
| فضي | Silver | Gümüş |
| كحلي | Navy | Lacivert |
| أزرق | Blue | Mavi |
| سماوي | Sky blue | Gök mavisi |
| تركواز | Turquoise | Turkuaz |
| أخضر | Green | Yeşil |
| زيتي | Olive | Zeytin yeşili |
| كاكي | Khaki | Haki |
| بيج | Beige | Bej |
| كريمي | Cream | Krem |
| بني | Brown | Kahverengi |
| أصفر | Yellow | Sarı |
| ذهبي | Gold | Altın |
| برتقالي | Orange | Turuncu |
| أحمر | Red | Kırmızı |
| خمري | Burgundy | Bordo |
| وردي | Pink | Pembe |
| فوشيا | Fuchsia | Fuşya |
| بنفسجي | Purple | Mor |
| أحرف (ملابس) | Letters (clothing) | Harf (giyim) |
| أرقام (ملابس) | Numbers (clothing) | Numara (giyim) |
| أحذية | Shoes | Ayakkabı |
| جينز (الخصر) | Jeans (waist) | Kot (bel) |
| أطفال (بالعمر) | Kids (by age) | Çocuk (yaşa göre) |

---

### Task 1: Options catalog, API endpoint and `color_hex`

**Files:**
- Create: `app/Support/ProductOptions.php`
- Create: `app/Http/Controllers/Api/Seller/ProductOptionsController.php` (invokable)
- Modify: `routes/api.php` (seller group at the `Route::get('/products', …)` block)
- Modify: `app/Http/Resources/ProductVariantResource.php`, `app/Http/Resources/ProductImageResource.php`
- Modify: `lang/en.json`, `lang/tr.json` (palette + size-system names from the second Copy table)
- Test: `tests/Unit/ProductOptionsTest.php`, `tests/Feature/ProductOptionsApiTest.php`

**Interfaces:**
- Produces (`final class App\Support\ProductOptions`, all static):
  - `colors(): array` — `list<array{name: string, hex: string}>`, the 22 palette entries in spec order.
  - `sizeSystems(): array` — `list<array{key: string, name: string, sizes: list<string>}>`, the 5 systems in spec order.
  - `normalizeName(string $name): string` — spec normalization rules.
  - `isHex(?string $value): bool` — matches `/^#?[0-9A-Fa-f]{6}$/`.
  - `canonicalHex(string $value): string` — `'#'.strtoupper(ltrim($value, '#'))`.
  - `hexFor(?string $color, ?string $colorName = null): ?string` — spec rule.
- Produces: `GET /api/v1/seller/product-options` → `{"colors":[{"name","label","hex"}],"size_systems":[{"key","label","sizes"}]}`; `label` is `__($name)` in the request locale (`X-App-Locale` header, `SetApiLocale`).
- Produces: `color_hex` (nullable string) on `ProductVariantResource` and `ProductImageResource`.

- [ ] **Step 1: Write the failing unit tests** in `tests/Unit/ProductOptionsTest.php` (extends `Tests\TestCase` so `__()` works):
  - `test_palette_has_22_unique_names_and_hexes` — count 22; names unique; hexes unique and each matches `/^#[0-9A-F]{6}$/`.
  - `test_size_systems_are_in_spec_order` — keys `['letters','clothing','shoes','jeans','kids']`; `letters` sizes `['XS','S','M','L','XL','XXL','XXXL','4XL']`; `kids` first `'0-3M'`, last `'13-14Y'`.
  - `test_normalize_name_unifies_arabic_spellings` — `normalizeName('اسود') === normalizeName('أسود')`; `normalizeName(' أحمـــر ') === normalizeName('احمر')`; `normalizeName('وردىّ') === normalizeName('وردي')`; `normalizeName('Navy  Blue') === 'navy blue'`.
  - `test_hex_for` — `hexFor('d32f2f') === '#D32F2F'`; `hexFor('#1a1a1a') === '#1A1A1A'`; `hexFor('اسود') === '#1A1A1A'`; `hexFor('x', 'احمر') === '#D32F2F'`; `hexFor('لون غريب') === null`; `hexFor(null, null) === null`.
- [ ] **Step 2: Write the failing feature test** `tests/Feature/ProductOptionsApiTest.php`:
  - `test_seller_gets_catalog_with_translated_labels` — approved seller (`User` role `seller` + `SellerProfile` status `approved`), `Sanctum::actingAs`, GET with header `X-App-Locale: en` → 200; `colors.0` = `{name: 'أسود', label: 'Black', hex: '#1A1A1A'}`; `size_systems.0.key` = `letters`, `size_systems.0.label` = `Letters (clothing)`.
  - `test_customer_cannot_read_seller_catalog` — customer token → 403.
  - `test_variant_and_image_resources_expose_color_hex` — product with variant `color 'اسود'` and an image `color '#c0c0c0'`; GET the public product API used by the app/quick view (`/api/v1/products/{slug}` — confirm the route in `routes/api.php`) → `variants.0.color_hex` = `#1A1A1A`, `images.*.color_hex` contains `#C0C0C0`.
- [ ] **Step 3: Run tests to verify they fail** — `php artisan test --compact tests/Unit/ProductOptionsTest.php tests/Feature/ProductOptionsApiTest.php` → FAIL (class/route missing).
- [ ] **Step 4: Implement** `ProductOptions`, the controller (returns `response()->json([...])`), the route `Route::get('/product-options', ProductOptionsController::class);` inside the `auth:sanctum, role:seller, seller.store` group, and `color_hex` in both resources. Add the palette/size-system translations.
- [ ] **Step 5: Run tests to verify they pass** — same command → PASS. Also run `php artisan test --compact tests/Feature/ApiLocaleTest.php` → PASS.
- [ ] **Step 6: Pint and commit** — `vendor/bin/pint --dirty --format agent`; `git add app/Support/ProductOptions.php app/Http/Controllers/Api/Seller/ProductOptionsController.php routes/api.php app/Http/Resources/ProductVariantResource.php app/Http/Resources/ProductImageResource.php lang/en.json lang/tr.json tests/Unit/ProductOptionsTest.php tests/Feature/ProductOptionsApiTest.php`; `git commit -m "feat(products): shared color palette and size systems with color_hex"`.

### Task 2: Variant sync upgrades legacy colors in place

**Files:**
- Modify: `app/Services/ProductVariantSync.php`
- Modify: `app/Http/Controllers/Seller/ProductController.php` (`validateProduct`), `app/Http/Controllers/Api/Seller/ProductController.php` (`validateProduct`)
- Test: `tests/Feature/ProductVariantOptionsSyncTest.php`

**Interfaces:**
- Consumes: `ProductOptions::isHex`, `ProductOptions::canonicalHex` (Task 1).
- Produces: request field `variant_original_color[i]` (nullable string ≤ 50) understood by both controllers; `variant_price_adjustment.*` now `min:0`.

- [ ] **Step 1: Write the failing tests** (reuse the helper style of `tests/Feature/SellerOrdersAndStockTest.php`: approved seller, product, `productForm`-like payload with `replace_variants=1`):
  - `test_palette_color_replaces_legacy_word_in_place_keeping_cart_item` — variant `color 'اسود', color_name 'اسود', size 'M', stock 5` + `CartItem` on it; web `PUT seller.products.update` with `variant_color ['#1a1a1a']`, `variant_color_name ['أسود']`, `variant_size ['M']`, `variant_stock_quantity [3]`, `variant_original_color ['اسود']` → same variant id; `color '#1A1A1A'`, `color_name 'أسود'`, `stock_quantity 3`; cart item still exists; product has exactly 1 variant.
  - `test_images_follow_the_renamed_color` — product image `color 'اسود'` → after the same request its `color` is `#1A1A1A` and `color_name` is `أسود`.
  - `test_hex_colors_are_stored_uppercase_with_hash` — create via web with `variant_color ['d32f2f']` → stored `#D32F2F`.
  - `test_original_color_without_a_matching_variant_creates_a_new_one` — `variant_original_color ['ليس موجوداً']` → a new variant is created with the submitted color; no error.
  - `test_negative_price_increase_is_rejected_on_web` → response has session errors on `variant_price_adjustment.0`; no variant changed.
  - `test_api_update_upgrades_legacy_color_and_rejects_negative_price` — `Sanctum::actingAs` seller, `POST /api/v1/seller/products/{id}` with the rename payload → 200, same id, `#1A1A1A`; then with `variant_price_adjustment [-5]` → 422 with `errors['variant_price_adjustment.0']`.
- [ ] **Step 2: Run** `php artisan test --compact tests/Feature/ProductVariantOptionsSyncTest.php` → FAIL.
- [ ] **Step 3: Implement.** In `rows()`: read `variant_original_color`; canonicalize hex colors; keep `original_color` in the row only for lookup (strip it before `create`/`update`). In `apply()`: lookup by `(color, size)`; if none and `original_color` is non-null and differs from `color`, lookup by `(original_color, size)` and update that variant in place; remember `original → [color, color_name]`; after the loop, before the replace-deletion, update `$product->images()` whose `color` equals each original key. Add the validation rules to both controllers.
- [ ] **Step 4: Run** the new test file and `tests/Feature/SellerOrdersAndStockTest.php` → PASS.
- [ ] **Step 5: Pint and commit** — `feat(products): upgrade legacy variant colors in place and refuse negative price increases`.

### Task 3: Web options editor (switch, palette, size systems, matrix)

**Files:**
- Create: `app/Support/ProductOptionsEditorState.php`
- Create: `resources/views/seller/products/_options.blade.php`
- Create: `public/js/ts-product-options.js`
- Modify: `resources/views/seller/products/_form.blade.php` (replace the "الألوان والمقاسات (اختيارية)" card and its generator/add/remove JS; keep the image preview JS untouched — Task 4 owns it; dedupe the describe button's colors/sizes with `new Set`)
- Modify: `lang/en.json`, `lang/tr.json` (Copy table rows used on the web)
- Test: `tests/Unit/ProductOptionsEditorStateTest.php`, `tests/Feature/SellerProductOptionsFormTest.php`

**Interfaces:**
- Consumes: `ProductOptions::colors()`, `sizeSystems()` (Task 1); wire format (Task 2).
- Produces (`final class App\Support\ProductOptionsEditorState`): `make(?Product $product, array $old): array` returning `array{enabled: bool, rows: list<array{color: ?string, color_name: ?string, size: ?string, sku: ?string, price_adjustment: float|int, stock_quantity: int, original_color: ?string}>}`. Rules: if `$old` has any of `variant_options_enabled`, `variant_color`, `variant_size` → rows from the old arrays (index-aligned, skipping rows with neither color nor size), `enabled = ($old['variant_options_enabled'] ?? '0') === '1'`, `original_color` from `variant_original_color`; else if `$product` → rows from `$product->variants` (`price_adjustment / 100`, `original_color = color`), `enabled = rows !== []`; else `['enabled' => false, 'rows' => []]`.
- Produces (browser global from `ts-product-options.js`): `window.tsProductOptions = { colors(): Array<{key: string, name: string, hex: string|null, originalKey: string|null}>, onColorsChange(cb: (colors) => void): void, refreshColorSelects(): void }`. `refreshColorSelects` is a no-op until Task 4 adds selects.
- Produces (form fields): hidden `variant_options_enabled` (`1`/`0`), `replace_variants=1`, and the `variant_*[]` hidden inputs regenerated on every change inside `#ts-options-inputs`.
- Config: the partial prints `<script type="application/json" id="ts-options-config">` with `{colors: [{name, label, hex}], sizeSystems: [{key, label, sizes}], state: ProductOptionsEditorState::make(...), text: {…}}`; every `label`/`text` value is produced with `__()` (Copy table) because the dictionary middleware skips `<script>`.

- [ ] **Step 1: Write failing unit tests** `tests/Unit/ProductOptionsEditorStateTest.php`:
  - `test_old_input_wins_and_restores_switch_and_rows` — `$old` with `variant_options_enabled '1'`, two rows (`#D32F2F`/`M`/stock 2, `#D32F2F`/`L`/stock 0) and one empty row → `enabled true`, 2 rows, stock values `[2, 0]`.
  - `test_product_variants_load_with_price_in_pounds_and_original_color` — variant `color 'اسود'`, `price_adjustment 1500` → row `price_adjustment 15`, `original_color 'اسود'`, `enabled true`.
  - `test_new_product_starts_switched_off` → `['enabled' => false, 'rows' => []]`.
- [ ] **Step 2: Write failing feature tests** `tests/Feature/SellerProductOptionsFormTest.php`:
  - `test_create_form_renders_editor_config_with_palette_and_text` — GET `seller.products.create` → 200; HTML contains `id="ts-options-config"`, `ts-product-options.js`, `#1A1A1A`; no element named `variant_color[]` is rendered server-side as a visible text input (`assertDontSee('placeholder="مثال: أحمر"', false)`).
  - `test_saving_a_color_size_matrix_creates_every_available_combination` — POST `seller.products.store` with 2 colors × 2 sizes as the JS would submit (4 indexes, stocks `[1,2,3,4]`, `variant_options_enabled 1`, `replace_variants 1`) → product has 4 variants, `stock_quantity 10`.
  - Run `tests/Feature/WebSellerPagesTranslatedTest.php` after implementation — it covers the create/edit product pages in en/tr.
- [ ] **Step 3: Run** both new test files → FAIL.
- [ ] **Step 4: Implement** the state class, the partial (card heading/switch/intro copy in Arabic Blade; editor mount `#ts-options-root`; hidden inputs container `#ts-options-inputs`), and the JS editor implementing spec § Editor behavior 1–7: palette swatch buttons (`aria-pressed`, swatch color from hex, name below), "لون آخر" inline form (name + `<input type="color">`), size-system segmented buttons + size chips (`تحديد الكل`/`مسح` per system) + custom size input, matrix grouped by color (or flat when only one axis), bulk inputs, per-color apply, SKU column toggle, totals line, `#product-stock` read-only with value = total and hint "يُحسب تلقائياً من الألوان والمقاسات" while the switch is on, confirm (`window.tsConfirm`) before switching off when combinations exist, submit guard (zero available / >200 → `window.tsAlert`, `preventDefault`). Legacy loading: a non-hex row color whose normalized name matches a palette name becomes that palette color with `originalKey` = the legacy key; unmatched legacy colors become custom colors with `hex: null` (neutral striped swatch, title "لون مكتوب سابقاً — اضغط لاختيار درجته"; clicking opens the color input and assigns a hex while keeping `originalKey`). Combinations missing from loaded rows start unavailable. Load the script with `<script src="{{ asset('js/ts-product-options.js') }}?v={{ @filemtime(public_path('js/ts-product-options.js')) }}" defer></script>` from the partial via `@push('scripts')`. Style in the partial's `@push('styles')` with the site variables; on narrow screens (< 576px) each matrix row wraps to two lines without horizontal page scroll.
- [ ] **Step 5: Run** the two new test files plus `tests/Feature/WebSellerPagesTranslatedTest.php` and `tests/Feature/SellerOrdersAndStockTest.php` → PASS.
- [ ] **Step 6: Pint and commit** — `feat(products): color palette and size matrix editor on the seller web form`.

### Task 4: Web image color tags chosen from the editor's colors

**Files:**
- Modify: `resources/views/seller/products/_form.blade.php` (image preview JS: replace the free-text `image_color[idx]` input and the hidden name input with a `<select data-ts-color-select name="image_color[idx]">` and hidden `image_color_name[idx]`; call `window.tsProductOptions?.refreshColorSelects()` after each render)
- Modify: `resources/views/seller/products/edit.blade.php` (existing image color text input → `<select data-ts-color-select data-selected="{{ $image->color }}" name="existing_image_color[{{ $image->id }}]" form="seller-product-form">` + hidden `existing_image_color_name[{{ $image->id }}]`)
- Modify: `public/js/ts-product-options.js` (`refreshColorSelects` implementation; call it from every colors change)
- Test: `tests/Feature/SellerProductOptionsFormTest.php` (add tests)

**Interfaces:**
- Consumes: `window.tsProductOptions` (Task 3).
- Select contract: first option value `''` text "بدون لون"; one option per editor color (value = key, text = name). Selection: current value, else `data-selected`; a color whose `key` or `originalKey` equals it is selected (so legacy `اسود` selects the black palette color). A non-empty value matching no color stays as an extra option labelled with the value. The paired hidden name input (found via `data-ts-color-name-for="<select name>"` or the next sibling — pick one and use it consistently) gets the selected color's name, or `''`.

- [ ] **Step 1: Write failing tests** — `test_edit_page_offers_color_selects_for_saved_images`: product with one image `color 'اسود'` → GET edit page contains `data-ts-color-select`, `data-selected="اسود"` and `name="existing_image_color_name[<id>]"`, and does not contain `placeholder="لون الصورة (اختياري)"`. `test_image_colors_are_saved_from_the_selects`: PUT update with `existing_image_color[<id>] = '#1A1A1A'`, `existing_image_color_name[<id>] = 'أسود'` → image updated.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** per the contract.
- [ ] **Step 4: Run** `tests/Feature/SellerProductOptionsFormTest.php` and `tests/Feature/WebSellerPagesTranslatedTest.php` → PASS.
- [ ] **Step 5: Pint and commit** — `feat(products): pick image colors from the product's colors`.

### Task 5: Storefront swatches use `color_hex`

**Files:**
- Modify: `resources/views/products/show.blade.php` (color buttons: `$hex = \App\Support\ProductOptions::hexFor($v->color, $v->color_name)`; render the swatch whenever `$hex` is not null)
- Modify: `resources/views/partials/quick-view.blade.php` (colors from `img.color_hex`; skip images without it)
- Test: `tests/Feature/ProductOptionsStorefrontTest.php`

**Interfaces:**
- Consumes: `ProductOptions::hexFor` (Task 1), `color_hex` on `ProductImageResource` (Task 1).

- [ ] **Step 1: Write the failing test** `test_legacy_word_color_gets_a_palette_swatch_on_the_product_page` — active, approved product with variant `color 'اسود'` → GET `products.show` contains `background:#1A1A1A`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** both views.
- [ ] **Step 4: Run** the test and `tests/Feature/ProductBrowsingViewsTest.php` → PASS.
- [ ] **Step 5: Commit** — `feat(storefront): color swatches for every variant color`.

### Task 6: App catalog model, `color_hex` and seller service

**Files:**
- Create: `lib/models/product_options.dart`
- Modify: `lib/models/product.dart` (`ProductVariant.colorHex`, `ProductImage.colorHex` from `color_hex`)
- Modify: `lib/services/seller_service.dart` (`productOptions()`; `variantFields` sends `variant_original_color`)
- Modify: `lib/screens/product/product_detail_screen.dart` (swatch color from `colorHex ?? color`)
- Test: `test/product_options_model_test.dart`

**Interfaces:**
- Produces (`lib/models/product_options.dart`):
  - `class ColorOption { final String name; final String label; final String hex; factory ColorOption.fromJson(Map<String, dynamic>) }`
  - `class SizeSystem { final String key; final String label; final List<String> sizes; factory SizeSystem.fromJson(...) }`
  - `class ProductOptionsCatalog { final List<ColorOption> colors; final List<SizeSystem> sizeSystems; factory ProductOptionsCatalog.fromJson(...); ColorOption? byHex(String hex); ColorOption? matchName(String? name) }` (`matchName` compares `normalizeColorName`).
  - top-level `String normalizeColorName(String input)`, `bool isHexColor(String? value)`, `String canonicalHex(String value)`, `Color? colorFromHex(String? value)` — same rules as PHP.
- Produces (`SellerService`): `Future<ProductOptionsCatalog> productOptions()` → `GET /seller/product-options`; `variantFields` maps a map key `original_color` to `variant_original_color[$i]` (omitted when blank, like the other keys).

- [ ] **Step 1: Write failing tests** — catalog `fromJson` round-trip (2 colors, 1 system); `normalizeColorName('اسود') == normalizeColorName('أسود')`, `normalizeColorName(' أحمـــر ') == normalizeColorName('احمر')`; `matchName('احمر')?.hex == '#D32F2F'` on a catalog containing أحمر; `canonicalHex('d32f2f') == '#D32F2F'`; `colorFromHex('#FF0000') == const Color(0xFFFF0000)`; `ProductVariant.fromJson({... 'color': 'اسود', 'color_hex': '#1A1A1A'}).colorHex == '#1A1A1A'`; `SellerService.variantFields([{'color': '#1A1A1A', 'size': 'M', 'stock_quantity': '2', 'original_color': 'اسود'}])['variant_original_color[0]'] == 'اسود'`.
- [ ] **Step 2: Run** `flutter test test/product_options_model_test.dart` → FAIL.
- [ ] **Step 3: Implement.** In the product detail swatches use `colorFromHex(v.colorHex ?? v.color)`; when null keep a neutral outlined swatch with the name.
- [ ] **Step 4: Run** the new test file and `flutter analyze` → PASS / `No issues found!`.
- [ ] **Step 5: Commit** — `feat(products): options catalog model and color_hex`.

### Task 7: App options controller (pure logic)

**Files:**
- Create: `lib/screens/seller/product_options_controller.dart`
- Modify: `assets/l10n/en.json`, `assets/l10n/tr.json` (the four messages this task returns, from the Copy table — `test/l10n_coverage_test.dart` scans `lib/screens/seller`)
- Test: `test/product_options_controller_test.dart`

**Interfaces:**
- Consumes: Task 6 model and helpers; `ProductVariant` (`color`, `colorName`, `size`, `sku`, `priceAdjustment` — already in SYP, `stockQuantity`).
- Produces:
  - `class OptionColor { final String key; final String name; final String? hex; final String? originalKey; }`
  - `class OptionCombo { bool available; String stock; String priceIncrease; String sku; }`
  - `class ProductOptionsController extends ChangeNotifier` with: constructor `ProductOptionsController(ProductOptionsCatalog catalog)`; `bool enabled`; `List<OptionColor> get colors`; `List<String> get sizes`; `void setEnabled(bool value)`; `bool isPaletteSelected(ColorOption c)`; `void togglePaletteColor(ColorOption c)`; `String? addCustomColor(String name, String hex)` (returns the Arabic error `'اكتب اسم اللون.'` or `'هذا اللون مضاف مسبقاً.'`, else null); `void recolor(String key, String hex)`; `void removeColor(String key)`; `void toggleSize(String size)`; `bool addCustomSize(String size)`; `List<({OptionColor? color, String? size})> get combinations`; `OptionCombo combo(String? colorKey, String? size)`; `void setAllStock(String v)`; `void setAllPriceIncrease(String v)`; `void setColorStock(String colorKey, String v)`; `int get totalStock`; `int get availableCount`; `String? validate()` (Arabic messages `'فعّل خياراً واحداً على الأقل، أو أوقف الألوان والمقاسات.'` / `'عدد الخيارات كبير جداً (الحد 200). قلّل الألوان أو المقاسات.'`, null when disabled or valid); `List<Map<String, String>> toVariantMaps()` (keys `color`, `color_name`, `size`, `sku`, `price_adjustment`, `stock_quantity`, `original_color`; only available combos; blank stock/price → `'0'`; empty list when disabled); `void load(List<ProductVariant> variants)`.
  - Combination values are stored by key `'${colorKey ?? ''}|${size ?? ''}'` and survive toggling.

- [ ] **Step 1: Write failing tests**:
  - `matrix is colors times sizes in selection order` — toggle red, black, then M, L → 4 combinations, order red-M, red-L, black-M, black-L.
  - `values survive toggling a size off and on` — set stock 7 on red-M, toggle M off and on → stock still `'7'`.
  - `duplicate custom color hex is refused` — red selected, `addCustomColor('قرمزي', '#d32f2f')` → `'هذا اللون مضاف مسبقاً.'`; colors count unchanged. Blank name → `'اكتب اسم اللون.'`.
  - `duplicate size ignoring case and spaces` — `addCustomSize('M')` after M selected via `toggleSize('M')` → false for `' m '`.
  - `legacy word color maps to palette and keeps original key` — `load([variant(color: 'اسود', colorName: 'اسود', size: 'M', stock: 4, priceAdjustment: 15)])` → `colors.single.key == '#1A1A1A'`, `originalKey == 'اسود'`; `toVariantMaps().single` has `color '#1A1A1A'`, `color_name 'أسود'`, `original_color 'اسود'`, `stock_quantity '4'`, `price_adjustment '15'` (no extra division); `enabled == true`.
  - `unknown legacy color stays custom with null hex` — `load(color 'زهري فاتح')` → key `'زهري فاتح'`, `hex == null`; after `recolor('زهري فاتح', '#FFC0CB')` the map has `color '#FFC0CB'`, `original_color 'زهري فاتح'`.
  - `unavailable combos are not sent and totals count only available` — 2×2, mark one unavailable, stocks 1..4 → `toVariantMaps().length == 3`, `totalStock` = sum of the three.
  - `validate` — enabled with colors but all unavailable → the first message; 201 combinations → the second; disabled → null and `toVariantMaps()` empty.
- [ ] **Step 2: Run** `flutter test test/product_options_controller_test.dart` → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the test file, `flutter test test/l10n_coverage_test.dart` and `flutter analyze` → PASS.
- [ ] **Step 5: Commit** — `feat(seller): product options controller`.

### Task 8: App options editor widget

**Files:**
- Create: `lib/screens/seller/product_options_editor.dart`
- Modify: `assets/l10n/en.json`, `assets/l10n/tr.json` (Copy table — app rows)
- Test: `test/product_options_editor_test.dart`

**Interfaces:**
- Consumes: `ProductOptionsController` (Task 7), `ColorOption`/`SizeSystem`/`colorFromHex` (Task 6).
- Produces: `class ProductOptionsEditor extends StatelessWidget { const ProductOptionsEditor({super.key, required this.controller}); }` (listens with `ListenableBuilder`); top-level `Future<({String name, String hex})?> showCustomColorDialog(BuildContext context, {String? initialName})` — name field + three `Slider`s (hue 0–360 "درجة اللون", saturation 0–1 "التشبع", brightness 0–1 "السطوع") via `HSVColor`, live preview circle + hex text; returns null on cancel.
- Layout (spec § Editor behavior): `SwitchListTile` "هذا المنتج له ألوان أو مقاسات"; when on: "الألوان" — `Wrap` of 40-px circular swatches (selected ring + check icon, name label under, `Semantics(button: true, selected: …)`), a "+ لون آخر" tile, legacy custom colors with a striped/neutral circle that opens the dialog to `recolor`; "المقاسات" — `ChoiceChip`s for size systems, `FilterChip`s for sizes, a text field + "إضافة مقاس"; "الكميات والأسعار" — bulk row ("الكمية لكل الخيارات" field + "تطبيق", "زيادة السعر لكل الخيارات" + "تطبيق"), SKU toggle, then per color a card (swatch + name + "تطبيق على هذا اللون") with one row per size: `Switch` "متوفر", size label, stock field, price-increase field, SKU field when shown; totals line from "المجموع: :stock قطعة في :count خيار" with both placeholders replaced. Text fields keep their own `TextEditingController`s keyed by combination so typing does not lose focus on rebuild.

- [ ] **Step 1: Write a failing widget test** — pump `MaterialApp` with the app's localization delegates (copy the setup in `test/web_parity_screens_test.dart`) and a controller over a catalog with أحمر/أسود and the `letters` system: tap the switch; tap the swatch labelled أحمر; tap chip `M`; enter `5` in the stock field → `controller.totalStock == 5`; enter `3` in "الكمية لكل الخيارات" and tap "تطبيق" → `controller.combo('#D32F2F', 'M').stock == '3'`.
- [ ] **Step 2: Run** `flutter test test/product_options_editor_test.dart` → FAIL.
- [ ] **Step 3: Implement** the widget and dialog; add the Copy-table strings to both l10n files.
- [ ] **Step 4: Run** the widget test, `flutter test test/l10n_coverage_test.dart`, and `flutter analyze` → PASS.
- [ ] **Step 5: Commit** — `feat(seller): visual colors and sizes editor`.

### Task 9: Seller product form uses the editor

**Files:**
- Modify: `lib/screens/seller/seller_product_form_screen.dart`
- Test: `test/seller_product_form_options_test.dart`

**Interfaces:**
- Consumes: `SellerService.productOptions()`/`variantFields` (Task 6), `ProductOptionsController` (Task 7), `ProductOptionsEditor`/`showCustomColorDialog` (Task 8).
- Produces: `SellerProductFormScreen({super.key, this.product, SellerService? sellerService, ProductService? productService})` — optional services for tests (default to the real ones).

- [ ] **Step 1: Write failing tests** (fake `SellerService`/`ProductService` subclasses like `_FakeAdminOps` in `test/web_parity_screens_test.dart`; the fake `updateProduct` captures `fields`):
  - `editing a legacy product sends the palette color with its original key` — product variant `color 'اسود'`, `colorName 'اسود'`, `size 'M'`, `stockQuantity 4`, `priceAdjustment 15` → screen shows the editor switched on; tap save → captured fields contain `variant_color[0] = '#1A1A1A'`, `variant_original_color[0] = 'اسود'`, `variant_stock_quantity[0] = '4'`, `variant_price_adjustment[0] = '15'`, `stock_quantity = '4'`, `replace_variants = '1'`.
  - `stock field is read-only while options are on`.
  - `saving with every combination unavailable shows the message and does not call updateProduct`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.** Remove `_VariantRow`, the size presets and the old variant UI. Load categories, product and `productOptions()` in `_loadInitialData`; create the controller and `load(full.variants)`. Product stock field `readOnly` and kept equal to `controller.totalStock` while `controller.enabled`. Describe button sends the distinct `colors.map(name)` and `sizes`. Submit: `controller.validate()` (show it in the existing `_error` area), then `SellerService.variantFields(controller.toVariantMaps(), replace: widget.isEditing)`. Image color tags: every new image thumbnail and every existing image gets a small color chip; tapping opens a bottom sheet listing "بدون لون" + the controller's colors; send `image_color[i]`/`image_color_name[i]` for new images (index in `_newImages`) and `existing_image_color[id]`/`existing_image_color_name[id]` for existing ones (an existing legacy color whose `originalKey` matches is shown as, and sent as, the mapped color).
- [ ] **Step 4: Run** the new test, `flutter test` (whole suite) and `flutter analyze` → all pass, `No issues found!`.
- [ ] **Step 5: Commit** — `feat(seller): product form uses the colors and sizes editor`.
