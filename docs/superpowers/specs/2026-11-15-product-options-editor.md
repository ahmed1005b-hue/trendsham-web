# Product colors & sizes editor — design spec

Date: 2026-11-15 · Status: approved by the owner's standing rule ("choose the best option yourself")

## Problem

Sellers add colors and sizes (product variants) one row at a time: each row has free-text
"color", "color display name", "size", SKU, price adjustment and stock. Real data shows the
cost of this design:

- Colors are typed as words (`اسود`, `احمر`), so the storefront quick view and the Flutter
  product page — which both expect `color` to be a hex code — draw no swatch.
- A seller typed every size into one cell (`s.m.l.xl`), producing one variant instead of four.
- A T-shirt in 4 colors × 6 sizes needs 24 hand-filled rows; the size generator only fills one
  color at a time; the web "bulk color" field is even bound to the wrong variable.
- Linking an image to a color means retyping the color text exactly.
- `price_adjustment` is `unsignedBigInteger` but validation accepts negatives (DB error).
- The Flutter edit screen divides the price adjustment by 100 twice (the API already divides).

## Decision: options → combinations matrix

The seller picks **colors** (visual palette + custom color) and **sizes** (size systems as
chips + custom size). The editor builds every color × size **combination** automatically; the
seller only fills stock (and optionally price increase / SKU) per combination, with bulk fill.

### Data model (no migration)

`product_variants` keeps its columns. Meaning, which the original migration already documents:

- `color` — the identity key of the color. New values are always canonical hex `#RRGGBB`
  (uppercase). Legacy word values stay valid until the seller edits the product.
- `color_name` — display name. Palette colors store the canonical **Arabic** palette name; the
  web storefront translates it through the existing dictionary middleware.
- `size` — free string; chosen from size systems or typed.
- `price_adjustment` — a non-negative **price increase** in piastres (×100), as today.

Product images keep `color`/`color_name`; they link to variants through the same `color` key.

### Shared catalog (server is the single source of truth)

`App\Support\ProductOptions` defines the palette and size systems. The web editor receives it
inline; the app fetches it from `GET /api/v1/seller/product-options`.

Palette (name → hex), in this order:

| name | hex | name | hex |
|---|---|---|---|
| أسود | #1A1A1A | بيج | #D8C3A5 |
| أبيض | #FFFFFF | كريمي | #F3E9D2 |
| رمادي | #9E9E9E | بني | #6D4C41 |
| فضي | #C0C0C0 | أصفر | #FBC02D |
| كحلي | #1F2A44 | ذهبي | #C9A227 |
| أزرق | #1E63C6 | برتقالي | #F57C00 |
| سماوي | #7EC8E3 | أحمر | #D32F2F |
| تركواز | #1ABC9C | خمري | #7B1E2B |
| أخضر | #2E7D32 | وردي | #F48FB1 |
| زيتي | #6B6E2E | فوشيا | #D81B60 |
| كاكي | #B5A27A | بنفسجي | #7B1FA2 |

Size systems (key · Arabic name · sizes in order):

- `letters` · أحرف (ملابس) · XS, S, M, L, XL, XXL, XXXL, 4XL
- `clothing` · أرقام (ملابس) · 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56
- `shoes` · أحذية · 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46
- `jeans` · جينز (الخصر) · 26, 27, 28, 29, 30, 31, 32, 33, 34, 36, 38, 40
- `kids` · أطفال (بالعمر) · 0-3M, 3-6M, 6-12M, 1-2Y, 2-3Y, 3-4Y, 4-5Y, 5-6Y, 7-8Y, 9-10Y, 11-12Y, 13-14Y

Kids sizes are language-neutral codes so the app shows them correctly in every language.

**Name normalization** (used to map legacy words to palette colors, identical in PHP, JS, Dart):
trim; collapse inner whitespace to one space; remove tatweel `ـ` and Arabic diacritics
U+064B–U+0652; `أ`/`إ`/`آ` → `ا`; `ى` → `ي`; `ة` → `ه`; lowercase Latin letters.
So `اسود` matches `أسود`, `احمر` matches `أحمر`.

