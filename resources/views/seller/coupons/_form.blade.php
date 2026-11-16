{{-- نموذج كوبون البائع (إنشاء وتعديل). المبالغ بالليرة. $locked: بعد أول جمع لا يُعدَّل إلا النهاية والعدد --}}
@php
    $locked = $campaign->exists && $campaign->isLocked();
    $lira = fn ($minor) => $minor === null ? null : $minor / 100;
    $v = fn ($key, $default = null) => old($key, $default);
    $type = $v('type', $campaign->type);
    $selected = collect(old('product_ids', $campaign->exists ? $campaign->targetIds('product') : []))->map(fn ($i) => (int) $i)->all();
    $valueDefault = $campaign->type === 'fixed' ? $lira($campaign->value) : ($campaign->type === 'percentage' ? $campaign->value : null);
    $giftable = $products->where('variants_count', 0);
@endphp

@if ($locked)
    <div class="alert alert-info small">
        جمع العملاء هذا الكوبون، لذلك لا يمكن تغيير قيمته أو نوعه أو نطاقه. يمكنك تمديد مدته أو زيادة عدده، أو إيقافه.
    </div>
@endif

<fieldset @disabled($locked) class="mb-0">
    <div class="ts-cp-sec">
        <label class="form-label fw-bold" for="cpTitle">اسم الكوبون</label>
        <input id="cpTitle" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="120"
               value="{{ $v('title', $campaign->title) }}" placeholder="مثال: خصم الافتتاح" required>
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="ts-cp-sec">
        <span class="form-label fw-bold d-block">نوع الكوبون</span>
        <div class="ts-cp-types" role="radiogroup">
            @foreach (['percentage' => ['bi-percent', 'نسبة مئوية'], 'fixed' => ['bi-cash-coin', 'مبلغ ثابت'], 'free_shipping' => ['bi-truck', 'شحن مجاني'], 'gift' => ['bi-gift', 'منتج هدية']] as $key => [$icon, $label])
                <label class="ts-cp-type">
                    <input type="radio" name="type" value="{{ $key }}" @checked($type === $key) data-cp-type>
                    <span><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}</span>
                </label>
            @endforeach
        </div>
        @error('type')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="ts-cp-sec row g-2" data-cp-show="percentage fixed">
        <div class="col-sm-6">
            <label class="form-label" for="cpValue"><span data-cp-show="percentage">النسبة %</span><span data-cp-show="fixed">مبلغ الخصم (ل.س)</span></label>
            <input id="cpValue" type="number" name="value" step="1" min="1" class="form-control @error('value') is-invalid @enderror"
                   value="{{ $v('value', $valueDefault) }}">
            @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text" data-cp-show="percentage">{{ __('حتى :max%. فوق :review% يحتاج موافقة الإدارة.', ['max' => \App\Coupons\SellerCampaignWriter::MAX_PERCENT, 'review' => \App\Coupons\Models\CouponCampaign::REVIEW_ABOVE_PERCENT]) }}</div>
        </div>
        <div class="col-sm-6" data-cp-show="percentage">
            <label class="form-label" for="cpMax">أعلى مبلغ للخصم (ل.س)</label>
            <input id="cpMax" type="number" name="max_discount" step="1" min="1" class="form-control @error('max_discount') is-invalid @enderror"
                   value="{{ $v('max_discount', $lira($campaign->max_discount)) }}">
            @error('max_discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <div class="alert alert-warning small mb-0 d-none" id="cpReviewNote">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i> {{ __('الخصم أكثر من :review%، لذلك سيظهر للعملاء بعد موافقة الإدارة.', ['review' => \App\Coupons\Models\CouponCampaign::REVIEW_ABOVE_PERCENT]) }}
            </div>
        </div>
    </div>

    <div class="ts-cp-sec" data-cp-show="free_shipping">
        <div class="alert alert-light border small mb-0">
            {{ __('الشحن المجاني يعمل عندما تكون كل منتجات السلة من متجرك، وتُخصم أجرة الشحن (:fee ل.س) من أرباحك عند تسليم الطلب.', ['fee' => number_format(\App\Http\Controllers\Customer\CheckoutController::SHIPPING_FEE / 100)]) }}
        </div>
    </div>

    <div class="ts-cp-sec row g-2" data-cp-show="gift">
        @if ($giftable->isEmpty())
            <div class="col-12 small text-muted">تحتاج منتجاً بلا ألوان أو مقاسات لتجعله هدية.</div>
        @endif
        <div class="col-sm-6">
            <label class="form-label" for="cpBuy">عندما يشتري العميل</label>
            <select id="cpBuy" name="buy_product_id" class="form-select @error('buy_product_id') is-invalid @enderror">
                <option value="">اختر منتجاً</option>
                @foreach ($products as $p)
                    <option value="{{ $p->id }}" @selected((int) $v('buy_product_id', $campaign->buy_product_id) === $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
            @error('buy_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="cpGift">يحصل هدية على</label>
            <select id="cpGift" name="gift_product_id" class="form-select @error('gift_product_id') is-invalid @enderror">
                <option value="">اختر منتجاً</option>
                @foreach ($giftable as $p)
                    <option value="{{ $p->id }}" @selected((int) $v('gift_product_id', $campaign->gift_product_id) === $p->id)>{{ $p->name }} (المخزون {{ $p->stock_quantity }})</option>
                @endforeach
            </select>
            @error('gift_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="ts-cp-sec">
        <span class="form-label fw-bold d-block">على أي منتجات؟</span>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="cpScopeAll" value="all" @checked($v('scope', $campaign->scope) === 'all') data-cp-scope>
            <label class="form-check-label" for="cpScopeAll">كل منتجات متجري</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="cpScopeSome" value="products" @checked($v('scope', $campaign->scope) === 'products') data-cp-scope>
            <label class="form-check-label" for="cpScopeSome">منتجات أختارها</label>
        </div>
        <div class="ts-cp-products mt-2 {{ $v('scope', $campaign->scope) === 'products' ? '' : 'd-none' }}" id="cpProducts">
            @forelse ($products as $p)
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, $selected, true))>
                    <span class="form-check-label">{{ $p->name }} <small class="text-muted">{{ number_format($p->price / 100) }} ل.س</small></span>
                </label>
            @empty
                <p class="small text-muted mb-0">لا توجد منتجات في متجرك بعد.</p>
            @endforelse
        </div>
        @error('product_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        @error('product_ids.*')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>

    <div class="ts-cp-sec">
        <span class="form-label fw-bold d-block">كيف يصل للعملاء؟</span>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="audience" id="cpAudCollect" value="collectable" @checked($v('audience', $campaign->audience) === 'collectable') data-cp-aud>
            <label class="form-check-label" for="cpAudCollect">يحفظه العميل من صفحة متجري ومنتجاتي</label>
        </div>
        <div class="form-check ms-4" id="cpAutoFollowersWrap">
            <input type="hidden" name="auto_followers" value="0">
            <input class="form-check-input" type="checkbox" name="auto_followers" id="cpAutoFollowers" value="1" @checked($v('auto_followers', $campaign->auto_followers))>
            <label class="form-check-label" for="cpAutoFollowers">وأرسله أيضاً لمتابعي متجري</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="audience" id="cpAudFollowers" value="followers" @checked($v('audience', $campaign->audience) === 'followers') data-cp-aud>
            <label class="form-check-label" for="cpAudFollowers">لمتابعي متجري فقط</label>
        </div>
        <div class="small text-muted mt-1" id="cpFollowersNote">
            {{ __('سيصل إلى :n متابعاً، ولكل من يتابعك لاحقاً حتى ينتهي الكوبون.', ['n' => number_format($followersCount)]) }}
            <span class="text-warning d-none" id="cpFollowersWarn">العدد الإجمالي أقل من عدد المتابعين؛ سيتوقف الإرسال عند نفاده.</span>
        </div>
    </div>
</fieldset>

<div class="ts-cp-sec row g-2">
    <div class="col-sm-6" @if ($locked) hidden @endif>
        <label class="form-label" for="cpMin">الحد الأدنى لمشتريات متجرك في السلة (ل.س)</label>
        <input id="cpMin" type="number" name="min_order_amount" min="0" step="1" class="form-control @error('min_order_amount') is-invalid @enderror"
               value="{{ $v('min_order_amount', $lira($campaign->min_order_amount) ?: '') }}" placeholder="0 = بلا حد" @disabled($locked)>
        @error('min_order_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6" @if ($locked) hidden @endif>
        <label class="form-label" for="cpStart">يبدأ في (اختياري)</label>
        <input id="cpStart" type="date" name="starts_at" class="form-control @error('starts_at') is-invalid @enderror"
               value="{{ $v('starts_at', $campaign->exists ? $campaign->starts_at?->format('Y-m-d') : '') }}" min="{{ now()->format('Y-m-d') }}" @disabled($locked)>
        @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="cpEnd">ينتهي في</label>
        <input id="cpEnd" type="date" name="ends_at" class="form-control @error('ends_at') is-invalid @enderror" required
               value="{{ $v('ends_at', $campaign->ends_at?->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d')) }}"
               min="{{ ($locked ? $campaign->ends_at : now())->format('Y-m-d') }}" max="{{ now()->addYear()->format('Y-m-d') }}">
        @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="cpTotal">عدد الكوبونات</label>
        <input id="cpTotal" type="number" name="total_limit" min="{{ $locked ? $campaign->total_limit : 1 }}" max="100000" step="1" required
               class="form-control @error('total_limit') is-invalid @enderror" value="{{ $v('total_limit', $campaign->total_limit ?? 100) }}">
        @error('total_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">كم عميلاً يستطيع حفظه. تكلفتك القصوى معروفة مسبقاً.</div>
    </div>
    <div class="col-sm-6" @if ($locked) hidden @endif>
        <label class="form-label" for="cpPer">مرات الاستخدام لكل عميل</label>
        <input id="cpPer" type="number" name="per_customer_limit" min="1" max="5" step="1" required @disabled($locked)
               class="form-control @error('per_customer_limit') is-invalid @enderror" value="{{ $v('per_customer_limit', $campaign->per_customer_limit ?? 1) }}">
        @error('per_customer_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6" @if ($locked) hidden @endif>
        <label class="form-label" for="cpDays">صلاحية الكوبون بعد الحفظ بالأيام (اختياري)</label>
        <input id="cpDays" type="number" name="valid_days" min="1" max="365" step="1" @disabled($locked)
               class="form-control @error('valid_days') is-invalid @enderror" value="{{ $v('valid_days', $campaign->valid_days) }}">
        @error('valid_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

@unless ($locked)
    <div class="ts-cp-preview" id="cpPreview" aria-live="polite"></div>
@endunless

<script>
(() => {
    const form = document.currentScript.closest('form');
    const rate = {{ $commissionRate }};
    const reviewAbove = {{ \App\Coupons\Models\CouponCampaign::REVIEW_ABOVE_PERCENT }};
    const followers = {{ $followersCount }};
    const $ = (s) => form.querySelector(s);
    const fmt = (n) => Math.round(n).toLocaleString('en-US') + ' ' + @json(__('ل.س'));
    const type = () => (form.querySelector('[data-cp-type]:checked') || {}).value;

    function refresh() {
        const t = type();
        form.querySelectorAll('[data-cp-show]').forEach((el) => { el.hidden = !el.dataset.cpShow.split(' ').includes(t); });
        const scope = (form.querySelector('[data-cp-scope]:checked') || {}).value;
        $('#cpProducts').classList.toggle('d-none', scope !== 'products');
        const aud = (form.querySelector('[data-cp-aud]:checked') || {}).value;
        $('#cpAutoFollowersWrap').hidden = aud !== 'collectable';
        const reach = aud === 'followers' || $('#cpAutoFollowers').checked;
        $('#cpFollowersNote').hidden = !reach;
        $('#cpFollowersWarn').classList.toggle('d-none', !(reach && parseInt($('#cpTotal').value || '0', 10) < followers));
        const val = parseFloat($('#cpValue').value || '0');
        $('#cpReviewNote').classList.toggle('d-none', !(t === 'percentage' && val > reviewAbove));

        const out = $('#cpPreview');
        if (!out) return;
        const sample = Math.max(parseFloat($('#cpMin').value || '0'), 100000);
        let discount = 0;
        if (t === 'percentage') { discount = sample * val / 100; const max = parseFloat($('#cpMax').value || '0'); if (max > 0) discount = Math.min(discount, max); }
        if (t === 'fixed') discount = val;
        discount = Math.max(0, Math.min(discount, sample));
        const base = sample - discount;
        out.innerHTML = (t === 'percentage' || t === 'fixed') && val > 0
            ? '<strong>' + @json(__('مثال:')) + '</strong> ' + @json(__('على طلب من متجرك بقيمة :sample: يدفع العميل :base، ويصلك :net بعد العمولة (:rate%). الخصم :discount من أرباحك.')).replace(':sample', fmt(sample)).replace(':base', fmt(base)).replace(':net', fmt(base - base * rate / 100)).replace(':rate', rate).replace(':discount', fmt(discount))
            : (t === 'free_shipping' ? '<strong>' + @json(__('مثال:')) + '</strong> ' + @json(__('تُخصم أجرة الشحن من أرباحك عند تسليم كل طلب استخدم الكوبون.'))
            : (t === 'gift' ? '<strong>' + @json(__('مثال:')) + '</strong> ' + @json(__('يصل منتج الهدية مع الطلب بسعر 0 ويُخصم من مخزونك.')) : ''));
    }
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
})();
</script>
