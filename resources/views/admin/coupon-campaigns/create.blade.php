@extends('layouts.app')

@section('title', 'كوبون جديد - لوحة المدير')

@section('content')
<nav class="navbar ts-header mb-4">
    <div class="container">
        <span class="navbar-brand mb-0 h1 ts-logo">تريند<span>شام</span> - كوبون جديد</span>
        <a href="{{ route('admin.coupon-campaigns.index') }}" class="btn btn-outline-secondary btn-sm">كل الكوبونات</a>
    </div>
</nav>

<div class="container mb-5" style="max-width: 820px">
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('admin.coupon-campaigns.store') }}" method="POST" class="card card-body" id="ts-campaign-form">
        @csrf
        <p class="small text-muted">{!! __('كوبون المنصة: :who ويأخذ البائع أرباحه كاملة.', ['who' => '<strong>'.e(__('المنصة تتحمّل الخصم')).'</strong>']) !!}</p>

        <h2 class="h6 mt-2">1. الاسم والنوع</h2>
        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label small" for="title">اسم الكوبون (يراه العميل)</label>
                <input id="title" name="title" class="form-control" maxlength="120" required value="{{ old('title') }}" placeholder="مثال: خصم الترحيب">
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="type">النوع</label>
                <select id="type" name="type" class="form-select" required>
                    @foreach (['percentage' => 'نسبة مئوية', 'fixed' => 'مبلغ ثابت', 'free_shipping' => 'شحن مجاني'] as $v => $l)
                        <option value="{{ $v }}" @selected(old('type', 'percentage') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4" data-show="percentage fixed">
                <label class="form-label small" for="value"><span data-show="percentage">النسبة %</span><span data-show="fixed">المبلغ (ل.س)</span></label>
                <input id="value" name="value" type="number" min="1" step="1" class="form-control" value="{{ old('value') }}">
            </div>
            <div class="col-md-4" data-show="percentage">
                <label class="form-label small" for="max_discount">الحد الأعلى للخصم (ل.س)</label>
                <input id="max_discount" name="max_discount" type="number" min="1" class="form-control" value="{{ old('max_discount') }}" placeholder="اختياري">
            </div>
            <div class="col-md-4">
                <label class="form-label small" for="min_order_amount">الحد الأدنى للسلة (ل.س)</label>
                <input id="min_order_amount" name="min_order_amount" type="number" min="0" class="form-control" value="{{ old('min_order_amount', 0) }}">
            </div>
        </div>

        <h2 class="h6 mt-4">2. على أي منتجات؟</h2>
        <div class="d-flex gap-3 flex-wrap mb-2">
            @foreach (['all' => 'كل الموقع', 'categories' => 'أقسام معيّنة', 'stores' => 'متاجر معيّنة'] as $v => $l)
                <label class="form-check"><input class="form-check-input" type="radio" name="scope" value="{{ $v }}" @checked(old('scope', 'all') === $v)> {{ $l }}</label>
            @endforeach
        </div>
        <div data-scope="categories">
            <select name="category_ids[]" class="form-select" multiple size="6" aria-label="الأقسام">
                @foreach ($categories as $cat)<option value="{{ $cat->id }}" @selected(in_array($cat->id, old('category_ids', [])))>{{ $cat->name }}</option>@endforeach
            </select>
            <div class="form-text">اضغط Ctrl لاختيار أكثر من قسم.</div>
        </div>
        <div data-scope="stores">
            <select name="store_ids[]" class="form-select" multiple size="6" aria-label="المتاجر">
                @foreach ($stores as $st)<option value="{{ $st->id }}" @selected(in_array($st->id, old('store_ids', [])))>{{ $st->store_name }}</option>@endforeach
            </select>
        </div>

        <h2 class="h6 mt-4">3. لمن يصل؟</h2>
        <div class="d-flex gap-3 flex-wrap mb-2">
            <label class="form-check"><input class="form-check-input" type="radio" name="audience" value="new_customers" @checked(old('audience') === 'new_customers')> {{ __('العملاء الجدد (:n حالياً)', ['n' => number_format($audienceSizes['new_customers'])]) }}</label>
            <label class="form-check"><input class="form-check-input" type="radio" name="audience" value="all_customers" @checked(old('audience', 'all_customers') === 'all_customers')> {{ __('كل العملاء (:n)', ['n' => number_format($audienceSizes['all_customers'])]) }}</label>
            <label class="form-check"><input class="form-check-input" type="radio" name="audience" value="specific" @checked(old('audience') === 'specific')> عميل معيّن</label>
        </div>
        <div class="form-text mb-2">"العملاء الجدد" = لم يطلبوا بعد وهاتفهم متحقَّق منه، ويشمل من يسجّل لاحقاً خلال مدة الكوبون.</div>
        <div data-audience="specific">
            <input name="customer_contact" class="form-control" value="{{ old('customer_contact') }}" placeholder="هاتف العميل أو بريده">
        </div>

        <h2 class="h6 mt-4">4. المدة والحدود</h2>
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small" for="starts_at">يبدأ</label><input id="starts_at" type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at') }}"></div>
            <div class="col-md-4"><label class="form-label small" for="ends_at">ينتهي</label><input id="ends_at" type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at') }}"></div>
            <div class="col-md-4"><label class="form-label small" for="valid_days">صالح لكل عميل (أيام)</label><input id="valid_days" type="number" min="1" max="365" name="valid_days" class="form-control" value="{{ old('valid_days') }}" placeholder="اختياري"></div>
            <div class="col-md-6"><label class="form-label small" for="per_customer_limit">مرات الاستخدام لكل عميل</label><input id="per_customer_limit" type="number" min="1" max="20" name="per_customer_limit" class="form-control" required value="{{ old('per_customer_limit', 1) }}"></div>
            <div class="col-md-6"><label class="form-label small" for="total_limit">العدد الإجمالي (أول N استخدام)</label><input id="total_limit" type="number" min="1" name="total_limit" class="form-control" value="{{ old('total_limit') }}" placeholder="بلا حد"></div>
        </div>

        <button class="btn btn-primary mt-4" data-confirm-when="audience=all_customers" data-confirm="{{ __('سيظهر هذا الكوبون في محفظة :n عميل. متأكد؟', ['n' => number_format($audienceSizes['all_customers'])]) }}">
            إنشاء الكوبون
        </button>
    </form>
</div>

@push('scripts')
<script>
(function () {
    var f = document.getElementById('ts-campaign-form');
    function sync() {
        var type = f.type.value, scope = f.scope.value, audience = f.audience.value;
        f.querySelectorAll('[data-show]').forEach(function (el) { el.hidden = el.getAttribute('data-show').split(' ').indexOf(type) === -1; });
        f.querySelectorAll('[data-scope]').forEach(function (el) { el.hidden = el.getAttribute('data-scope') !== scope; });
        f.querySelectorAll('[data-audience]').forEach(function (el) { el.hidden = el.getAttribute('data-audience') !== audience; });
    }
    f.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
@endsection