`ProductOptions::hexFor(color, colorName)`: a hex `color` (`#?[0-9A-Fa-f]{6}`) → canonical
`#RRGGBB`; otherwise the palette hex whose normalized name equals the normalized `colorName`
or `color`; otherwise `null`. API resources expose it as `color_hex` on variants and images;
the storefront swatches use it, so legacy products get swatches without being edited.

### Wire format (web form and app multipart — unchanged plus one field)

Per combination `i`: `variant_color[i]`, `variant_color_name[i]`, `variant_size[i]`,
`variant_sku[i]`, `variant_price_adjustment[i]` (SYP, ≥ 0), `variant_stock_quantity[i]` (≥ 0),
and the **new optional** `variant_original_color[i]` — the color key this combination had when
the editor loaded it. Plus `replace_variants=1`. Images: `image_color[i]`/`image_color_name[i]`
for new uploads (index-aligned with `images[]`), `existing_image_color[id]`/
`existing_image_color_name[id]` for saved images.

### Legacy upgrade without breaking carts

When the editor loads a variant whose `color` is not hex but whose name matches a palette
color, it shows the palette swatch, submits the palette hex as `variant_color` and the legacy
word as `variant_original_color`. `ProductVariantSync` then finds the existing variant by
`(original_color, size)` and updates it in place — same id, so cart items and order links
survive — and recolors product images whose `color` equals the original key. Legacy words with
no palette match appear as "custom colors" with a neutral swatch, keep their key, and can be
given a real color by the seller (same original-color mechanism).

### Editor behavior (web and app, same rules)

1. A switch "هذا المنتج له ألوان أو مقاسات". Off → no options are sent (`replace_variants=1`
   with no rows removes existing variants on save, as today) and the product stock field is
   editable. On → the product stock field is read-only and shows the computed total.
2. Colors: palette swatches toggle on tap/click; "لون آخر" adds a custom color (name + color
   picker → hex). A custom hex equal to an already-selected color is rejected with a message.
3. Sizes: choose a size system, tap sizes to toggle; "إضافة مقاس" adds a custom size; a size
   already selected is not added twice (comparison trims and ignores case). Sizes from several
   systems may be mixed; order = order of selection.
4. Combinations = selected colors × selected sizes (colors only → one per color; sizes only →
   one per size). Each has an "available" toggle, stock, price increase, SKU (SKU column hidden
   behind "إظهار رمز SKU لكل خيار"). Unavailable combinations are not submitted.
5. Bulk: "الكمية لكل الخيارات" + apply; "زيادة السعر لكل الخيارات" + apply; per-color stock
   apply. Values the seller typed survive toggling a color/size off and on during the session.
6. Totals line: "المجموع: :stock قطعة في :count خيار".
7. Saving with options on but zero available combinations is blocked with a message; more than
   200 combinations is blocked with a message.
8. Image color tags are chosen from the selected colors (plus "بدون لون"), never typed.
9. Validation-error round trip (web `old()` input) restores the editor exactly.

### Out of scope

Translating custom color names; per-combination images; changing cart/checkout; data
migration of legacy rows (they upgrade lazily on edit); the customer web product page layout
beyond swatch rendering.

## Constraints

- No new dependencies (composer, npm, pub). No database migrations.
- Web: Blade + vanilla JS (no build step) in `public/js/ts-product-options.js`, loaded with a
  `filemtime` cache-buster like `css/ts-app.css`. Colors from site CSS variables so dark mode
  works (`--ts-surface`, `--ts-surface-2`, `--ts-border`, `--ts-ink`, `--ts-muted`,
  `--ts-emerald`, `--ts-gold`); logical CSS properties so RTL and LTR both work.
- Web copy is written in Arabic in views/`__()`; every new phrase gets `lang/en.json` and
  `lang/tr.json` entries. App copy uses `AppText`/`context.tr` with `assets/l10n/en.json` and
  `tr.json` entries (enforced by `test/l10n_coverage_test.dart`).
- PHP follows Pint; Flutter passes `flutter analyze` with no issues.
