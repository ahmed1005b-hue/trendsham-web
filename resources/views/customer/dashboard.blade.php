@extends('layouts.customer-account')
@php($accountTitle = 'حسابي')
@php($sp = auth()->user()?->sellerProfile)

@section('account_content')
    <div class="ts-account-card">
        <div class="ts-account-head">
            <h1>حسابي</h1>
            <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-secondary">تعديل المعلومات</a>
        </div>

        @if (auth()->user()?->role !== 'admin')
            @if ($sp && $sp->status !== 'approved')
                <div class="alert {{ $sp->status === 'rejected' ? 'alert-danger' : 'alert-warning' }} d-flex flex-column gap-1 mb-3">
                    <div><strong>حالة التحقق من هوية البائع:</strong> {{ method_exists($sp,'identityStatusLabel') ? $sp->identityStatusLabel() : $sp->status }}</div>
                    @if ($sp->status === 'rejected' && $sp->rejection_reason)<div class="small">سبب الرفض: {{ $sp->rejection_reason }}</div>@endif
                    <div><a href="{{ route('customer.seller-application.create') }}" class="btn btn-sm {{ $sp->status === 'rejected' ? 'btn-danger' : 'btn-outline-dark' }}">إدارة طلب التحقق</a></div>
                </div>
            @elseif (! $sp)
                <div class="border rounded-2 p-3 mb-3 d-flex align-items-center justify-content-between gap-2 flex-wrap" style="border-color:var(--ts-soft-border, #ededed) !important; background:#fffaf4;">
                    <div><strong>هل تريد البيع على تريندشام؟</strong><div class="small text-muted">ابدأ طلب فتح المتجر وأرسل وثيقة الهوية للمراجعة.</div></div>
                    <a href="{{ route('customer.seller-application.create') }}" class="btn btn-sm btn-buy">ابدأ البيع / تحقق من الهوية</a>
                </div>
            @else
                <div class="alert alert-success d-flex align-items-center justify-content-between gap-2 mb-3"><span><i class="bi bi-patch-check-fill"></i> تم اعتماد هوية متجرك.</span><a href="{{ route('seller.dashboard') }}" class="btn btn-sm btn-outline-success">لوحة المتجر</a></div>
            @endif
        @endif

        <div class="ts-account-stats">
            <div class="ts-account-stat"><div class="v">{{ $stats['orders_count'] ?? 0 }}</div><div class="l">طلب إجمالي</div></div>
            <div class="ts-account-stat"><div class="v">{{ $stats['pending_orders'] ?? 0 }}</div><div class="l">قيد التنفيذ</div></div>
            <div class="ts-account-stat"><div class="v">{{ $stats['favorites_count'] ?? 0 }}</div><div class="l">المفضلة</div></div>
            <div class="ts-account-stat"><div class="v">{{ $stats['followed_stores_count'] ?? 0 }}</div><div class="l">متاجر أتابعها</div></div>
        </div>

    <section class="ts-prefs mt-3 mb-4" aria-labelledby="ts-prefs-title">
        <div class="ts-prefs__head">
            <span class="ts-prefs__icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
            <div>
                <h2 id="ts-prefs-title" class="ts-prefs__title">اللغة والمظهر</h2>
                <p class="ts-prefs__sub">اختر لغة الموقع وشكل العرض المريح لعينيك</p>
            </div>
        </div>

        <div class="ts-prefs__group">
            <div class="ts-prefs__label"><i class="bi bi-translate" aria-hidden="true"></i> اللغة</div>
            <form method="POST" action="{{ route('preferences.language') }}" class="ts-prefs__options" role="radiogroup" aria-label="اللغة">
                @csrf
                @foreach (['ar' => ['ع', 'العربية'], 'en' => ['EN', 'English'], 'tr' => ['TR', 'Türkçe']] as $code => [$badge, $name])
                    @php($on = app()->getLocale() === $code)
                    <button type="submit" name="locale" value="{{ $code }}" class="ts-prefs__opt {{ $on ? 'is-active' : '' }}" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" translate="no">
                        <span class="ts-prefs__badge" translate="no">{{ $badge }}</span>
                        <span class="ts-prefs__name">{{ $name }}</span>
                        <i class="bi bi-check-circle-fill ts-prefs__check" aria-hidden="true"></i>
                    </button>
                @endforeach
            </form>
        </div>

        <div class="ts-prefs__group">
            <div class="ts-prefs__label"><i class="bi bi-palette2" aria-hidden="true"></i> المظهر</div>
            <div class="ts-prefs__options" id="ts-theme" role="radiogroup" aria-label="المظهر">
                @foreach (['light' => ['bi-sun-fill', 'فاتح'], 'dark' => ['bi-moon-stars-fill', 'داكن'], 'system' => ['bi-circle-half', 'حسب الجهاز']] as $val => [$icon, $name])
                    <button type="button" data-theme-pref="{{ $val }}" class="ts-prefs__opt ts-prefs__opt--{{ $val }}" role="radio" aria-checked="false">
                        <span class="ts-prefs__badge"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                        <span class="ts-prefs__name">{{ $name }}</span>
                        <i class="bi bi-check-circle-fill ts-prefs__check" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>
        </div>
    </section>
        <div class="d-flex align-items-center justify-content-between mb-2 mt-3">
            <h2 class="h6 mb-0" style="color:#333;font-weight:700">المتاجر التي أتابعها</h2>
            <a href="{{ route('customer.followed-stores.index') }}" class="small" style="color:var(--ts-emerald, #0B5F4A)">عرض الكل</a>
        </div>
        @if (($followedStores ?? collect())->isEmpty())
            <div class="text-muted small border rounded-2 p-3 mb-4" style="border-color:var(--ts-soft-border, #ededed) !important">
                لا تتابع أي متجر حالياً. <a href="{{ route('home') }}" style="color:var(--ts-emerald, #0B5F4A)">تصفح المتاجر</a>
            </div>
        @else
            <div class="d-flex flex-wrap gap-2 mb-4">
                @foreach ($followedStores as $store)
                    <a href="{{ route('stores.show', $store) }}" class="d-inline-flex align-items-center gap-2 border rounded-pill px-2 py-1 text-decoration-none" style="border-color:var(--ts-soft-border, #ededed) !important; color:#333;">
                        <span class="rounded-circle bg-light d-inline-grid" style="width:28px;height:28px;place-items:center;overflow:hidden;">
                            @if ($store->store_logo)
                                <img src="{{ asset('storage/'.$store->store_logo) }}" alt="" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                <i class="bi bi-shop small text-muted"></i>
                            @endif
                        </span>
                        <span class="small fw-semibold" translate="no">{{ $store->store_name }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="d-flex align-items-center justify-content-between mb-2">
            <h2 class="h6 mb-0" style="color:#333;font-weight:700">طلباتي الأخيرة</h2>
            <a href="{{ route('customer.orders.index') }}" class="small" style="color:var(--ts-emerald, #0B5F4A)">عرض الكل</a>
        </div>

        @if (($recentOrders ?? collect())->isEmpty())
            <div class="text-center text-muted py-4 border rounded-2" style="border-color:var(--ts-soft-border, #ededed) !important">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                لا توجد طلبات حتى الآن.
                <div class="mt-2"><a href="{{ route('home') }}" class="btn btn-sm btn-buy">ابدأ التسوق</a></div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>رقم الطلب</th><th>التاريخ</th><th>الحالة</th><th>المبلغ</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($recentOrders as $order)
                            @php($label = \App\Models\Order::STATUS_LABELS[$order->status] ?? $order->status)
                            <tr>
                                <td class="fw-semibold">{{ $order->order_number ?? ('#'.$order->id) }}</td>
                                <td class="text-muted small">{{ $order->created_at?->format('Y-m-d') }}</td>
                                <td><span class="ts-order-chip {{ $order->status }}">{{ $label }}</span></td>
                                <td>{{ method_exists($order, 'getFormattedTotalAttribute') || isset($order->formatted_total) ? $order->formatted_total : number_format(($order->total ?? 0) / 100).' ل.س' }}</td>
                                <td class="text-end"><a href="{{ route('customer.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">التفاصيل</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@push('styles')
<style>
.ts-prefs { padding: 1rem; border: 1px solid var(--ts-border, #E8DFCC); border-radius: 16px; background: var(--ts-surface, #fff); }
.ts-prefs__head { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
.ts-prefs__icon { display: grid; flex: 0 0 42px; width: 42px; height: 42px; place-items: center; border-radius: 12px; background: var(--ts-emerald, #0B5F4A); color: var(--ts-gold-light, #F1D9A0); font-size: 1.15rem; }
.ts-prefs__title { margin: 0; color: var(--ts-ink, #1d2a24); font-size: 1rem; font-weight: 800; }
.ts-prefs__sub { margin: .1rem 0 0; color: var(--ts-muted, #6b7770); font-size: .8rem; }
.ts-prefs__group + .ts-prefs__group { margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--ts-border, #E8DFCC); }
.ts-prefs__label { display: flex; align-items: center; gap: .4rem; margin-bottom: .55rem; color: var(--ts-ink, #1d2a24); font-size: .85rem; font-weight: 700; }
.ts-prefs__label i { color: var(--ts-emerald, #0B5F4A); }
.ts-prefs__options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; margin: 0; }
.ts-prefs__opt { position: relative; display: flex; flex-direction: column; align-items: center; gap: .4rem; min-width: 0; padding: .75rem .4rem .6rem; border: 1.5px solid var(--ts-border, #E8DFCC); border-radius: 14px; background: var(--ts-surface-2, #FBF8F2); color: var(--ts-ink, #1d2a24); font: inherit; cursor: pointer; transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, transform .15s ease; }
.ts-prefs__opt:hover { border-color: var(--ts-gold, #C9962C); transform: translateY(-1px); }
.ts-prefs__opt:focus-visible { outline: 2px solid var(--ts-gold, #C9962C); outline-offset: 2px; }
.ts-prefs__badge { display: grid; width: 40px; height: 40px; place-items: center; border-radius: 50%; background: var(--ts-surface, #fff); box-shadow: inset 0 0 0 1px var(--ts-border, #E8DFCC); color: var(--ts-emerald, #0B5F4A); font-size: .9rem; font-weight: 800; }
.ts-prefs__opt--light .ts-prefs__badge { color: #C9962C; }
.ts-prefs__opt--dark .ts-prefs__badge { background: #12261F; color: #F1D9A0; box-shadow: none; }
.ts-prefs__opt--system .ts-prefs__badge { color: var(--ts-emerald, #0B5F4A); }
.ts-prefs__name { max-width: 100%; overflow: hidden; font-size: .82rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
.ts-prefs__check { position: absolute; top: 6px; inset-inline-end: 6px; color: var(--ts-emerald, #0B5F4A); font-size: .9rem; opacity: 0; transform: scale(.6); transition: opacity .15s ease, transform .15s ease; }
.ts-prefs__opt.is-active { border-color: var(--ts-emerald, #0B5F4A); background: var(--ts-emerald-soft, #E5F1EC); box-shadow: 0 6px 16px -10px rgba(11,95,74,.6); }
.ts-prefs__opt.is-active .ts-prefs__name { color: var(--ts-emerald, #0B5F4A); }
.ts-prefs__opt.is-active .ts-prefs__check { opacity: 1; transform: none; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // نفس مصدر الوضع الذي يستخدمه زر الهيدر (partials/theme-head)
    var group = document.getElementById('ts-theme');
    if (!group || !window.tsTheme) return;
    var opts = group.querySelectorAll('[data-theme-pref]');
    var mark = function (pref) {
        opts.forEach(function (b) {
            var on = b.getAttribute('data-theme-pref') === pref;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
    };
    mark(window.tsTheme.get());
    opts.forEach(function (b) {
        b.addEventListener('click', function () { window.tsTheme.set(b.getAttribute('data-theme-pref')); mark(b.getAttribute('data-theme-pref')); });
    });
    document.addEventListener('ts:themechange', function (e) { mark(e.detail.pref); });
})();
</script>
@endpush
@endsection
